<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Tarpai finishing — shirts only.
         * Manager sends completed shirts to Tarpai for finishing.
         * Each design has its own per_piece_price at Tarpai.
         * System flags shirts still outstanding (sent but not returned).
         */
        Schema::create('tarpai_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->foreignId('design_id')->constrained('designs');
            $table->date('sent_date');
            $table->decimal('per_piece_price', 10, 2);      // rate charged by Tarpai for this design
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('tarpai_send_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarpai_send_id')->constrained('tarpai_sends')->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['tarpai_send_id', 'size']);
        });

        Schema::create('tarpai_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarpai_send_id')->constrained('tarpai_sends')->cascadeOnDelete();
            $table->date('return_date');
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('tarpai_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarpai_return_id')->constrained('tarpai_returns')->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['tarpai_return_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarpai_return_items');
        Schema::dropIfExists('tarpai_returns');
        Schema::dropIfExists('tarpai_send_items');
        Schema::dropIfExists('tarpai_sends');
    }
};
