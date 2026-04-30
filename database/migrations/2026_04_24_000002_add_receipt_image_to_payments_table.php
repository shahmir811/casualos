<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Column already added in the create_payments_table migration — skip if present
        if (!Schema::hasColumn('payments', 'receipt_image')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('receipt_image')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        // Only drop if the create migration didn't originally include this column
        if (Schema::hasColumn('payments', 'receipt_image')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('receipt_image');
            });
        }
    }
};
