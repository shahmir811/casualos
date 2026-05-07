<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stitching_return_items', function (Blueprint $table) {
            $table->enum('component', ['kameez', 'shalwar', 'dupatta'])
                  ->default('kameez')
                  ->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('stitching_return_items', function (Blueprint $table) {
            $table->dropColumn('component');
        });
    }
};
