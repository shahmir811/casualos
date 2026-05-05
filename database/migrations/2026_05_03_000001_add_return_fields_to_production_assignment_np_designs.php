<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_assignment_np_designs', function (Blueprint $table) {
            $table->unsignedSmallInteger('returned_quantity')->nullable()->after('quantity');
            $table->date('return_date')->nullable()->after('returned_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('production_assignment_np_designs', function (Blueprint $table) {
            $table->dropColumn(['returned_quantity', 'return_date']);
        });
    }
};
