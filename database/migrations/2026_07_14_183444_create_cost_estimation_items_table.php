<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_estimation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_estimation_id')->constrained()->cascadeOnDelete();
            $table->enum('category', [
                'fabric_cost',
                'dupatta',
                'block_printing',
                'dying',
                'computer_embroidery',
                'pakki_embroidery',
                'hand_embroidery',
                'accessories',
                'stitching_cost',
            ]);
            $table->string('particulars')->nullable();
            $table->decimal('avg', 10, 2)->nullable();
            $table->decimal('qty', 10, 2)->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_estimation_items');
    }
};
