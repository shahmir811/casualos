<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Naeem Pakki batch assignments no longer store a single design_id on the
     * parent record — designs are in production_assignment_np_designs instead.
     * Stitching assignments still populate design_id as before.
     *
     * MySQL allows multiple NULLs in a unique index, so the existing
     * (catalogue_id, design_id) unique constraint continues to work:
     *  - Stitching: unique per (catalogue, design)
     *  - NP batches: design_id IS NULL → multiple batches per catalogue allowed
     */
    public function up(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            $table->foreignId('design_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            $table->foreignId('design_id')->nullable(false)->change();
        });
    }
};
