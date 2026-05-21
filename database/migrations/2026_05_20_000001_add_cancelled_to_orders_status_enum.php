<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('received','confirmed','stitching','partially_dispatched','dispatched','cancelled') NOT NULL DEFAULT 'received'");
    }

    public function down(): void
    {
        DB::statement("UPDATE orders SET status = 'received' WHERE status = 'cancelled'");
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('received','confirmed','stitching','partially_dispatched','dispatched') NOT NULL DEFAULT 'received'");
    }
};
