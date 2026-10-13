<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_signup_requests', function (Blueprint $table) {
            $table->string('contact_number', 30)->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->string('country', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_signup_requests', function (Blueprint $table) {
            $table->string('contact_number', 30)->nullable(false)->change();
            $table->string('city', 100)->nullable(false)->change();
            $table->string('country', 50)->nullable(false)->change();
        });
    }
};
