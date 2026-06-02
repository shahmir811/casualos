<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('press_return_items', function (Blueprint $table) {
            $table->unsignedInteger('original_quantity')->default(0)->after('quantity');
        });

        DB::statement('UPDATE press_return_items SET original_quantity = quantity');
    }

    public function down(): void
    {
        Schema::table('press_return_items', function (Blueprint $table) {
            $table->dropColumn('original_quantity');
        });
    }
};
