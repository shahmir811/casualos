<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Weekly worker wages — calculated every Friday.
         * Formula: total_suits_stitched × catalogue.wage_rate = total_wages
         *
         * The system records that wages were paid.
         * Actual cash payment is made by admin/accountant outside the system.
         */
        Schema::create('wages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->date('week_start');                      // Monday
            $table->date('week_end');                        // Sunday (or Friday)
            $table->unsignedInteger('total_suits_stitched');
            $table->decimal('wage_rate', 10, 2);             // snapshot from catalogue.wage_rate
            $table->decimal('total_wages', 12, 2);           // total_suits_stitched × wage_rate
            $table->boolean('is_confirmed')->default(false); // manager confirms payment
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['catalogue_id', 'week_start']); // one record per catalogue per week
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wages');
    }
};
