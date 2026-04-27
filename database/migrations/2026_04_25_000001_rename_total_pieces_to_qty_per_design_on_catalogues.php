<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business logic correction:
     *
     * Previously: total_pieces = 70 meant 70 total across ALL designs.
     *   → pieces per design = 70 ÷ 7 designs = 10 each (WRONG interpretation)
     *
     * Corrected: qty_per_design = 70 means 70 pieces FROM EACH design.
     *   → total actual production = 70 × 7 designs = 490 pieces
     *
     * The column is renamed to make the meaning unambiguous.
     */
    public function up(): void
    {
        Schema::table('catalogues', function (Blueprint $table) {
            $table->renameColumn('total_pieces', 'qty_per_design');
        });
    }

    public function down(): void
    {
        Schema::table('catalogues', function (Blueprint $table) {
            $table->renameColumn('qty_per_design', 'total_pieces');
        });
    }
};
