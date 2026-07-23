<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make refunds.order_id and order_reduction_id nullable so a refund created
     * as part of deleting an order (no OrderReduction involved) can be stored,
     * and survives as a standalone audit record once the order itself is deleted.
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->dropForeign(['order_reduction_id']);
            $table->unsignedBigInteger('order_reduction_id')->nullable()->change();
            $table->foreign('order_reduction_id')->references('id')->on('order_reductions');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders');

            $table->dropForeign(['order_reduction_id']);
            $table->unsignedBigInteger('order_reduction_id')->nullable(false)->change();
            $table->foreign('order_reduction_id')->references('id')->on('order_reductions');
        });
    }
};
