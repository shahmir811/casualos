<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wages', function (Blueprint $table) {
            // Replace the old (catalogue_id, week_start) unique with
            // (catalogue_id, stitching_unit_id, week_start) so each unit
            // can have its own wage record per catalogue per week.
            $table->unique(['catalogue_id', 'stitching_unit_id', 'week_start'], 'wages_cat_unit_week_unique');
        });

        Schema::table('wages', function (Blueprint $table) {
            $table->dropUnique(['catalogue_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::table('wages', function (Blueprint $table) {
            $table->unique(['catalogue_id', 'week_start']);
        });

        Schema::table('wages', function (Blueprint $table) {
            $table->dropUnique('wages_cat_unit_week_unique');
        });
    }
};
