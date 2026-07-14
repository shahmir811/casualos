<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;

/**
 * Automatically applies a customer's existing advance credit balance to a
 * brand-new order at submission time, recording it as a real Payment — the
 * same end result as an accountant manually recording an "Advance" payment,
 * without the manual step.
 *
 * Unlike PaymentController::store()'s `advance` branch (built for an HTTP
 * request with an arbitrary entered amount that can exceed the available
 * balance and split into a credit portion + payment portion), the amount
 * here is always capped at min(balance, order total). Since the order is
 * brand new, total_paid starts at 0, so the applied amount can never create
 * a surplus or need a payment-portion ledger entry.
 */
class AdvanceCreditAutoApplyService
{
    public function apply(Order $order, Customer $customer): ?Payment
    {
        $available = (float) $customer->advance_credit_balance;

        if ($available <= 0) {
            return null;
        }

        $amount = min($available, (float) $order->total_amount);

        if ($amount <= 0) {
            return null;
        }

        $nextSequence = (Payment::where('order_id', $order->id)->max('sequence_number') ?? 0) + 1;

        $payment = Payment::create([
            'order_id'        => $order->id,
            'sequence_number' => $nextSequence,
            'customer_id'     => $order->customer_id,
            'amount'          => $amount,
            'payment_type'    => 'advance',
            'bank_account_id' => null,
            'title_given'     => 'Advance',
            'payment_date'    => now(),
            'notes'           => 'Auto-applied from advance credit balance',
            'receipt_image'   => null,
            'logged_by'       => null,
        ]);

        // Credit portion consumed — no ledger entry, same convention as the
        // "advance" branch in PaymentController::store().
        $customer->decrement('advance_credit_balance', $amount);

        $newOutstanding = max(0, (float) $order->total_amount - $amount);

        $statusUpdate = [
            'total_paid'          => $amount,
            'outstanding_balance' => $newOutstanding,
        ];

        if ($newOutstanding <= 0) {
            $statusUpdate['is_flagged'] = false;
        }

        if ($amount > config('casualite.advance_credit_auto_confirm_threshold')) {
            $statusUpdate['status'] = 'confirmed';
        }

        $order->update($statusUpdate);

        return $payment;
    }
}
