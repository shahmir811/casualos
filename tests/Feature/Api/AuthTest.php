<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('personal_access_tokens');
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
}
