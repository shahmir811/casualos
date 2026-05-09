<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add the new constraint first so MySQL has an index for the FK before we drop the old one.
        Schema::table('stitching_return_items', function (Blueprint $table) {
            $table->unique(['stitching_return_id', 'size', 'component']);
        });

        Schema::table('stitching_return_items', function (Blueprint $table) {
            $table->dropUnique(['stitching_return_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::table('stitching_return_items', function (Blueprint $table) {
            $table->unique(['stitching_return_id', 'size']);
        });

        Schema::table('stitching_return_items', function (Blueprint $table) {
            $table->dropUnique(['stitching_return_id', 'size', 'component']);
        });
    }
};
