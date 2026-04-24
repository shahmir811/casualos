<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * After fabric arrives, manager assigns every design to either:
         *  - naeem_pakki   : goes for embellishment/embroidery first
         *  - stitching_unit: goes directly to stitching
         *
         * ALL designs must be assigned — not just Naeem Pakki ones.
         * Quantities are broken down by size (XS, S, M, L, XL).
         */
        Schema::create('production_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->foreignId('design_id')->constrained('designs');
            $table->enum('destination', ['naeem_pakki', 'stitching_unit']);
            $table->date('assignment_date');
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();

            $table->unique(['catalogue_id', 'design_id']); // one assignment per design per catalogue
        });

        Schema::create('production_assignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_assignment_id')
                  ->constrained('production_assignments')
                  ->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['production_assignment_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_assignment_items');
        Schema::dropIfExists('production_assignments');
    }
};
