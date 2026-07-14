<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Advance payments are money received from a customer that isn't tied to any
     * order — e.g. a customer who has already fully settled their current order
     * pre-pays toward whatever they book next. Distinct from the order-scoped
     * `payments` table (which requires an order and produces a Payment ID in the
     * form {order_number}p{n}) because there is no order to anchor either of
     * those to here. Every row here increments customer.advance_credit_balance
     * and writes an `advance_received` customer_ledger entry.
     */
    public function up(): void
    {
        Schema::create('advance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->enum('payment_type', ['cash', 'bank_transfer']);
            $table->decimal('amount', 12, 2);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->json('receipt_image')->nullable();
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_payments');
    }
};
