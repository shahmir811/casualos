<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (announcement, customer), created for every customer at send
 * time (read_at null) so read stats can be computed as read-count vs
 * sent-count without joining against the framework `notifications` table.
 * Announcements sent before this migration have no rows here — their stats
 * page shows "not tracked" rather than a fabricated 0/recipient_count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
