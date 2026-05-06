<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stitching_units', function (Blueprint $table) {
            $table->decimal('per_piece_rate', 10, 2)->nullable()->after('salary_amount');
        });
    }

    public function down(): void
    {
        Schema::table('stitching_units', function (Blueprint $table) {
            $table->dropColumn('per_piece_rate');
        });
    }
};
