<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single-use, short-lived handoff credential that lets a staff member who
 * just proved identity via mobile_login_token + email (Api\AuthController::verify())
 * get a real Laravel web session started inside the mobile app's embedded
 * WebView (MobileLoginController::consume()). Deliberately a separate table
 * from personal_access_tokens — staff never get a Sanctum bearer token at
 * all, since they never call another /api/* endpoint. Only a SHA-256 hash of
 * the raw token is ever stored, same convention as customer_devices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_mobile_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_mobile_login_tokens');
    }
};
