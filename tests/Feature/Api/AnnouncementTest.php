<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises GET /api/announcements and POST /api/announcements/{id}/read
 * against real HTTP + real Sanctum tokens, same hand-built-schema approach
 * as AuthTest — several historical migrations use MySQL-only raw ENUM DDL
 * that breaks under the SQLite in-memory DB this suite runs on.
 */
class AnnouncementTest extends TestCase
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

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
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
        foreach (['expo_push_tokens', 'notifications', 'personal_access_tokens', 'customers', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    protected function makeCustomer(array $attributes = []): Customer
    {
        $admin = User::find(1) ?? User::forceCreate(['id' => 1]);

        return Customer::create(array_merge([
            'name'         => 'Ayesha Khan',
            'email'        => 'ayesha' . uniqid() . '@example.com',
            'portal_token' => (string) \Illuminate\Support\Str::uuid(),
            'created_by'   => $admin->id,
        ], $attributes));
    }

    protected function bearerToken(Customer $customer): string
    {
        return $customer->createToken('mobile-app')->plainTextToken;
    }

    public function test_index_requires_a_bearer_token(): void
    {
        $this->getJson('/api/announcements')->assertStatus(401);
    }

    public function test_index_returns_only_the_authenticated_customers_announcements_newest_first(): void
    {
        $customer = $this->makeCustomer();
        $otherCustomer = $this->makeCustomer();

        $customer->notify(new AnnouncementNotification('First Drop', 'Our first announcement.'));
        // Both notifications can otherwise land in the same second under SQLite's
        // timestamp precision, making the newest-first ordering assertion flaky.
        $this->travel(1)->seconds();
        $customer->notify(new AnnouncementNotification('Second Drop', 'Our second announcement.'));
        $otherCustomer->notify(new AnnouncementNotification('Not Yours', 'Should not appear.'));

        $token = $this->bearerToken($customer);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/announcements');

        $response->assertOk();
        $announcements = $response->json('announcements');

        $this->assertCount(2, $announcements);
        $this->assertSame('Second Drop', $announcements[0]['title']);
        $this->assertSame('First Drop', $announcements[1]['title']);
        $this->assertNull($announcements[0]['read_at']);
    }

    public function test_index_includes_image_url_when_an_image_path_was_set(): void
    {
        Storage::fake('s3');

        $customer = $this->makeCustomer();
        $customer->notify(new AnnouncementNotification('With Image', 'Body text', 'announcements/abc.jpg'));

        $token = $this->bearerToken($customer);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/announcements');

        $imageUrl = $response->json('announcements.0.image_url');
        $this->assertNotNull($imageUrl);
        $this->assertStringContainsString('announcements/abc.jpg', $imageUrl);
    }

    public function test_mark_read_sets_read_at_and_is_scoped_to_the_authenticated_customer(): void
    {
        $customer = $this->makeCustomer();
        $otherCustomer = $this->makeCustomer();

        $customer->notify(new AnnouncementNotification('Read Me', 'Body'));
        $notificationId = $customer->notifications()->first()->id;

        $token = $this->bearerToken($customer);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/announcements/{$notificationId}/read")
            ->assertOk();

        $this->assertNotNull($customer->notifications()->first()->fresh()->read_at);

        // The sanctum guard memoizes the resolved user on itself once per
        // request cycle within a single test (same reasoning as AuthTest's
        // logout test) — without this, the previous request's resolved
        // customer would leak into this assertion regardless of the token.
        $this->app['auth']->forgetGuards();

        // A different customer can't mark someone else's announcement read.
        $otherToken = $this->bearerToken($otherCustomer);

        $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->postJson("/api/announcements/{$notificationId}/read")
            ->assertStatus(404);
    }

    public function test_sending_an_announcement_delivers_via_expo_push_and_writes_a_database_row(): void
    {
        Http::fake([
            'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
        ]);

        $customer = $this->makeCustomer();
        \App\Models\ExpoPushToken::create(['customer_id' => $customer->id, 'token' => 'tokenA']);

        $customer->notify(new AnnouncementNotification('Sale', 'Everything 20% off.'));

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $customer->id,
            'notifiable_type' => Customer::class,
        ]);

        $notificationId = $customer->notifications()->first()->id;

        Http::assertSent(function ($request) use ($notificationId) {
            $body = $request->data();

            return $request->url() === 'https://exp.host/--/api/v2/push/send'
                && $body[0]['to'] === 'tokenA'
                && $body[0]['title'] === 'Sale'
                && $body[0]['data']['announcement_id'] === $notificationId;
        });
    }
}
