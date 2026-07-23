<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot order_number + catalogue_name onto the refund at creation time.
     * refunds.order_id gets nulled via nullOnDelete once an order created by
     * OrderDeleteController's full flow is hard-deleted (see rule 5.28), which
     * would otherwise leave no way to tell which order/catalogue a refund was
     * ever for. Backfilled here for existing rows whose order still exists.
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('order_number')->nullable()->after('order_reduction_id');
            $table->string('catalogue_name')->nullable()->after('order_number');
        });

        DB::statement('
            UPDATE refunds
            INNER JOIN orders ON orders.id = refunds.order_id
            LEFT JOIN catalogues ON catalogues.id = orders.catalogue_id
            SET refunds.order_number = orders.order_number,
                refunds.catalogue_name = catalogues.name
        ');

        // A handful of refunds created by OrderDeleteController before this snapshot
        // existed have order_id already null (the order was deleted first) — but the
        // notes column still has the order number as plain text ("Refund from deleted
        // Order #1005395"). Recover just the order_number from that pattern; the
        // catalogue name was never captured anywhere else, so it stays unrecoverable.
        DB::statement("
            UPDATE refunds
            SET order_number = SUBSTRING_INDEX(notes, '#', -1)
            WHERE order_id IS NULL
              AND order_number IS NULL
              AND notes LIKE 'Refund from deleted Order #%'
        ");
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn(['order_number', 'catalogue_name']);
        });
    }
};
