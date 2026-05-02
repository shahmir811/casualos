<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Naeem Pakki embroidery sends — pieces sent for embellishment.
         * Piece-based only: no size breakdown. The Naeem Pakki work happens before
         * stitching, so sizes are not yet relevant at this stage.
         */
        Schema::create('naeem_pakki_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs')->cascadeOnDelete();
            $table->date('sent_date');
            $table->unsignedInteger('quantity');                  // total pieces sent, no size breakdown
            $table->decimal('per_piece_price', 10, 2);            // rate charged by Naeem Pakki for this design
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        /**
         * Naeem Pakki returns — pieces received back after embellishment.
         * Piece-based only: no size breakdown.
         */
        Schema::create('naeem_pakki_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('naeem_pakki_send_id')
                  ->constrained('naeem_pakki_sends')
                  ->cascadeOnDelete();
            $table->date('return_date');
            $table->unsignedInteger('quantity');                  // total pieces returned
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('naeem_pakki_returns');
        Schema::dropIfExists('naeem_pakki_sends');
    }
};
