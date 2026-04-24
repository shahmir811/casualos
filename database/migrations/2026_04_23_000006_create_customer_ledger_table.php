<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * The ledger is the single source of truth for all customer financial activity.
         * Every entry is immutable — records are NEVER updated or deleted.
         *
         * Transaction types:
         *  - advance_received    : Customer paid advance (credit)
         *  - order_charged       : Order total debited to customer
         *  - payment_received    : Payment logged against an order (credit)
         *  - credit_applied      : Advance credit applied to an order (credit reduces advance, debit order)
         *  - order_reduced       : Order total reduced (damaged pieces) — may create surplus credit
         *  - surplus_to_advance  : Surplus from overpayment/reduction added to advance credit
         */
        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->enum('transaction_type', [
                'advance_received',
                'order_charged',
                'payment_received',
                'credit_applied',
                'order_reduced',
                'surplus_to_advance',
            ]);
            // Positive = credit (money in favour of customer), Negative = debit (customer owes)
            $table->decimal('amount', 12, 2);
            // Running balance of advance credit after this entry
            $table->decimal('running_advance_balance', 12, 2)->default(0.00);
            // Polymorphic reference — link to order, payment, or order_reduction
            $table->string('reference_type')->nullable();    // 'order', 'payment', 'order_reduction'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger');
    }
};
