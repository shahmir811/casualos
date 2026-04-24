<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Payments table logs every payment event recorded by the accountant.
         *
         * payment_type:
         *  - advance       : Advance credit deposit (not against a specific order)
         *  - order_payment : Direct payment against an order
         *  - credit_applied: Advance credit applied to an order (no cash, ledger-only)
         */
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('payment_type', ['advance', 'order_payment', 'credit_applied']);
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
