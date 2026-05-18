<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Expand the enum to include both old and new values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','accountant','manager','designer','production_manager','creative_head') NOT NULL DEFAULT 'production_manager'");

        // Step 2: Migrate existing data
        DB::table('users')->where('role', 'manager')->update(['role' => 'production_manager']);
        DB::table('users')->where('role', 'designer')->update(['role' => 'creative_head']);

        // Step 3: Lock the enum to only the new values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','accountant','production_manager','creative_head') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','accountant','manager','designer','production_manager','creative_head') NOT NULL");

        DB::table('users')->where('role', 'production_manager')->update(['role' => 'manager']);
        DB::table('users')->where('role', 'creative_head')->update(['role' => 'designer']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','accountant','manager','designer') NOT NULL");
    }
};
