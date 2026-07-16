<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogue_hd_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained()->cascadeOnDelete();
            $table->string('s3_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogue_hd_images');
    }
};
