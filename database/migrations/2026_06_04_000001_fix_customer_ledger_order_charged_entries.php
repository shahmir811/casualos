<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix 1: Flip all negative order_charged amounts to positive.
        // These were written with the wrong sign — either by an old version of PublicOrderController
        // or by the OrderPieceReassignmentController bug (amount => -$totalAdded).
        DB::statement("
            UPDATE customer_ledger
            SET amount = ABS(amount)
            WHERE transaction_type = 'order_charged' AND amount < 0
        ");

        // Fix 2: Insert missing order_charged entries for orders that have none.
        // Orders placed before the ledger entry was wired up in PublicOrderController
        // never had an order_charged row created, so the customer's debt was never recorded.
        $missing = DB::table('orders as o')
            ->leftJoin('customer_ledger as cl', function ($join) {
                $join->on('cl.reference_id', '=', 'o.id')
                     ->where('cl.reference_type', '=', 'App\Models\Order')
                     ->where('cl.transaction_type', '=', 'order_charged');
            })
            ->whereNull('cl.id')
            ->whereNotNull('o.customer_id')
            ->where('o.total_amount', '>', 0)
            ->select('o.id', 'o.customer_id', 'o.total_amount', 'o.order_number', 'o.created_at', 'o.catalogue_id')
            ->get();

        foreach ($missing as $order) {
            $catalogueName        = DB::table('catalogues')->where('id', $order->catalogue_id)->value('name') ?? 'Unknown';
            $advanceCreditBalance = DB::table('customers')->where('id', $order->customer_id)->value('advance_credit_balance') ?? 0;

            DB::table('customer_ledger')->insert([
                'customer_id'             => $order->customer_id,
                'transaction_type'        => 'order_charged',
                'amount'                  => $order->total_amount,
                'running_advance_balance' => $advanceCreditBalance,
                'reference_type'          => 'App\Models\Order',
                'reference_id'            => $order->id,
                'notes'                   => "Order #{$order->order_number} — {$catalogueName}",
                'created_by'              => null,
                'created_at'              => $order->created_at,
                'updated_at'              => $order->created_at,
            ]);
        }
    }

    public function down(): void
    {
        // Data corrections are not safely reversible
    }
};
