<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stitching_returns', function (Blueprint $table) {
            // 1–4 representing the four stitching units in the factory.
            // Required for wages calculation: pieces returned per unit per week.
            $table->unsignedTinyInteger('stitching_unit')->nullable()->after('design_id');
        });
    }

    public function down(): void
    {
        Schema::table('stitching_returns', function (Blueprint $table) {
            $table->dropColumn('stitching_unit');
        });
    }
};
