<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The restructure migration (2026_05_05_000001) added production_assignment_id
        // and created naeem_pakki_return_items for per-design quantities, but forgot
        // to drop the old single `quantity` column from naeem_pakki_returns.
        Schema::table('naeem_pakki_returns', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('naeem_pakki_returns', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->after('return_date')->default(0);
        });
    }
};
