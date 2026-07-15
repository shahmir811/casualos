<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs persistent portal login for the customer PWA. A customer may verify
     * from several devices (phone, laptop, a fresh home-screen install), so this
     * is a one-to-many table keyed on customer_id rather than a single
     * remember-token column on customers. Only the sha256 hash of the cookie
     * value is stored — the raw token lives only in the customer's browser
     * cookie and is never persisted server-side, mirroring how password-reset
     * tokens are handled elsewhere in Laravel.
     */
    public function up(): void
    {
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('token_hash', 64)->unique();
            $table->string('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_devices');
    }
};
