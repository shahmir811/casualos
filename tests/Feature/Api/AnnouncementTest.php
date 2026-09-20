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

    public function test_index_includes_image_urls_when_image_paths_were_set(): void
    {
        Storage::fake('s3');

        $customer = $this->makeCustomer();
        $customer->notify(new AnnouncementNotification('With Image', 'Body text', ['announcements/abc.jpg', 'announcements/def.jpg']));

        $token = $this->bearerToken($customer);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/announcements');

        // 'image_url' stays the first image, for older app builds that only read one.
        $imageUrl = $response->json('announcements.0.image_url');
        $this->assertNotNull($imageUrl);
        $this->assertStringContainsString('announcements/abc.jpg', $imageUrl);

        $imageUrls = $response->json('announcements.0.image_urls');
        $this->assertCount(2, $imageUrls);
        $this->assertStringContainsString('announcements/abc.jpg', $imageUrls[0]);
        $this->assertStringContainsString('announcements/def.jpg', $imageUrls[1]);
    }

    public function test_index_includes_audio_url_and_has_audio_flag_when_a_voice_note_was_attached(): void
    {
        Storage::fake('s3');

        $customer = $this->makeCustomer();
        $customer->notify(new AnnouncementNotification('With Audio', 'Listen up', [], 'announcements/voice.m4a'));

        $token = $this->bearerToken($customer);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/announcements');

        $this->assertTrue($response->json('announcements.0.has_audio'));
        $this->assertStringContainsString('announcements/voice.m4a', $response->json('announcements.0.audio_url'));
    }

    public function test_index_reports_has_audio_false_when_no_voice_note_was_attached(): void
    {
        $customer = $this->makeCustomer();
        $customer->notify(new AnnouncementNotification('No Audio', 'Just text'));

        $token = $this->bearerToken($customer);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/announcements');

        $this->assertFalse($response->json('announcements.0.has_audio'));
        $this->assertNull($response->json('announcements.0.audio_url'));
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
                && $body[0]['sound'] === 'casualite_notification_01.wav'
                && $body[0]['badge'] === 1
                && $body[0]['data']['announcement_id'] === $notificationId
                && $body[0]['data']['has_audio'] === false;
        });
    }

    public function test_expo_push_body_is_prefixed_and_flagged_when_a_voice_note_is_attached(): void
    {
        Http::fake([
            'exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
        ]);

        $customer = $this->makeCustomer();
        \App\Models\ExpoPushToken::create(['customer_id' => $customer->id, 'token' => 'tokenA']);

        $customer->notify(new AnnouncementNotification('Sale', 'Everything 20% off.', [], 'announcements/voice.m4a'));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body[0]['body'] === '🎤 Voice message · Everything 20% off.'
                && $body[0]['data']['has_audio'] === true;
        });
    }

    public function test_badge_counts_current_announcement_once_in_either_channel_order(): void
    {
        $customer = $this->makeCustomer();
        for ($i = 0; $i < 5; $i++) {
            $customer->notify(new AnnouncementNotification('Earlier', 'Body'));
        }
        $other = $this->makeCustomer();
        $other->notify(new AnnouncementNotification('Other customer', 'Body'));

        $notification = new AnnouncementNotification('New', 'Body');
        $notification->id = (string) \Illuminate\Support\Str::uuid();
        $this->assertSame(6, $notification->toExpoPush($customer)['badge']);

        $customer->notifications()->create([
            'id' => $notification->id,
            'type' => AnnouncementNotification::class,
            'data' => $notification->toDatabase($customer),
            'read_at' => null,
        ]);
        $this->assertSame(6, $notification->toExpoPush($customer)['badge']);

        $customer->notifications()->findOrFail($notification->id)->markAsRead();
        $this->assertSame(5, $notification->toExpoPush($customer)['badge']);

        $customer->notifications()->whereNull('read_at')->update(['read_at' => now()]);
        $this->assertSame(0, $notification->toExpoPush($customer)['badge']);
    }

}
