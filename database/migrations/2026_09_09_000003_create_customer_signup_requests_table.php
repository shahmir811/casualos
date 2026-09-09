<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_signup_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_number', 30);
            $table->string('city', 100);
            $table->string('country', 50);
            $table->string('address')->nullable();
            $table->string('email')->unique();
            $table->string('status')->default('pending'); // pending|approved|rejected
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_signup_requests');
    }
};
