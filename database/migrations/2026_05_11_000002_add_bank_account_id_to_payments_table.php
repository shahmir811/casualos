<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete()->after('payment_type');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\BankAccount::class);
            $table->dropColumn('bank_account_id');
        });
    }
};
