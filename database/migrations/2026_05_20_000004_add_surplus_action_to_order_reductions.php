<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_reductions', function (Blueprint $table) {
            // What to do with the reduction surplus
            $table->enum('surplus_action', ['none', 'credit_to_advance', 'refund'])->default('none')->after('notes');
            // Also replace the old adjustment_type enum with the correct values
        });

        // Replace the old adjustment_type enum values with the correct business ones
        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE order_reductions MODIFY COLUMN adjustment_type ENUM('damage','short_supply','price_correction','other') NOT NULL"
        );
    }

    public function down(): void
    {
        Schema::table('order_reductions', function (Blueprint $table) {
            $table->dropColumn('surplus_action');
        });

        \Illuminate\Support\Facades\DB::statement(
            "ALTER TABLE order_reductions MODIFY COLUMN adjustment_type ENUM('surplus_to_advance','balance_reduced','no_payment_no_change') NOT NULL"
        );
    }
};
