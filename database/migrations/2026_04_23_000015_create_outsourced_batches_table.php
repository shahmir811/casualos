<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Outsourced design batches — fully finished suits arriving from external factories.
         * These go directly into packed_inventory (no pressing/packing needed).
         * Manager records each batch with design quantities by size.
         */
        Schema::create('outsourced_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('outsourced_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outsourced_batch_id')
                  ->constrained('outsourced_batches')
                  ->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outsourced_batch_items');
        Schema::dropIfExists('outsourced_batches');
    }
};
