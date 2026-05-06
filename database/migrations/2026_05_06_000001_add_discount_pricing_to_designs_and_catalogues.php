<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: selling_price was already renamed to normal_price in a partial run.
        // This migration only adds the two new columns.
        Schema::table('designs', function (Blueprint $table) {
            $table->decimal('discount_price', 10, 2)->nullable()->after('normal_price');
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
