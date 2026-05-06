<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── production_assignments ──────────────────────────────────────
        Schema::table('production_assignments', function (Blueprint $table) {
            $table->foreignId('stitching_unit_id')->nullable()->after('stitching_unit')
                  ->constrained('stitching_units');
        });

        // Copy existing integer values — they already equal the seeded IDs (1–4)
        DB::statement('UPDATE production_assignments SET stitching_unit_id = stitching_unit WHERE stitching_unit IS NOT NULL');

        Schema::table('production_assignments', function (Blueprint $table) {
            $table->dropColumn('stitching_unit');
        });

        // ── stitching_returns ───────────────────────────────────────────
        Schema::table('stitching_returns', function (Blueprint $table) {
            $table->foreignId('stitching_unit_id')->nullable()->after('stitching_unit')
                  ->constrained('stitching_units');
        });

        DB::statement('UPDATE stitching_returns SET stitching_unit_id = stitching_unit WHERE stitching_unit IS NOT NULL');

        Schema::table('stitching_returns', function (Blueprint $table) {
            $table->dropColumn('stitching_unit');
        });
    }

    public function down(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            $table->dropForeign(['stitching_unit_id']);
            $table->dropColumn('stitching_unit_id');
            $table->unsignedTinyInteger('stitching_unit')->nullable()->after('destination');
        });

        Schema::table('stitching_returns', function (Blueprint $table) {
            $table->dropForeign(['stitching_unit_id']);
            $table->dropColumn('stitching_unit_id');
            $table->unsignedTinyInteger('stitching_unit')->nullable()->after('design_id');
        });
    }
};
