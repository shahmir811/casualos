<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\ExpoPushToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises POST/DELETE /api/push-tokens. Same hand-built-schema approach as
 * the rest of tests/Feature/Api.
 */
class PushTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_login_token', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('portal_token', 64)->unique();
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

        Schema::create('expo_push_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['expo_push_tokens', 'personal_access_tokens', 'customers', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    protected function makeCustomer(array $attributes = []): Customer
    {
        $admin = User::find(1) ?? User::forceCreate(['id' => 1]);

        return Customer::create(array_merge([
            'name'       => 'Ayesha Khan',
            'email'      => 'ayesha' . uniqid() . '@example.com',
            'created_by' => $admin->id,
        ], $attributes));
    }

    protected function authHeaders(Customer $customer): array
    {
        $token = $customer->createToken('mobile-app')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_registers_a_new_token_for_the_authenticated_customer(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->withHeaders($this->authHeaders($customer))->postJson('/api/push-tokens', [
            'token'    => 'ExponentPushToken[abc123]',
            'platform' => 'ios',
        ]);

        $response->assertOk()->assertJson(['status' => 'registered']);

        $this->assertDatabaseHas('expo_push_tokens', [
            'customer_id' => $customer->id,
            'token'       => 'ExponentPushToken[abc123]',
            'platform'    => 'ios',
        ]);
    }

    public function test_reregistering_the_same_token_reassigns_it_to_the_new_customer(): void
    {
        $firstCustomer  = $this->makeCustomer(['email' => 'first@example.com']);
        $secondCustomer = $this->makeCustomer(['email' => 'second@example.com']);

        ExpoPushToken::create([
            'customer_id' => $firstCustomer->id,
            'token'       => 'ExponentPushToken[shared]',
        ]);

        $response = $this->withHeaders($this->authHeaders($secondCustomer))->postJson('/api/push-tokens', [
            'token' => 'ExponentPushToken[shared]',
        ]);

        $response->assertOk();

        $this->assertSame(1, ExpoPushToken::where('token', 'ExponentPushToken[shared]')->count());
        $this->assertSame($secondCustomer->id, ExpoPushToken::where('token', 'ExponentPushToken[shared]')->first()->customer_id);
    }

    public function test_deletes_only_the_authenticated_customers_own_token(): void
    {
        $owner  = $this->makeCustomer(['email' => 'owner@example.com']);
        $other  = $this->makeCustomer(['email' => 'other@example.com']);

        ExpoPushToken::create(['customer_id' => $owner->id, 'token' => 'mine']);
        ExpoPushToken::create(['customer_id' => $other->id, 'token' => 'theirs']);

        // Attempting to delete someone else's token is a silent no-op, not an error.
        $this->withHeaders($this->authHeaders($owner))->deleteJson('/api/push-tokens', ['token' => 'theirs'])
            ->assertOk();
        $this->assertDatabaseHas('expo_push_tokens', ['token' => 'theirs']);

        $this->withHeaders($this->authHeaders($owner))->deleteJson('/api/push-tokens', ['token' => 'mine'])
            ->assertOk();
        $this->assertDatabaseMissing('expo_push_tokens', ['token' => 'mine']);
    }

    public function test_push_token_endpoints_require_authentication(): void
    {
        $this->postJson('/api/push-tokens', ['token' => 'x'])->assertStatus(401);
        $this->deleteJson('/api/push-tokens', ['token' => 'x'])->assertStatus(401);
    }
}
