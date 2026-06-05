<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $order = DB::table('orders')->where('order_number', '524308')->first();
        if (! $order) return;

        $surplus = max(0, $order->total_paid - $order->total_amount);
        if ($surplus <= 0) return;

        // Only update advance_credit_balance — no ledger entry needed because
        // the overpayment is already visible in the ledger via payment_received
        // entries that exceed the order_charged amount.
        DB::table('customers')
            ->where('id', $order->customer_id)
            ->increment('advance_credit_balance', $surplus);
    }

    public function down(): void
    {
        $order = DB::table('orders')->where('order_number', '524308')->first();
        if (! $order) return;

        $surplus = max(0, $order->total_paid - $order->total_amount);
        if ($surplus <= 0) return;

        DB::table('customers')
            ->where('id', $order->customer_id)
            ->decrement('advance_credit_balance', $surplus);
    }
};
