<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');

            // Quantity per size — 5 fixed sizes
            $table->unsignedSmallInteger('qty_xs')->default(0);
            $table->unsignedSmallInteger('qty_s')->default(0);
            $table->unsignedSmallInteger('qty_m')->default(0);
            $table->unsignedSmallInteger('qty_l')->default(0);
            $table->unsignedSmallInteger('qty_xl')->default(0);

            // Snapshot of the selling price at time of order
            $table->decimal('unit_price', 10, 2);

            // Computed totals stored for performance
            $table->unsignedSmallInteger('total_qty');       // sum of all sizes
            $table->decimal('total_amount', 12, 2);          // total_qty * unit_price

            $table->timestamps();

            $table->unique(['order_id', 'design_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
