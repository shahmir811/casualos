<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Rebuild naeem_pakki_returns ────────────────────────────────
        // Old: linked to naeem_pakki_send_id (one total qty per send)
        // New: linked to production_assignment_id (one header per batch return)
        Schema::table('naeem_pakki_returns', function (Blueprint $table) {
            $table->dropForeign(['naeem_pakki_send_id']);
            $table->dropColumn('naeem_pakki_send_id');
            $table->foreignId('production_assignment_id')
                  ->after('id')
                  ->constrained('production_assignments')
                  ->cascadeOnDelete();
        });

        // ── 2. Rebuild naeem_pakki_return_items ───────────────────────────
        // Old: naeem_pakki_return_id + size + quantity (size-based, old model)
        // New: naeem_pakki_return_id + np_design_id + quantity (per-design per batch)
        Schema::table('naeem_pakki_return_items', function (Blueprint $table) {
            $table->dropColumn('size');
            $table->foreignId('np_design_id')
                  ->after('naeem_pakki_return_id')
                  ->constrained('production_assignment_np_designs')
                  ->cascadeOnDelete();
        });

        // ── 3. Drop the single-value return columns from np_designs ───────
        // Per-design return totals will now be computed from return_items.
        Schema::table('production_assignment_np_designs', function (Blueprint $table) {
            $table->dropColumn(['returned_quantity', 'return_date']);
        });
    }

    public function down(): void
    {
        Schema::table('production_assignment_np_designs', function (Blueprint $table) {
            $table->unsignedSmallInteger('returned_quantity')->nullable()->after('quantity');
            $table->date('return_date')->nullable()->after('returned_quantity');
        });

        Schema::table('naeem_pakki_return_items', function (Blueprint $table) {
            $table->dropForeign(['np_design_id']);
            $table->dropColumn('np_design_id');
            $table->string('size')->after('naeem_pakki_return_id');
        });

        Schema::table('naeem_pakki_returns', function (Blueprint $table) {
            $table->dropForeign(['production_assignment_id']);
            $table->dropColumn('production_assignment_id');
            $table->foreignId('naeem_pakki_send_id')
                  ->after('id')
                  ->constrained('naeem_pakki_sends')
                  ->cascadeOnDelete();
        });
    }
};
