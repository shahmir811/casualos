<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarpai_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained()->cascadeOnDelete();
            $table->enum('tarpai_house', ['rashid_bhai', 'yousaf_bhai']);
            $table->date('week_start');
            $table->date('week_end');
            $table->unsignedInteger('total_pieces_sent')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->boolean('is_confirmed')->default(false);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['catalogue_id', 'tarpai_house', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarpai_payments');
    }
};
