<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Press & Pack records — manager records when in-house suits are
         * pressed, packed, and ready for dispatch. These feed into packed_inventory.
         */
        Schema::create('press_pack_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->foreignId('design_id')->constrained('designs');
            $table->date('packed_date');
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('press_pack_record_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_pack_record_id')
                  ->constrained('press_pack_records')
                  ->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();

            $table->unique(['press_pack_record_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_pack_record_items');
        Schema::dropIfExists('press_pack_records');
    }
};
