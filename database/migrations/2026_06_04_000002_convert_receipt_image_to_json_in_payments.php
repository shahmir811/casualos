<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wrap every existing single-path string in a JSON array.
        // e.g. "receipts/abc.jpg"  →  ["receipts/abc.jpg"]
        // NULL rows are left as NULL.
        DB::statement("UPDATE payments SET receipt_image = JSON_ARRAY(receipt_image) WHERE receipt_image IS NOT NULL");

        Schema::table('payments', function (Blueprint $table) {
            $table->text('receipt_image')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Pull the first element back out as a plain string.
        DB::statement("UPDATE payments SET receipt_image = JSON_UNQUOTE(JSON_EXTRACT(receipt_image, '$[0]')) WHERE receipt_image IS NOT NULL");

        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_image')->nullable()->change();
        });
    }
};
