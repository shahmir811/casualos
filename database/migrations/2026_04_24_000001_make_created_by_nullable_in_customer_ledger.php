<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make created_by nullable in customer_ledger so that public/system-generated
     * entries (e.g. orders placed via the public booking form) can be stored
     * without a user reference.
     */
    public function up(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            // Drop the existing non-nullable FK constraint first
            $table->dropForeign(['created_by']);

            // Make the column nullable (must use unsignedBigInteger for ->change())
            $table->unsignedBigInteger('created_by')->nullable()->change();

            // Re-add the FK — nullOnDelete so the ledger row is preserved if a user is deleted
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_ledger', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users');
        });
    }
};
