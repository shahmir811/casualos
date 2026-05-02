<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            // 1–4 representing the four stitching units in the factory.
            // Only populated when destination = 'stitching_unit'.
            $table->unsignedTinyInteger('stitching_unit')->nullable()->after('destination');
        });
    }

    public function down(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            $table->dropColumn('stitching_unit');
        });
    }
};
