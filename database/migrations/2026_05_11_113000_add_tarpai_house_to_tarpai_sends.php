<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The refactor migration (2026_05_09_000002) assumed a prior partial run had already
    // added tarpai_house and dropped design_id from tarpai_sends. That partial run never
    // executed in this environment, so we finish the job here.
    public function up(): void
    {
        Schema::table('tarpai_sends', function (Blueprint $table) {
            $table->enum('tarpai_house', ['rashid_bhai', 'yousaf_bhai'])
                  ->after('catalogue_id')
                  ->nullable();

            $table->dropForeign(['design_id']);
            $table->dropColumn('design_id');
        });
    }

    public function down(): void
    {
        Schema::table('tarpai_sends', function (Blueprint $table) {
            $table->dropColumn('tarpai_house');
            $table->foreignId('design_id')->after('catalogue_id')->constrained('designs');
        });
    }
};
