<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('naeem_pakki_return_items', function (Blueprint $table) {
            // Must drop FK before dropping the index it relies on.
            $table->dropForeign('naeem_pakki_return_items_naeem_pakki_return_id_foreign');

            // Old unique was (naeem_pakki_return_id, size) — after size was dropped,
            // MySQL left a unique on naeem_pakki_return_id alone (blocks multi-design batches).
            $table->dropUnique('naeem_pakki_return_items_naeem_pakki_return_id_size_unique');

            // Re-add FK with a plain non-unique index.
            $table->foreign('naeem_pakki_return_id')
                  ->references('id')->on('naeem_pakki_returns')
                  ->cascadeOnDelete();

            // Correct constraint: one row per design per return batch.
            $table->unique(['naeem_pakki_return_id', 'np_design_id'], 'npri_return_design_unique');
        });
    }

    public function down(): void
    {
        Schema::table('naeem_pakki_return_items', function (Blueprint $table) {
            $table->dropForeign(['naeem_pakki_return_id']);
            $table->dropUnique('npri_return_design_unique');
            $table->unique(['naeem_pakki_return_id'], 'naeem_pakki_return_items_naeem_pakki_return_id_size_unique');
            $table->foreign('naeem_pakki_return_id')
                  ->references('id')->on('naeem_pakki_returns')
                  ->cascadeOnDelete();
        });
    }
};
