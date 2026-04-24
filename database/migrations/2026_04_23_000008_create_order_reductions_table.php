<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('reduced_by')->constrained('users');   // admin only
            $table->date('reduction_date');
            // How the financial adjustment was handled
            $table->enum('adjustment_type', [
                'surplus_to_advance',    // customer overpaid — surplus credited
                'balance_reduced',       // customer underpaid — new lower balance
                'no_payment_no_change',  // customer hadn't paid — total simply reduced
            ]);
            $table->decimal('original_total', 12, 2);
            $table->decimal('new_total', 12, 2);
            $table->decimal('adjustment_amount', 12, 2)->default(0.00); // surplus credited or balance delta
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_reduction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_reduction_id')->constrained('order_reductions')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('qty_reduced');
            $table->decimal('unit_price', 10, 2);            // snapshot at time of reduction
            $table->decimal('amount_reduced', 10, 2);        // qty_reduced * unit_price
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reduction_items');
        Schema::dropIfExists('order_reductions');
    }
};
