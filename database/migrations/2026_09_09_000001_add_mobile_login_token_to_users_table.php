<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_login_token', 64)->nullable()->unique()->after('email');
        });

        // Backfill existing staff so the mobile app's staff-login lookup works retroactively.
        foreach (DB::table('users')->whereNull('mobile_login_token')->pluck('id') as $id) {
            DB::table('users')->where('id', $id)->update(['mobile_login_token' => Str::uuid()->toString()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mobile_login_token');
        });
    }
};
