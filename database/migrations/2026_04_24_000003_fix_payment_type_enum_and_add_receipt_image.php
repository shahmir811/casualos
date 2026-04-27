<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix the payment_type enum to match the form values
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM('cash','bank_transfer','easypaisa','jazzcash','advance') NOT NULL");

        // Add receipt_image column if it doesn't already exist
        if (! Schema::hasColumn('payments', 'receipt_image')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('receipt_image')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM('advance','order_payment','credit_applied') NOT NULL");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('receipt_image');
        });
    }
};
