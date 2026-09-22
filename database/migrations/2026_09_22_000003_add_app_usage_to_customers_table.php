<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks general mobile-app usage per customer, independent of push
 * notification opt-in (expo_push_tokens only exists for customers who
 * granted push permission, which undercounts real app usage). Updated at
 * every login (Api\AuthController::verify()) and on every subsequent
 * authenticated app request via the TrackCustomerAppUsage middleware, so
 * app_last_seen_at stays current for the life of a customer's session
 * rather than freezing at first install.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('app_platform', ['ios', 'android'])->nullable()->after('portal_token');
            $table->timestamp('app_last_seen_at')->nullable()->after('app_platform');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['app_platform', 'app_last_seen_at']);
        });
    }
};
