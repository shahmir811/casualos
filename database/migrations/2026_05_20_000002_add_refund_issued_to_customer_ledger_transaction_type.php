<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE customer_ledger MODIFY COLUMN transaction_type ENUM('advance_received','order_charged','payment_received','credit_applied','order_reduced','surplus_to_advance','refund_issued') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM customer_ledger WHERE transaction_type = 'refund_issued'");
        DB::statement("ALTER TABLE customer_ledger MODIFY COLUMN transaction_type ENUM('advance_received','order_charged','payment_received','credit_applied','order_reduced','surplus_to_advance') NOT NULL");
    }
};
