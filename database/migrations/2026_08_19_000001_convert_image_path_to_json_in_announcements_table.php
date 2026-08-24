<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements can now carry multiple images per post. Same convention as
 * 2026_06_04_000002_convert_receipt_image_to_json_in_payments: wrap every
 * existing single-path string in a JSON array, then widen the column.
 * Unlike that migration, this one also renames the column to `image_paths`
 * (plural) since `announcements` has only a handful of historical rows and
 * no other system depends on the old column name.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE announcements SET image_path = JSON_ARRAY(image_path) WHERE image_path IS NOT NULL");

        Schema::table('announcements', function (Blueprint $table) {
            $table->text('image_path')->nullable()->change();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->renameColumn('image_path', 'image_paths');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->renameColumn('image_paths', 'image_path');
        });

        DB::statement("UPDATE announcements SET image_path = JSON_UNQUOTE(JSON_EXTRACT(image_path, '$[0]')) WHERE image_path IS NOT NULL");

        Schema::table('announcements', function (Blueprint $table) {
            $table->string('image_path')->nullable()->change();
        });
    }
};
