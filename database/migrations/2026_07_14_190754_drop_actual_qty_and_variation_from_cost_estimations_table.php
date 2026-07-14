<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_estimations', function (Blueprint $table) {
            $table->dropColumn(['actual_production_qty', 'production_variation']);
        });
    }

    public function down(): void
    {
        Schema::table('cost_estimations', function (Blueprint $table) {
            $table->unsignedInteger('actual_production_qty')->nullable();
            $table->integer('production_variation')->nullable();
        });
    }
};
