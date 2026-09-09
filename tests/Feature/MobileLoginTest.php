<?php

namespace Tests\Feature;

use App\Models\StaffMobileLoginToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises MobileLoginController::consume() — the web-side counterpart to
 * Api\AuthController::verify()'s staff branch. A staff member's mobile app
 * opens the returned redirect_url in an embedded WebView; this is what turns
 * that single-use handoff token into a real Laravel session.
 *
 * Hand-builds its own minimal schema for the same reason
 * tests/Feature/Api/AuthTest.php does: several historical migrations use
 * raw MySQL-only ENUM DDL that fails outright against the SQLite in-memory
 * DB this suite runs on.
 */
class MobileLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('mobile_login_token', 64)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_mobile_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('staff_mobile_login_tokens');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    protected function makeStaff(array $attributes = []): User
    {
        return User::create(array_merge([
            'name'  => 'Bilal Accountant',
            'email' => 'bilal@example.com',
        ], $attributes));
    }

    protected function makeHandoffToken(User $user, array $attributes = []): array
    {
        $rawToken = 'raw-test-token-' . uniqid();

        $record = StaffMobileLoginToken::create(array_merge([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addSeconds(90),
        ], $attributes));

        return [$rawToken, $record];
    }

    public function test_valid_unused_token_logs_the_user_in_and_redirects_to_dashboard(): void
    {
        $staff = $this->makeStaff();
        [$rawToken, $record] = $this->makeHandoffToken($staff);

        $response = $this->get("/mobile-login/{$rawToken}");

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(Auth::check());
        $this->assertEquals($staff->id, Auth::id());

        $this->assertNotNull($record->fresh()->used_at);
    }

    public function test_reusing_the_same_token_is_rejected_and_never_logs_in_twice(): void
    {
        $staff = $this->makeStaff();
        [$rawToken] = $this->makeHandoffToken($staff);

        $this->get("/mobile-login/{$rawToken}")->assertRedirect(route('dashboard'));
        Auth::logout();

        $second = $this->get("/mobile-login/{$rawToken}");

        $second->assertRedirect(route('login'));
        $this->assertFalse(Auth::check());
    }

    public function test_expired_token_is_rejected(): void
    {
        $staff = $this->makeStaff();
        [$rawToken] = $this->makeHandoffToken($staff, ['expires_at' => now()->subSecond()]);

        $response = $this->get("/mobile-login/{$rawToken}");

        $response->assertRedirect(route('login'));
        $this->assertFalse(Auth::check());
    }

    public function test_unknown_token_is_rejected(): void
    {
        $response = $this->get('/mobile-login/does-not-exist');

        $response->assertRedirect(route('login'));
        $this->assertFalse(Auth::check());
    }

    public function test_disabled_user_at_consume_time_is_rejected_with_disabled_message(): void
    {
        $staff = $this->makeStaff(['is_active' => false]);
        [$rawToken, $record] = $this->makeHandoffToken($staff);

        $response = $this->get("/mobile-login/{$rawToken}");

        $response->assertRedirect(route('login'));
        $this->assertFalse(Auth::check());

        // Still consumed even though rejected — the token was already spent
        // the moment it was looked up, so it can't be retried once the
        // account is reactivated.
        $this->assertNotNull($record->fresh()->used_at);
    }
}
