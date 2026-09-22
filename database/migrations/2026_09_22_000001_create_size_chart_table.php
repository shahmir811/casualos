<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton table — one global size-chart image for the whole system (not
 * per-catalogue). Mirrors the single-row pattern already used by
 * order_number_sequence rather than bolting nullable columns onto an
 * unrelated table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('size_chart', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('size_chart');
    }
};
