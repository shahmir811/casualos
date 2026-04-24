<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogues', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // e.g. ISHQIA
            $table->string('cover_photo')->nullable();       // stored path
            $table->unsignedInteger('total_pieces');         // fixed before orders open
            $table->unsignedInteger('number_of_designs');    // set by admin
            // pieces_per_design = total_pieces / number_of_designs (computed)
            $table->decimal('wage_rate', 10, 2);             // Rs per suit stitched (for wages)
            $table->longText('notes')->nullable();           // internal admin notes, unlimited
            $table->enum('status', ['open', 'closed'])->default('open');
            // Unique token for the shareable order link
            $table->string('order_token', 64)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogues');
    }
};
