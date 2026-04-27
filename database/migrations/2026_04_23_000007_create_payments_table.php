<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('payment_type', ['cash', 'bank_transfer', 'easypaisa', 'jazzcash', 'advance']);
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->string('receipt_image')->nullable();
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
