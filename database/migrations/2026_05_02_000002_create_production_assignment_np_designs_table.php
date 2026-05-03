<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the per-design details for a Naeem Pakki batch assignment.
     * One ProductionAssignment (destination=naeem_pakki, design_id=null) can
     * contain many designs here — each with its own quantity and per-piece rate.
     *
     * Stitching assignments continue to use production_assignment_items (sizes).
     */
    public function up(): void
    {
        Schema::dropIfExists('production_assignment_np_designs');

        Schema::create('production_assignment_np_designs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_assignment_id');
            $table->foreign('production_assignment_id', 'panpd_pa_fk')
                  ->references('id')->on('production_assignments')
                  ->cascadeOnDelete();
            $table->unsignedBigInteger('design_id');
            $table->foreign('design_id', 'panpd_design_fk')
                  ->references('id')->on('designs');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('per_piece_price', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['production_assignment_id', 'design_id'], 'panpd_pa_design_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_assignment_np_designs');
    }
};
