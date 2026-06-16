<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill original_quantity = quantity for all items where it is still 0
        // but quantity > 0. These are press returns created after the Jun 1 migration
        // that added the column — PressReturnItem.$fillable was missing
        // 'original_quantity', so Laravel silently ignored the value passed in create().
        DB::statement('UPDATE press_return_items SET original_quantity = quantity WHERE original_quantity = 0 AND quantity > 0');

        // Delete duplicate press returns 59 and 60, logged on Jun 16 by the production
        // manager who thought returns 57 and 56 (respectively) had gone missing.
        // They had not — the return history was showing blank quantities because of
        // the original_quantity = 0 bug above.
        //
        // After removal: PS-0015 has 7 pcs still outstanding (L:5, XL:2)
        //                PS-0016 has 7 pcs still outstanding (S:2, M:5)
        DB::table('press_return_items')->where('press_return_id', 59)->delete();
        DB::table('press_return_items')->where('press_return_id', 60)->delete();
        DB::table('press_returns')->whereIn('id', [59, 60])->delete();
    }

    public function down(): void
    {
        // Cannot reliably reverse the original_quantity backfill.
    }
};
