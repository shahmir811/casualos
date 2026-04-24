<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Stitching unit daily returns — manager records completed suits daily.
         * System reconciles: pieces assigned to stitching unit vs pieces returned,
         * broken down by design AND size. Every size must match before a design
         * is considered complete.
         */
        Schema::create('stitching_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->foreignId('design_id')->constrained('designs');
            $table->date('return_date');
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('stitching_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stitching_return_id')
                  ->constrained('stitching_returns')
                  ->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['stitching_return_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stitching_return_items');
        Schema::dropIfExists('stitching_returns');
    }
};
