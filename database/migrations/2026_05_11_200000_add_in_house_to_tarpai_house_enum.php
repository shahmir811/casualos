<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tarpai_sends MODIFY tarpai_house ENUM('rashid_bhai','yousaf_bhai','in_house') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tarpai_sends MODIFY tarpai_house ENUM('rashid_bhai','yousaf_bhai') NULL");
    }
};
