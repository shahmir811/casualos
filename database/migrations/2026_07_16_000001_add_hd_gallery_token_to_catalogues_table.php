<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogues', function (Blueprint $table) {
            $table->string('hd_gallery_token', 32)->nullable()->unique()->after('order_token');
        });

        // Backfill existing catalogues so the shareable HD gallery link works retroactively.
        foreach (DB::table('catalogues')->whereNull('hd_gallery_token')->pluck('id') as $id) {
            DB::table('catalogues')->where('id', $id)->update(['hd_gallery_token' => Str::random(32)]);
        }
    }

    public function down(): void
    {
        Schema::table('catalogues', function (Blueprint $table) {
            $table->dropColumn('hd_gallery_token');
        });
    }
};
