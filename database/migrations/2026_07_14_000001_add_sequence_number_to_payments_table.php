<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedInteger('sequence_number')->nullable()->after('order_id');
        });

        // Backfill: assign 1..n per order, ordered chronologically (payment_date, then id
        // as a tiebreaker for same-day payments). Reads whatever is actually in the table
        // at migrate-time, so this is safe to run against any environment's real data.
        $orderIds = DB::table('payments')->whereNotNull('order_id')->distinct()->pluck('order_id');

        foreach ($orderIds as $orderId) {
            $payments = DB::table('payments')
                ->where('order_id', $orderId)
                ->orderBy('payment_date')
                ->orderBy('id')
                ->get(['id']);

            $sequence = 1;
            foreach ($payments as $payment) {
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update(['sequence_number' => $sequence]);
                $sequence++;
            }
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['order_id', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'sequence_number']);
            $table->dropColumn('sequence_number');
        });
    }
};
