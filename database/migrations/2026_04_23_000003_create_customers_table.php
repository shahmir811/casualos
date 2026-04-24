<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city');
            $table->string('contact_number', 30);
            $table->string('email')->unique();               // primary identifier — must be unique
            // Auto-generated UUID portal link token (never changes after creation)
            $table->string('portal_token', 64)->unique();
            // Running advance credit balance (denormalized for quick access)
            $table->decimal('advance_credit_balance', 12, 2)->default(0.00);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            // Note: customers are NEVER deleted — per proposal
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
