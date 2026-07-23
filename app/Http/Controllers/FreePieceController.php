<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Design;
use App\Models\FreePiece;
use App\Models\Order;
use App\Services\AdvanceCreditAutoApplyService;
use App\Services\ProductionAssignmentAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FreePieceController extends Controller
{
    private const SIZES = ['xs', 's', 'm', 'l', 'xl'];

    private function activeCatalogue(): ?Catalogue
    {
        $catalogueId = (int) session('active_catalogue_id', 0) ?: null;

        return $catalogueId
            ? Catalogue::find($catalogueId)
            : Catalogue::orderByDesc('created_at')->first();
    }

    /**
     * Free pieces stay uniform across every design in a catalogue (they originate
     * from a deleted order, which itself applied one uniform size split to every
     * design — same rule as the public order form). So "available" per size is a
     * single number, not a sum across designs — read as the MIN across designs
     * carrying that size so a display/assign cap can never oversell if a design
     * were ever to drift out of sync.
     */
    private function sizeTotals(Catalogue $catalogue): \Illuminate\Support\Collection
    {
        $byDesign = FreePiece::where('catalogue_id', $catalogue->id)->get()->groupBy('design_id');

        return collect(self::SIZES)->mapWithKeys(function ($size) use ($byDesign) {
            $values = $byDesign->map(fn($rows) => $rows->firstWhere('size', $size)?->quantity ?? 0);

            return [$size => $values->isEmpty() ? 0 : $values->min()];
        });
    }

    public function index()
    {
        $catalogue = $this->activeCatalogue();

        $freePieces = $catalogue
            ? FreePiece::where('catalogue_id', $catalogue->id)->with('design')->get()->groupBy('design_id')
            : collect();

        $sizeTotals = $catalogue ? $this->sizeTotals($catalogue) : collect(self::SIZES)->mapWithKeys(fn($s) => [$s => 0]);
        $grandTotal = $sizeTotals->sum();

        return view('free-pieces.index', compact('catalogue', 'freePieces', 'sizeTotals', 'grandTotal'));
    }

    public function assign()
    {
        $catalogue = $this->activeCatalogue();
        abort_unless($catalogue, 404);

        $sizeTotals = $this->sizeTotals($catalogue);

        $existingTargets = Order::where('catalogue_id', $catalogue->id)
            ->whereNotIn('status', ['dispatched', 'cancelled'])
            ->with('customer')
            ->get();

        $orderedCustomerIds = Order::where('catalogue_id', $catalogue->id)->pluck('customer_id');
        $newCustomerTargets = Customer::whereNotIn('id', $orderedCustomerIds)->orderBy('name')->get();

        return view('free-pieces.assign', compact('catalogue', 'sizeTotals', 'existingTargets', 'newCustomerTargets'));
    }

    public function store(Request $request)
    {
        $catalogue = $this->activeCatalogue();
        abort_unless($catalogue, 404);

        $request->validate([
            'qty_xs'      => 'nullable|integer|min:0',
            'qty_s'       => 'nullable|integer|min:0',
            'qty_m'       => 'nullable|integer|min:0',
            'qty_l'       => 'nullable|integer|min:0',
            'qty_xl'      => 'nullable|integer|min:0',
            'target_type' => 'required|in:existing_order,new_customer',
            'target_id'   => 'required|integer',
        ]);

        $requestedQty = collect(self::SIZES)->mapWithKeys(
            fn($size) => [$size => max(0, (int) $request->input('qty_' . $size, 0))]
        );
        $piecesPerDesign = $requestedQty->sum();

        if ($piecesPerDesign === 0) {
            return back()->withErrors(['qty_s' => 'Please enter at least one piece quantity.'])->withInput();
        }

        $targetType = $request->input('target_type');
        $targetId   = (int) $request->input('target_id');

        if ($targetType === 'existing_order') {
            $validTarget = Order::where('id', $targetId)
                ->where('catalogue_id', $catalogue->id)
                ->whereNotIn('status', ['dispatched', 'cancelled'])
                ->exists();
        } else {
            $validTarget = Customer::where('id', $targetId)->exists();
        }

        if (! $validTarget) {
            return back()->withErrors(['target_id' => 'Invalid target for this assignment.'])->withInput();
        }

        try {
            DB::transaction(function () use ($catalogue, $requestedQty, $piecesPerDesign, $targetType, $targetId) {
                // Lock every free-piece row for this catalogue up front so concurrent
                // assignments can't double-spend the same stock.
                $byDesign = FreePiece::where('catalogue_id', $catalogue->id)
                    ->lockForUpdate()
                    ->get()
                    ->groupBy('design_id');

                if ($byDesign->isEmpty()) {
                    throw new \RuntimeException('No free pieces available for this catalogue.');
                }

                // Guard: requested qty per size must not exceed what's available on
                // EVERY design carrying free pieces — they're expected to always be
                // uniform, but this defends against any drift.
                foreach (self::SIZES as $size) {
                    $qty = $requestedQty[$size];
                    if ($qty <= 0) {
                        continue;
                    }
                    foreach ($byDesign as $rows) {
                        $available = $rows->firstWhere('size', $size)?->quantity ?? 0;
                        if ($qty > $available) {
                            throw new \RuntimeException('Requested quantity exceeds available free pieces for size ' . strtoupper($size) . '.');
                        }
                    }
                }

                // Decrement every design's free-piece rows by the requested amount —
                // one uniform quantity applied across the board, mirroring how the
                // public order form applies one size split to every design.
                foreach ($byDesign as $rows) {
                    foreach (self::SIZES as $size) {
                        $qty = $requestedQty[$size];
                        if ($qty <= 0) {
                            continue;
                        }
                        $row    = $rows->firstWhere('size', $size);
                        $newQty = $row->quantity - $qty;
                        if ($newQty <= 0) {
                            $row->delete();
                        } else {
                            $row->update(['quantity' => $newQty]);
                        }
                    }
                }

                $designIds = $byDesign->keys()->all();

                if ($targetType === 'existing_order') {
                    $targetOrder = Order::where('id', $targetId)->lockForUpdate()->first();
                    $targetOrder->load(['items', 'customer']);
                    $itemsByDesign = $targetOrder->items->keyBy('design_id');

                    $totalAdded = 0;
                    foreach ($designIds as $designId) {
                        $item = $itemsByDesign->get($designId);
                        if (! $item) {
                            continue;
                        }

                        foreach (self::SIZES as $size) {
                            $qty = $requestedQty[$size];
                            if ($qty > 0) {
                                $item->increment('qty_' . $size, $qty);
                            }
                        }
                        $item->refresh();
                        $item->save(); // OrderItem::booted() recomputes total_qty/total_amount

                        $totalAdded += $item->unit_price * $piecesPerDesign;
                    }

                    if ($totalAdded > 0) {
                        $targetOrder->increment('total_amount', $totalAdded);
                        $targetOrder->increment('outstanding_balance', $totalAdded);

                        CustomerLedger::create([
                            'customer_id'             => $targetOrder->customer_id,
                            'transaction_type'        => 'order_charged',
                            'amount'                  => $totalAdded,
                            'running_advance_balance' => (float) $targetOrder->customer->advance_credit_balance,
                            'reference_type'          => Order::class,
                            'reference_id'            => $targetOrder->id,
                            'notes'                   => "Free pieces assigned from catalogue {$catalogue->name}",
                            'created_by'              => Auth::id(),
                        ]);
                    }

                    app(ProductionAssignmentAlertService::class)
                        ->checkOrder($targetOrder, $designIds, 'free_pieces_assigned');
                } else {
                    $customer = Customer::where('id', $targetId)->lockForUpdate()->first();

                    // Duplicate guard, mirrors PublicOrderController::submit() — re-checked
                    // here (not just at form-render time) to close a race between two
                    // concurrent submissions targeting the same customer.
                    $alreadyOrdered = Order::where('customer_id', $customer->id)
                        ->where('catalogue_id', $catalogue->id)
                        ->exists();
                    if ($alreadyOrdered) {
                        throw new \RuntimeException("Customer #{$customer->id} already has an order on this catalogue.");
                    }

                    $order = Order::create([
                        'catalogue_id'        => $catalogue->id,
                        'customer_id'         => $customer->id,
                        'status'              => 'received',
                        'total_amount'        => 0,
                        'total_paid'          => 0,
                        'outstanding_balance' => 0,
                        'submitted_name'      => $customer->name,
                        'submitted_city'      => $customer->city,
                        'submitted_email'     => $customer->email,
                        'submitted_at'        => now(),
                        'notes'               => 'Created from Free Pieces assignment.',
                    ]);

                    // Same pricing rule as PublicOrderController::submit() — one uniform
                    // piecesPerDesign compared against the catalogue benchmark, applied
                    // identically to every design.
                    $benchmark   = $catalogue->quantity_benchmark;
                    $useDiscount = $benchmark !== null && $piecesPerDesign > $benchmark;
                    $totalAmount = 0;

                    foreach ($designIds as $designId) {
                        $design    = Design::find($designId);
                        $unitPrice = (int) round(
                            ($useDiscount && $design->discount_price !== null)
                                ? (float) $design->discount_price
                                : (float) $design->selling_price
                        );

                        $lineAmount = $piecesPerDesign * $unitPrice;
                        $order->items()->create([
                            'design_id'    => $designId,
                            'qty_xs'       => $requestedQty['xs'],
                            'qty_s'        => $requestedQty['s'],
                            'qty_m'        => $requestedQty['m'],
                            'qty_l'        => $requestedQty['l'],
                            'qty_xl'       => $requestedQty['xl'],
                            'unit_price'   => $unitPrice,
                            'total_amount' => $lineAmount,
                        ]);
                        $totalAmount += $lineAmount;
                    }

                    $order->update(['total_amount' => $totalAmount, 'outstanding_balance' => $totalAmount]);

                    CustomerLedger::create([
                        'customer_id'             => $customer->id,
                        'transaction_type'        => 'order_charged',
                        'amount'                  => $totalAmount,
                        'running_advance_balance' => (float) ($customer->advance_credit_balance ?? 0),
                        'reference_type'          => Order::class,
                        'reference_id'            => $order->id,
                        'notes'                   => "Order #{$order->order_number} — created from free pieces ({$catalogue->name})",
                        'created_by'              => Auth::id(),
                    ]);

                    // Same auto-apply PublicOrderController::submit() does for a brand-new
                    // order — uses up any advance credit the customer already holds and
                    // auto-confirms if the applied amount clears the configured threshold.
                    $autoAppliedPayment = app(AdvanceCreditAutoApplyService::class)->apply($order, $customer);

                    if ($autoAppliedPayment) {
                        activity()
                            ->performedOn($order)
                            ->causedBy(Auth::user())
                            ->event('detail')
                            ->withProperties([
                                'order'          => 'Order #' . $order->order_number,
                                'customer'       => $customer->name,
                                'amount'         => 'PKR ' . number_format((float) $autoAppliedPayment->amount, 0),
                                'auto_confirmed' => $order->status === 'confirmed' ? 'Yes' : 'No',
                            ])
                            ->log('Payment #' . $order->order_number . 'p' . $autoAppliedPayment->sequence_number
                                . ' of PKR ' . number_format((float) $autoAppliedPayment->amount, 0)
                                . ' auto-applied from advance credit on Order #' . $order->order_number);
                    }

                    app(ProductionAssignmentAlertService::class)
                        ->checkOrder($order, $designIds, 'free_pieces_assigned');
                }

                activity()
                    ->causedBy(Auth::user())
                    ->event('detail')
                    ->withProperties(['catalogue' => $catalogue->name])
                    ->log('Free pieces assigned for catalogue ' . $catalogue->name);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['assignments' => $e->getMessage()])->withInput();
        }

        return redirect()->route('free-pieces.index')->with('success', 'Free pieces assigned.');
    }
}
