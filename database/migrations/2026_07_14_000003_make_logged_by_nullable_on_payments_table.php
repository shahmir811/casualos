<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make logged_by nullable in payments so that system-generated payments
     * (e.g. advance credit auto-applied to a new order on submission) can be
     * stored without a staff user reference.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop the existing non-nullable FK constraint first
            $table->dropForeign(['logged_by']);

            // Make the column nullable (must use unsignedBigInteger for ->change())
            $table->unsignedBigInteger('logged_by')->nullable()->change();

            // Re-add the FK — nullOnDelete so the payment row is preserved if a user is deleted
            $table->foreign('logged_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['logged_by']);
            $table->unsignedBigInteger('logged_by')->nullable(false)->change();
            $table->foreign('logged_by')->references('id')->on('users');
        });
    }
};
