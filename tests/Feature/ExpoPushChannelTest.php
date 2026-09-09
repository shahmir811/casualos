<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ExpoPushToken;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises ExpoPushChannel directly against a stub notification (rather than
 * OrderStatusChanged) so these tests cover the channel's own mechanics —
 * no-op with no tokens, batching, DeviceNotRegistered pruning — independent
 * of any one notification's content. Same hand-built-schema approach as the
 * rest of tests/Feature/Api.
 */
class ExpoPushChannelTest extends TestCase
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
        foreach (['expo_push_tokens', 'customers', 'users'] as $table) {
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

    protected function stubNotification(): Notification
    {
        return new class extends Notification {
            public function via(mixed $notifiable): array
            {
                return [ExpoPushChannel::class];
            }

            public function toExpoPush(mixed $notifiable): array
            {
                return ['title' => 'Hello', 'body' => 'World', 'sound' => 'default', 'data' => ['x' => 1]];
            }
        };
    }

    public function test_no_request_is_sent_when_the_customer_has_no_registered_tokens(): void
    {
        Http::fake();
        $customer = $this->makeCustomer();

        $customer->notify($this->stubNotification());

        Http::assertNothingSent();
    }

    public function test_sends_one_batched_request_covering_every_registered_token(): void
    {
        Http::fake([
            'exp.host/*' => Http::response(['data' => [['status' => 'ok'], ['status' => 'ok']]], 200),
        ]);

        $customer = $this->makeCustomer();
        ExpoPushToken::create(['customer_id' => $customer->id, 'token' => 'tokenA']);
        ExpoPushToken::create(['customer_id' => $customer->id, 'token' => 'tokenB']);

        $customer->notify($this->stubNotification());

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://exp.host/--/api/v2/push/send'
                && count($body) === 2
                && $body[0]['to'] === 'tokenA'
                && $body[0]['title'] === 'Hello'
                && $body[1]['to'] === 'tokenB';
        });
    }

    public function test_includes_the_configured_access_token_when_present(): void
    {
        config(['services.expo.access_token' => 'expo-secret']);
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200)]);

        $customer = $this->makeCustomer();
        ExpoPushToken::create(['customer_id' => $customer->id, 'token' => 'tokenA']);

        $customer->notify($this->stubNotification());

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer expo-secret'));
    }

    public function test_prunes_a_token_expo_reports_as_device_not_registered(): void
    {
        Http::fake([
            'exp.host/*' => Http::response([
                'data' => [['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]],
            ], 200),
        ]);

        $customer = $this->makeCustomer();
        ExpoPushToken::create(['customer_id' => $customer->id, 'token' => 'stale-token']);

        $customer->notify($this->stubNotification());

        $this->assertDatabaseMissing('expo_push_tokens', ['token' => 'stale-token']);
    }

    public function test_does_not_prune_tokens_on_a_failed_response(): void
    {
        Http::fake(['exp.host/*' => Http::response('Server Error', 500)]);

        $customer = $this->makeCustomer();
        ExpoPushToken::create(['customer_id' => $customer->id, 'token' => 'tokenA']);

        $customer->notify($this->stubNotification());

        $this->assertDatabaseHas('expo_push_tokens', ['token' => 'tokenA']);
    }
}
