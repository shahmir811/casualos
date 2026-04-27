<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            // Rate agreed with Naeem Pakki for this design's embroidery — only relevant
            // when destination = 'naeem_pakki'. Null for stitching_unit assignments.
            $table->decimal('naeem_pakki_rate', 10, 2)->nullable()->after('destination');
        });
    }

    public function down(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            $table->dropColumn('naeem_pakki_rate');
        });
    }
};
