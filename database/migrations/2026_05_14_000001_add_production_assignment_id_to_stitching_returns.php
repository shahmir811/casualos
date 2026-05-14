<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stitching_returns', function (Blueprint $table) {
            $table->foreignId('production_assignment_id')
                ->nullable()
                ->after('stitching_unit_id')
                ->constrained('production_assignments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stitching_returns', function (Blueprint $table) {
            $table->dropForeign(['production_assignment_id']);
            $table->dropColumn('production_assignment_id');
        });
    }
};
