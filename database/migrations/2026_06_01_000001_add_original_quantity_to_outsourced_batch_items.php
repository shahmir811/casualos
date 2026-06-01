<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outsourced_batch_items', function (Blueprint $table) {
            $table->unsignedInteger('original_quantity')->default(0)->after('quantity');
        });

        // Seed existing rows: for rows not yet touched by dispatch, original = current quantity.
        // Rows already at 0 (fully dispatched) retain 0 — historical data cannot be recovered.
        DB::statement('UPDATE outsourced_batch_items SET original_quantity = quantity');
    }

    public function down(): void
    {
        Schema::table('outsourced_batch_items', function (Blueprint $table) {
            $table->dropColumn('original_quantity');
        });
    }
};
