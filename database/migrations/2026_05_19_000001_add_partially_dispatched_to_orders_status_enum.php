<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('received','confirmed','stitching','dispatched','partially_dispatched') NOT NULL DEFAULT 'received'");
    }

    public function down(): void
    {
        // Update any partially_dispatched rows back to stitching before reverting enum
        DB::statement("UPDATE orders SET status = 'stitching' WHERE status = 'partially_dispatched'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('received','confirmed','stitching','dispatched') NOT NULL DEFAULT 'received'");
    }
};
