<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('audio_path')->nullable()->after('image_paths');
            $table->string('audio_original_filename')->nullable()->after('audio_path');
            $table->unsignedBigInteger('audio_file_size')->nullable()->after('audio_original_filename');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['audio_path', 'audio_original_filename', 'audio_file_size']);
        });
    }
};
