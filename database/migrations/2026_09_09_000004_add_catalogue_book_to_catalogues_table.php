<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogues', function (Blueprint $table) {
            $table->string('catalogue_book_path')->nullable()->after('hd_gallery_token');
            $table->string('catalogue_book_original_filename')->nullable()->after('catalogue_book_path');
            $table->unsignedBigInteger('catalogue_book_file_size')->nullable()->after('catalogue_book_original_filename');
            $table->foreignId('catalogue_book_uploaded_by')->nullable()->after('catalogue_book_file_size')->constrained('users')->nullOnDelete();
            $table->timestamp('catalogue_book_uploaded_at')->nullable()->after('catalogue_book_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('catalogues', function (Blueprint $table) {
            $table->dropForeign(['catalogue_book_uploaded_by']);
            $table->dropColumn([
                'catalogue_book_path',
                'catalogue_book_original_filename',
                'catalogue_book_file_size',
                'catalogue_book_uploaded_by',
                'catalogue_book_uploaded_at',
            ]);
        });
    }
};
