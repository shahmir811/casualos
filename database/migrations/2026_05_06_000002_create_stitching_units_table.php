<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stitching_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('name');
            $table->enum('payment_type', ['salary', 'per_piece'])->default('per_piece');
            $table->decimal('salary_amount', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the four existing units with explicit IDs so existing FK values (1–4) in
        // production_assignments and stitching_returns map correctly after migration 003.
        DB::table('stitching_units')->insert([
            ['id' => 1, 'number' => 1, 'name' => 'Unit 1', 'payment_type' => 'per_piece', 'salary_amount' => null, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'number' => 2, 'name' => 'Unit 2', 'payment_type' => 'per_piece', 'salary_amount' => null, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'number' => 3, 'name' => 'Unit 3', 'payment_type' => 'per_piece', 'salary_amount' => null, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'number' => 4, 'name' => 'Unit 4', 'payment_type' => 'salary',    'salary_amount' => null, 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stitching_units');
    }
};
