<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tarpai_sends already has tarpai_house and no design_id (applied in a prior partial run).
        // Only the items tables still need updating.

        // Add design_id to tarpai_send_items; add new composite unique key first, then drop old one.
        // (MySQL requires an index covering the FK column to exist before dropping the old index.)
        Schema::table('tarpai_send_items', function (Blueprint $table) {
            $table->foreignId('design_id')->nullable()->after('tarpai_send_id')->constrained('designs');
            $table->unique(['tarpai_send_id', 'design_id', 'size']);
            $table->dropUnique(['tarpai_send_id', 'size']);
        });

        // Add design_id to tarpai_return_items; same ordering trick.
        Schema::table('tarpai_return_items', function (Blueprint $table) {
            $table->foreignId('design_id')->nullable()->after('tarpai_return_id')->constrained('designs');
            $table->unique(['tarpai_return_id', 'design_id', 'size']);
            $table->dropUnique(['tarpai_return_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::table('tarpai_return_items', function (Blueprint $table) {
            $table->unique(['tarpai_return_id', 'size']);
            $table->dropUnique(['tarpai_return_id', 'design_id', 'size']);
            $table->dropForeign(['design_id']);
            $table->dropColumn('design_id');
        });

        Schema::table('tarpai_send_items', function (Blueprint $table) {
            $table->unique(['tarpai_send_id', 'size']);
            $table->dropUnique(['tarpai_send_id', 'design_id', 'size']);
            $table->dropForeign(['design_id']);
            $table->dropColumn('design_id');
        });

        Schema::table('tarpai_sends', function (Blueprint $table) {
            $table->dropColumn('tarpai_house');
            $table->foreignId('design_id')->after('catalogue_id')->constrained('designs');
        });
    }
};
