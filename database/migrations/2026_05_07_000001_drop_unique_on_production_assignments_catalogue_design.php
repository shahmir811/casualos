<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A design can have multiple assignments (e.g. NP then stitching, or split
    // batches). The controller's availability check already prevents over-assignment,
    // so the DB-level unique constraint on (catalogue_id, design_id) is too strict.
    public function up(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            // MySQL refuses to drop the compound unique if it's the only index
            // covering catalogue_id (used by the FK). Add a plain index first.
            $table->index('catalogue_id', 'production_assignments_catalogue_id_index');
            $table->dropUnique(['catalogue_id', 'design_id']);
        });
    }

    public function down(): void
    {
        Schema::table('production_assignments', function (Blueprint $table) {
            $table->unique(['catalogue_id', 'design_id']);
            $table->dropIndex('production_assignments_catalogue_id_index');
        });
    }
};
