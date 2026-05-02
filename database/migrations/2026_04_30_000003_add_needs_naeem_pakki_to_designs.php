<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            // Set at design creation time: does this in-house design need Naeem Pakki embroidery work?
            $table->boolean('needs_naeem_pakki')->default(false)->after('manufacturing_type');
        });
    }

    public function down(): void
    {
        Schema::table('designs', function (Blueprint $table) {
            $table->dropColumn('needs_naeem_pakki');
        });
    }
};
