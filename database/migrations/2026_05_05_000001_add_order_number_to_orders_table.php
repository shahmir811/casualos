<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 20)->nullable()->unique()->after('id');
        });

        // Backfill existing orders with random 6-digit numbers
        DB::table('orders')->orderBy('id')->each(function ($order) {
            do {
                $number = (string) random_int(100000, 999999);
            } while (DB::table('orders')->where('order_number', $number)->exists());

            DB::table('orders')->where('id', $order->id)->update(['order_number' => $number]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 20)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });
    }
};
