<?php

namespace App\Http\Controllers;

use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\Order;
use App\Models\CustomerLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicOrderController extends Controller
{
    public function show(string $token)
    {
        $catalogue = Catalogue::where('order_token', $token)
            ->with(['designs' => fn($q) => $q->orderBy('sort_order')])
            ->firstOrFail();

        // Sold-out: only when admin explicitly closes the catalogue
        $soldOut = $catalogue->status !== 'open';

        return view('public.order', compact('catalogue', 'soldOut'));
    }

    public function submit(Request $request, string $token)
    {
        $catalogue = Catalogue::where('order_token', $token)
            ->with(['designs'])
            ->firstOrFail();

        // Guard: reject submission if catalogue has been closed by admin
        if ($catalogue->status !== 'open') {
            return redirect()->route('order.show', $token);
        }

        // Validate customer details + collective size quantities
        $request->validate([
            'customer_name'   => 'required|string|max:255',
            'submitted_email' => 'required|email|max:255',
            'city'            => 'required|string|max:100',
            'notes'           => 'nullable|string|max:1000',
            'qty_xs'          => 'nullable|integer|min:0',
            'qty_s'           => 'nullable|integer|min:0',
            'qty_m'           => 'nullable|integer|min:0',
            'qty_l'           => 'nullable|integer|min:0',
            'qty_xl'          => 'nullable|integer|min:0',
        ]);

        // Collective sizes — same quantity applied to every design
        $qtyXS = max(0, (int) $request->input('qty_xs', 0));
        $qtyS  = max(0, (int) $request->input('qty_s',  0));
        $qtyM  = max(0, (int) $request->input('qty_m',  0));
        $qtyL  = max(0, (int) $request->input('qty_l',  0));
        $qtyXL = max(0, (int) $request->input('qty_xl', 0));

        $piecesPerDesign = $qtyXS + $qtyS + $qtyM + $qtyL + $qtyXL;

        if ($piecesPerDesign === 0) {
            return back()
                ->withErrors(['qty_s' => 'Please enter at least one piece quantity to place an order.'])
                ->withInput();
        }

        // Determine pricing tier: discount applies when total qty exceeds benchmark
        $benchmark      = $catalogue->quantity_benchmark;
        $useDiscount    = $benchmark !== null && $piecesPerDesign > $benchmark;

        // Calculate total amount across all designs using the effective price
        $totalAmount = (int) round($catalogue->designs->sum(function ($design) use ($piecesPerDesign, $useDiscount) {
            $price = ($useDiscount && $design->discount_price !== null)
                ? round((float) $design->discount_price)
                : round((float) $design->selling_price);
            return $piecesPerDesign * $price;
        }));

        // Verify the customer exists in the system — new customers must be registered by admin first
        $customer = Customer::where('email', $request->input('submitted_email'))->first();

        if (! $customer) {
            return back()
                ->withInput()
                ->with('customer_not_found', true);
        }

        // Prevent duplicate orders — one order per customer per catalogue
        $alreadyOrdered = Order::where('customer_id', $customer->id)
            ->where('catalogue_id', $catalogue->id)
            ->exists();

        if ($alreadyOrdered) {
            return back()
                ->withInput()
                ->with('duplicate_order', true);
        }

        $orderId = null;

        DB::transaction(function () use ($request, $catalogue, $customer, $qtyXS, $qtyS, $qtyM, $qtyL, $qtyXL, $piecesPerDesign, $totalAmount, $useDiscount, &$orderId) {

            // Create the order
            $order = Order::create([
                'catalogue_id'        => $catalogue->id,
                'customer_id'         => $customer->id,
                'status'              => 'received',
                'total_amount'        => $totalAmount,
                'total_paid'          => 0,
                'outstanding_balance' => $totalAmount,
                'submitted_name'      => $request->input('customer_name'),
                'submitted_city'      => $request->input('city'),
                'submitted_email'     => $request->input('submitted_email'),
                'submitted_at'        => now(),
                'notes'               => $request->input('notes'),
            ]);

            // Create one OrderItem per design — same sizes for every design
            foreach ($catalogue->designs as $design) {
                $unitPrice  = (int) round(($useDiscount && $design->discount_price !== null)
                    ? (float) $design->discount_price
                    : (float) $design->selling_price);
                $lineAmount = $piecesPerDesign * $unitPrice;

                $order->items()->create([
                    'design_id'    => $design->id,
                    'qty_xs'       => $qtyXS,
                    'qty_s'        => $qtyS,
                    'qty_m'        => $qtyM,
                    'qty_l'        => $qtyL,
                    'qty_xl'       => $qtyXL,
                    'unit_price'   => $unitPrice,
                    'total_amount' => $lineAmount,
                ]);
            }

            // Customer ledger entry — debit the order amount
            // 'order_charged' is the correct ENUM value for a new order debit
            CustomerLedger::create([
                'customer_id'             => $customer->id,
                'transaction_type'        => 'order_charged',
                'amount'                  => -$totalAmount, // negative = customer owes
                'running_advance_balance' => $customer->advance_credit_balance ?? 0,
                'reference_type'          => 'App\Models\Order',
                'reference_id'            => $order->id,
                'notes'                   => "Order #{$order->order_number} — {$catalogue->name}",
                'created_by'              => null, // nullable — see migration
            ]);

            $orderId = $order->id;
        });

        // Store order ID in session for the thank-you page
        session(['last_order_id' => $orderId]);

        return redirect()->route('order.thankyou', $token);
    }

    public function thankyou(string $token)
    {
        $catalogue = Catalogue::where('order_token', $token)->firstOrFail();

        $orderId = session('last_order_id');
        $order   = $orderId ? Order::with('catalogue')->find($orderId) : null;

        return view('public.thankyou', compact('catalogue', 'order'));
    }
}
