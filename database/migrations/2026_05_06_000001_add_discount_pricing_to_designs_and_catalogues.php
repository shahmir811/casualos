<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->decimal('discount_price', 10, 2)->nullable()->after('selling_price');
        });

        Schema::table('catalogues', function (Blueprint $table) {
            $table->unsignedInteger('quantity_benchmark')->nullable()->after('wage_rate');
        });
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropColumn('discount_price');
        });

        Schema::table('catalogues', function (Blueprint $table) {
            $table->dropColumn('quantity_benchmark');
        });
    }
};
