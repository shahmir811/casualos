<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_estimations', function (Blueprint $table) {
            $table->dropColumn('cutting_by');
        });
    }

    public function down(): void
    {
        Schema::table('cost_estimations', function (Blueprint $table) {
            $table->string('cutting_by')->nullable();
        });
    }
};
