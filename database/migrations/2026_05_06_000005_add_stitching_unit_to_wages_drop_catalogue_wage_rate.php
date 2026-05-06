<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wages', function (Blueprint $table) {
            $table->foreignId('stitching_unit_id')
                ->nullable()
                ->after('catalogue_id')
                ->constrained('stitching_units')
                ->nullOnDelete();
        });

        Schema::table('catalogues', function (Blueprint $table) {
            $table->dropColumn('wage_rate');
        });
    }

    public function down(): void
    {
        Schema::table('wages', function (Blueprint $table) {
            $table->dropForeign(['stitching_unit_id']);
            $table->dropColumn('stitching_unit_id');
        });

        Schema::table('catalogues', function (Blueprint $table) {
            $table->decimal('wage_rate', 10, 2)->nullable()->after('number_of_designs');
        });
    }
};
