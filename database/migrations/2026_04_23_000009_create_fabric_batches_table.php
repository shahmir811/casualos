<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Fabric batch arrivals from the embroidery factory.
         * Manager records each batch as it arrives.
         */
        Schema::create('fabric_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->date('arrival_date');
            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('fabric_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fabric_batch_id')->constrained('fabric_batches')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['fabric_batch_id', 'design_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabric_batch_items');
        Schema::dropIfExists('fabric_batches');
    }
};
