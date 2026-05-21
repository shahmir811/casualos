<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('order_reduction_id')->constrained('order_reductions');
            $table->foreignId('customer_id')->constrained('customers');
            $table->decimal('amount', 12, 2);
            $table->enum('refund_method', ['cash', 'bank_transfer']);
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->date('refund_date');
            $table->text('notes')->nullable();
            $table->foreignId('refunded_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
