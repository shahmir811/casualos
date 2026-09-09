<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerSignupRequest;
use App\Models\StaffMobileLoginToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises the mobile-app auth flow end to end (real HTTP + real Sanctum
 * tokens), not just the controller in isolation, since a token round-trip
 * bug wouldn't show up any other way.
 *
 * Builds its own minimal schema instead of running RefreshDatabase against
 * the project's full migration history: several historical migrations use
 * raw `ALTER TABLE ... MODIFY COLUMN ... ENUM(...)` (MySQL-only) to widen
 * enum columns, which fails outright against the SQLite in-memory DB this
 * suite runs on — same reason OrderPlacementServiceTest avoids the database
 * entirely. This test only touches users/customers/personal_access_tokens,
 * so it creates just those three tables and tears them down per test.
 */
class AuthTest extends TestCase
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

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->string('email')->unique();
            $table->string('portal_token', 64)->unique();
            $table->decimal('advance_credit_balance', 12, 2)->default(0.00);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_signup_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_number', 30);
            $table->string('city', 100);
            $table->string('country', 50);
            $table->string('address')->nullable();
            $table->string('email')->unique();
            $table->string('status')->default('pending');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('customer_signup_requests');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('staff_mobile_login_tokens');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    protected function makeCustomer(array $attributes = []): Customer
    {
        $admin = User::forceCreate(['id' => 1]);

        return Customer::create(array_merge([
            'name'         => 'Ayesha Khan',
            'city'         => 'Lahore',
            'email'        => 'ayesha@example.com',
            'created_by'   => $admin->id,
        ], $attributes));
    }

    protected function makeStaff(array $attributes = []): User
    {
        return User::create(array_merge([
            'name'  => 'Bilal Accountant',
            'email' => 'bilal@example.com',
        ], $attributes));
    }

    public function test_correct_token_and_email_issues_a_bearer_token(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => $customer->portal_token,
            'email'        => $customer->email,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'customer' => ['id', 'name', 'email', 'city', 'country']])
            ->assertJsonPath('customer.id', $customer->id);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_a_full_pasted_portal_link_resolves_the_same_customer_as_the_bare_token(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => "https://casualiteos.com/portal/{$customer->portal_token}",
            'email'        => $customer->email,
        ]);

        $response->assertOk()->assertJsonPath('customer.id', $customer->id);
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        $customer = $this->makeCustomer(['email' => 'ayesha@example.com']);

        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => $customer->portal_token,
            'email'        => 'AYESHA@EXAMPLE.COM',
        ]);

        $response->assertOk();
    }

    public function test_wrong_email_is_rejected_and_issues_no_token(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => $customer->portal_token,
            'email'        => 'someone-else@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_unknown_portal_token_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => (string) \Illuminate\Support\Str::uuid(),
            'email'        => 'nobody@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_me_requires_a_bearer_token(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_the_authenticated_customer_with_a_valid_token(): void
    {
        $customer = $this->makeCustomer();

        $verify = $this->postJson('/api/auth/verify', [
            'portal_token' => $customer->portal_token,
            'email'        => $customer->email,
        ]);

        $token = $verify->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('customer.id', $customer->id);
    }

    public function test_logout_requires_a_bearer_token(): void
    {
        $this->postJson('/api/auth/logout')->assertStatus(401);
    }

    public function test_logout_revokes_only_the_token_used_for_the_request(): void
    {
        $customer = $this->makeCustomer();

        $tokenA = $this->postJson('/api/auth/verify', [
            'portal_token' => $customer->portal_token,
            'email'        => $customer->email,
        ])->json('token');

        $tokenB = $this->postJson('/api/auth/verify', [
            'portal_token' => $customer->portal_token,
            'email'        => $customer->email,
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);

        // The sanctum guard memoizes the resolved user on itself once per
        // request cycle, and this test's app container isn't rebooted
        // between calls, so without this the previous request's resolved
        // user would leak into the next assertion regardless of the delete.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/me')
            ->assertStatus(401);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->getJson('/api/me')
            ->assertOk();
    }

    public function test_staff_token_and_email_returns_a_redirect_url_and_issues_no_bearer_token(): void
    {
        $staff = $this->makeStaff();

        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => $staff->mobile_login_token,
            'email'        => $staff->email,
        ]);

        $response->assertOk()
            ->assertJsonPath('account_type', 'staff')
            ->assertJsonStructure(['account_type', 'redirect_url']);

        $this->assertStringContainsString(
            rtrim(config('casualite.web_app_url'), '/') . '/mobile-login/',
            $response->json('redirect_url')
        );

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('staff_mobile_login_tokens', 1);
    }

    public function test_staff_handoff_token_is_stored_hashed_not_raw(): void
    {
        $staff = $this->makeStaff();

        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => $staff->mobile_login_token,
            'email'        => $staff->email,
        ]);

        $redirectUrl = $response->json('redirect_url');
        $rawToken = last(explode('/mobile-login/', $redirectUrl));

        $record = StaffMobileLoginToken::first();

        $this->assertNotNull($record);
        $this->assertNotEquals($rawToken, $record->token_hash);
        $this->assertEquals(hash('sha256', $rawToken), $record->token_hash);
        $this->assertTrue($record->expires_at->isFuture());
    }

    public function test_inactive_staff_user_is_rejected_with_the_generic_message(): void
    {
        $staff = $this->makeStaff(['is_active' => false]);

        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => $staff->mobile_login_token,
            'email'        => $staff->email,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('staff_mobile_login_tokens', 0);
    }

    public function test_staff_token_with_mismatched_email_is_rejected(): void
    {
        $staff = $this->makeStaff();

        $response = $this->postJson('/api/auth/verify', [
            'portal_token' => $staff->mobile_login_token,
            'email'        => 'someone-else@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('staff_mobile_login_tokens', 0);
    }

    protected function validSignupPayload(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Sana Malik',
            'contact_number' => '03001234567',
            'city'           => 'Karachi',
            'country'        => 'Pakistan',
            'address'        => '123 Clifton Road',
            'email'          => 'sana@example.com',
        ], $overrides);
    }

    public function test_new_signup_creates_a_pending_request(): void
    {
        $response = $this->postJson('/api/auth/signup', $this->validSignupPayload());

        $response->assertCreated()->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('customer_signup_requests', [
            'email'  => 'sana@example.com',
            'status' => 'pending',
        ]);
    }

    public function test_signup_is_rejected_when_email_already_belongs_to_a_customer(): void
    {
        $this->makeCustomer(['email' => 'sana@example.com']);

        $response = $this->postJson('/api/auth/signup', $this->validSignupPayload());

        $response->assertStatus(422);
        $this->assertDatabaseCount('customer_signup_requests', 0);
    }

    public function test_resubmitting_while_pending_does_not_create_a_duplicate_row(): void
    {
        $this->postJson('/api/auth/signup', $this->validSignupPayload())->assertCreated();

        $response = $this->postJson('/api/auth/signup', $this->validSignupPayload());

        $response->assertOk()->assertJsonPath('status', 'pending');
        $this->assertDatabaseCount('customer_signup_requests', 1);
    }

    public function test_a_rejected_request_can_be_resubmitted_on_the_same_row(): void
    {
        $signup = CustomerSignupRequest::create(array_merge($this->validSignupPayload(), [
            'status'       => 'rejected',
            'reviewed_by'  => null,
            'reviewed_at'  => now(),
        ]));

        $response = $this->postJson('/api/auth/signup', $this->validSignupPayload(['name' => 'Sana Malik Updated']));

        $response->assertCreated()->assertJsonPath('status', 'pending');

        $this->assertDatabaseCount('customer_signup_requests', 1);
        $this->assertDatabaseHas('customer_signup_requests', [
            'id'          => $signup->id,
            'name'        => 'Sana Malik Updated',
            'status'      => 'pending',
            'reviewed_at' => null,
        ]);
    }

    public function test_signup_validates_country_against_the_fixed_list(): void
    {
        $response = $this->postJson('/api/auth/signup', $this->validSignupPayload(['country' => 'Narnia']));

        $response->assertStatus(422)->assertJsonValidationErrors('country');
    }
}
