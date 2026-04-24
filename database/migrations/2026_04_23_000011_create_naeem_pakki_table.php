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
         * Each design has its own per_piece_price at Naeem Pakki.
         */
        Schema::create('naeem_pakki_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_assignment_id')
                  ->constrained('production_assignments')
                  ->cascadeOnDelete();
            $table->date('sent_date');
            $table->decimal('per_piece_price', 10, 2);      // rate charged by Naeem Pakki for this design
            // total_cost = per_piece_price * total pieces sent (computed via items)
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('naeem_pakki_send_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('naeem_pakki_send_id')
                  ->constrained('naeem_pakki_sends')
                  ->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['naeem_pakki_send_id', 'size']);
        });

        /**
         * Naeem Pakki returns — pieces received back after embellishment.
         */
        Schema::create('naeem_pakki_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('naeem_pakki_send_id')
                  ->constrained('naeem_pakki_sends')
                  ->cascadeOnDelete();
            $table->date('return_date');
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('naeem_pakki_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('naeem_pakki_return_id')
                  ->constrained('naeem_pakki_returns')
                  ->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['naeem_pakki_return_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('naeem_pakki_return_items');
        Schema::dropIfExists('naeem_pakki_returns');
        Schema::dropIfExists('naeem_pakki_send_items');
        Schema::dropIfExists('naeem_pakki_sends');
    }
};
