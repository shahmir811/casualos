<?php

namespace App\Services;

use App\Exceptions\OrderPlacementException;
use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for the collective-quantity order model.
 *
 * WHY THIS EXISTS
 * ---------------
 * This logic used to live inline in PublicOrderController::submit(). The
 * Casualite mobile app adds a second caller (POST /api/orders) that must
 * produce byte-identical order totals to the web form — if the two ever
 * diverge, a customer sees one price in the app and is invoiced another.
 * So the calculation lives here once and both callers call it.
 *
 * Do not reimplement any of this in the React Native app. The app should
 * post sizes and render whatever `quote()` returns.
 *
 * THE COLLECTIVE-QUANTITY MODEL
 * -----------------------------
 * The customer enters ONE set of XS/S/M/L/XL quantities for the whole
 * catalogue, and that same set is applied to EVERY design in it.
 *
 *   1 XS on a 7-design catalogue = 7 XS pieces manufactured.
 *
 * So `piecesPerDesign` is the sum of the five size inputs, and the order
 * total is piecesPerDesign x unitPrice, summed over every design.
 *
 * PRICING TIER
 * ------------
 * When the catalogue has a `quantity_benchmark` and piecesPerDesign is
 * STRICTLY GREATER than it, each design's `discount_price` is used instead
 * of `selling_price` (designs with a null discount_price stay on selling
 * price). Note: CLAUDE.md describes this threshold as "meets or exceeds",
 * but the shipped behaviour has always been strictly greater-than. This
 * class preserves the shipped behaviour exactly — see BENCHMARK_IS_EXCLUSIVE
 * below. Changing it is a business decision, not a refactor.
 *
 * Prices are rounded to whole rupees per design before multiplying, which is
 * how the live total on the web order form has always been calculated.
 */
class OrderPlacementService
{
    /**
     * The discount tier requires piecesPerDesign > benchmark, not >=.
     * Flip this to false only with the client's explicit sign-off, and
     * update the live total in resources/views/public/order.blade.php to
     * match in the same change.
     */
    public const BENCHMARK_IS_EXCLUSIVE = true;

    public const SIZES = ['xs', 's', 'm', 'l', 'xl'];

    /**
     * Coerce raw request input into a clean size map. Missing, negative and
     * non-numeric values all become 0.
     *
     * @param  array<string, mixed>  $input  Keyed either 'xs' or 'qty_xs'.
     * @return array<string, int>            Keyed 'xs'...'xl'.
     */
    public function normaliseSizes(array $input): array
    {
        $sizes = [];

        foreach (self::SIZES as $size) {
            $value = $input[$size] ?? $input["qty_{$size}"] ?? 0;

            $sizes[$size] = max(0, (int) $value);
        }

        return $sizes;
    }

    /**
     * Price an order without writing anything.
     *
     * Safe to call on every keystroke from the app to drive a live running
     * total, and called internally by place() so the quoted total and the
     * stored total can never disagree.
     *
     * @param  array<string, int>  $sizes  Output of normaliseSizes().
     * @return array{
     *     pieces_per_design: int,
     *     total_pieces: int,
     *     design_count: int,
     *     uses_discount: bool,
     *     quantity_benchmark: int|null,
     *     total_amount: int,
     *     sizes: array<string, int>,
     *     lines: array<int, array<string, mixed>>
     * }
     *
     * @throws OrderPlacementException  when every size is zero.
     */
    public function quote(Catalogue $catalogue, array $sizes): array
    {
        $sizes = $this->normaliseSizes($sizes);

        $piecesPerDesign = array_sum($sizes);

        if ($piecesPerDesign === 0) {
            throw OrderPlacementException::noQuantity();
        }

        $catalogue->loadMissing('designs');

        $benchmark   = $catalogue->quantity_benchmark;
        $useDiscount = $this->qualifiesForDiscount($piecesPerDesign, $benchmark);

        $lines       = [];
        $totalAmount = 0;

        foreach ($catalogue->designs as $design) {
            $unitPrice  = $this->unitPriceFor($design, $useDiscount);
            $lineAmount = $piecesPerDesign * $unitPrice;

            $totalAmount += $lineAmount;

            $lines[] = [
                'design_id'  => $design->id,
                'design'     => $design->name,
                'unit_price' => $unitPrice,
                'xs'         => $sizes['xs'],
                's'          => $sizes['s'],
                'm'          => $sizes['m'],
                'l'          => $sizes['l'],
                'xl'         => $sizes['xl'],
                'pieces'     => $piecesPerDesign,
                'line_total' => $lineAmount,
            ];
        }

        return [
            'pieces_per_design'  => $piecesPerDesign,
            'total_pieces'       => $piecesPerDesign * $catalogue->designs->count(),
            'design_count'       => $catalogue->designs->count(),
            'uses_discount'      => $useDiscount,
            'quantity_benchmark' => $benchmark,
            'total_amount'       => $totalAmount,
            'sizes'              => $sizes,
            'lines'              => $lines,
        ];
    }

    /**
     * Place a real order: one Order, one OrderItem per design, a debit on the
     * customer ledger, and any advance credit auto-applied as a Payment.
     *
     * All guards run before the transaction opens.
     *
     * @param  array<string, int>     $sizes      Output of normaliseSizes().
     * @param  array<string, string|null>  $submitted  name, city, email, notes.
     *
     * @throws OrderPlacementException
     */
    public function place(Catalogue $catalogue, Customer $customer, array $sizes, array $submitted): Order
    {
        $this->assertCatalogueOpen($catalogue);
        $this->assertNotAlreadyOrdered($catalogue, $customer);

        $quote = $this->quote($catalogue, $sizes);

        $autoAppliedPayment = null;

        $order = DB::transaction(function () use ($catalogue, $customer, $quote, $submitted, &$autoAppliedPayment) {

            $order = Order::create([
                'catalogue_id'        => $catalogue->id,
                'customer_id'         => $customer->id,
                'status'              => 'received',
                'total_amount'        => $quote['total_amount'],
                'total_paid'          => 0,
                'outstanding_balance' => $quote['total_amount'],
                'submitted_name'      => $submitted['name']  ?? $customer->name,
                'submitted_city'      => $submitted['city']  ?? $customer->city,
                'submitted_email'     => $submitted['email'] ?? $customer->email,
                'submitted_at'        => now(),
                'notes'               => $submitted['notes'] ?? null,
            ]);

            // One OrderItem per design — the same size spread on each.
            foreach ($quote['lines'] as $line) {
                $order->items()->create([
                    'design_id'    => $line['design_id'],
                    'qty_xs'       => $line['xs'],
                    'qty_s'        => $line['s'],
                    'qty_m'        => $line['m'],
                    'qty_l'        => $line['l'],
                    'qty_xl'       => $line['xl'],
                    'unit_price'   => $line['unit_price'],
                    'total_amount' => $line['line_total'],
                ]);
            }

            // Debit the customer ledger. 'order_charged' is the correct ENUM
            // value for a new order — NOT 'order_placed', which does not exist.
            CustomerLedger::create([
                'customer_id'             => $customer->id,
                'transaction_type'        => 'order_charged',
                'amount'                  => $quote['total_amount'],
                'running_advance_balance' => $customer->advance_credit_balance ?? 0,
                'reference_type'          => Order::class,
                'reference_id'            => $order->id,
                'notes'                   => "Order #{$order->order_number} — {$catalogue->name}",
                'created_by'              => null, // nullable — see migration
            ]);

            $autoAppliedPayment = app(AdvanceCreditAutoApplyService::class)->apply($order, $customer);

            return $order;
        });

        if ($autoAppliedPayment) {
            $this->logAutoAppliedPayment($order->fresh(), $customer, $autoAppliedPayment);
        }

        return $order;
    }

    /**
     * Resolve the customer placing the order from the email typed into the
     * public form. New customers must be registered by an admin first.
     *
     * The mobile app does not need this — an authenticated request already
     * knows its Customer.
     *
     * @throws OrderPlacementException
     */
    public function resolveCustomerByEmail(string $email): Customer
    {
        $customer = Customer::where('email', $email)->first();

        if (! $customer) {
            throw OrderPlacementException::customerNotFound($email);
        }

        return $customer;
    }

    /** @throws OrderPlacementException */
    public function assertCatalogueOpen(Catalogue $catalogue): void
    {
        if ($catalogue->status !== 'open') {
            throw OrderPlacementException::catalogueClosed();
        }
    }

    /**
     * One order per customer per catalogue.
     *
     * @throws OrderPlacementException
     */
    public function assertNotAlreadyOrdered(Catalogue $catalogue, Customer $customer): void
    {
        $exists = Order::where('customer_id', $customer->id)
            ->where('catalogue_id', $catalogue->id)
            ->exists();

        if ($exists) {
            throw OrderPlacementException::duplicateOrder();
        }
    }

    protected function qualifiesForDiscount(int $piecesPerDesign, $benchmark): bool
    {
        if ($benchmark === null) {
            return false;
        }

        return self::BENCHMARK_IS_EXCLUSIVE
            ? $piecesPerDesign > $benchmark
            : $piecesPerDesign >= $benchmark;
    }

    protected function unitPriceFor($design, bool $useDiscount): int
    {
        $price = ($useDiscount && $design->discount_price !== null)
            ? (float) $design->discount_price
            : (float) $design->selling_price;

        return (int) round($price);
    }

    /**
     * Order uses LogsActivity, so activity() on it is safe. Do NOT copy this
     * onto production models — only Order and Catalogue carry the trait.
     */
    protected function logAutoAppliedPayment(Order $order, Customer $customer, $payment): void
    {
        activity()
            ->performedOn($order)
            ->event('detail')
            ->withProperties([
                'order'          => 'Order #' . $order->order_number,
                'customer'       => $customer->name,
                'amount'         => 'PKR ' . number_format((float) $payment->amount, 0),
                'auto_confirmed' => $order->status === 'confirmed' ? 'Yes' : 'No',
            ])
            ->log('Payment #' . $order->order_number . 'p' . $payment->sequence_number
                . ' of PKR ' . number_format((float) $payment->amount, 0)
                . ' auto-applied from advance credit on Order #' . $order->order_number);
    }
}
