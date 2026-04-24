<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues')->cascadeOnDelete();
            $table->string('name');                          // Design name or number
            $table->string('photo')->nullable();             // stored path
            $table->decimal('selling_price', 10, 2);        // price per suit charged to customer
            $table->enum('manufacturing_type', ['in_house', 'outsourced']);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designs');
    }
};
