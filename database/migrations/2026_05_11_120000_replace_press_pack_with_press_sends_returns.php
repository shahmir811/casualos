<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('press_pack_record_items');
        Schema::dropIfExists('press_pack_records');

        Schema::create('press_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->date('sent_date');
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('press_send_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_send_id')->constrained('press_sends')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });

        Schema::create('press_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_send_id')->constrained('press_sends')->cascadeOnDelete();
            $table->date('return_date');
            $table->foreignId('logged_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('press_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('press_return_id')->constrained('press_returns')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_return_items');
        Schema::dropIfExists('press_returns');
        Schema::dropIfExists('press_send_items');
        Schema::dropIfExists('press_sends');

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
            $table->foreignId('press_pack_record_id')->constrained('press_pack_records')->cascadeOnDelete();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
            $table->unique(['press_pack_record_id', 'size']);
        });
    }
};
