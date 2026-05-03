<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Naeem Pakki assignments don't have a per-size breakdown — pieces are
     * tracked as a single total.  Adding 'np' as a valid size value lets us
     * store one item row (size='np', quantity=total) without changing the
     * available-qty queries that already sum production_assignment_items.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE production_assignment_items
            MODIFY COLUMN size ENUM('xs','s','m','l','xl','np') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE production_assignment_items
            MODIFY COLUMN size ENUM('xs','s','m','l','xl') NOT NULL");
    }
};
