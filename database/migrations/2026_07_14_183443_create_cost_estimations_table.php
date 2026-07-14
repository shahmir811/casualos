<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_estimations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('estimation_date')->nullable();
            $table->string('stitched_by')->nullable();
            $table->string('cutting_by')->nullable();
            $table->unsignedInteger('production_plan_qty')->default(0);
            $table->unsignedInteger('actual_production_qty')->nullable();
            $table->integer('production_variation')->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('per_unit_cost', 10, 2)->nullable();
            $table->decimal('market_rate', 10, 2)->nullable();
            $table->decimal('margin', 10, 2)->nullable();
            $table->string('approved_by')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_estimations');
    }
};
