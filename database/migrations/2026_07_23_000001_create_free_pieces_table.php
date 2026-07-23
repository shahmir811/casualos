<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('design_id')->constrained()->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['catalogue_id', 'design_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_pieces');
    }
};
