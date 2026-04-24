<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Dispatch batches — a customer order can be dispatched in one or multiple batches.
         * Full payment must be cleared before any dispatch.
         * Each batch has: batch_number, dispatch_date, shipping_address, cargo_document.
         * Dispatch reduces packed_inventory quantities.
         */
        Schema::create('dispatch_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->unsignedSmallInteger('batch_number');   // sequential per order (1, 2, 3...)
            $table->date('dispatch_date');
            $table->text('shipping_address');
            $table->string('cargo_document')->nullable();   // stored file path (PDF/image)
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();

            $table->unique(['order_id', 'batch_number']);
        });

        Schema::create('dispatch_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_batch_id')
                  ->constrained('dispatch_batches')
                  ->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_batch_items');
        Schema::dropIfExists('dispatch_batches');
    }
};
