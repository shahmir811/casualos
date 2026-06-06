<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('assigned_bank_account_id')
                  ->nullable()
                  ->after('catalogue_id')
                  ->constrained('bank_accounts')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_bank_account_id']);
            $table->dropColumn('assigned_bank_account_id');
        });
    }
};
