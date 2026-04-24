<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->enum('status', ['received', 'confirmed', 'stitching', 'dispatched'])->default('received');

            // Fields as submitted by customer on the public order form
            $table->string('submitted_name');
            $table->string('submitted_city');
            $table->string('submitted_email');

            // Financial totals (updated if order is reduced due to damage)
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('total_paid', 12, 2)->default(0.00);
            $table->decimal('outstanding_balance', 12, 2)->default(0.00);

            // Flagged = submitted email doesn't match any customer record
            $table->boolean('is_flagged')->default(false);

            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
