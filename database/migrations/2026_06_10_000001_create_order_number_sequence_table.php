<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_number_sequence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('last_number');
        });

        // Seed with 1005334 so the first order issued gets 1005335.
        // Existing orders retain their original random numbers (100000–999999).
        // Sequential numbers (1005335+) can never collide with old random numbers.
        DB::table('order_number_sequence')->insert(['last_number' => 1005334]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequence');
    }
};
