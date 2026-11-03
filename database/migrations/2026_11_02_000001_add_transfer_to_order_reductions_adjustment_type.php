<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 'transfer' is written only by OrderPieceMoveController — never
        // selectable from the Log Reduction form's adjustment_type dropdown,
        // which keeps its own hardcoded damage/short_supply/price_correction/
        // other list unchanged.
        DB::statement(
            "ALTER TABLE order_reductions MODIFY COLUMN adjustment_type ENUM('damage','short_supply','price_correction','other','transfer') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE order_reductions MODIFY COLUMN adjustment_type ENUM('damage','short_supply','price_correction','other') NOT NULL"
        );
    }
};
