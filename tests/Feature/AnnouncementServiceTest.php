<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use App\Services\AnnouncementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises AnnouncementService::send() — the shared logic behind both the
 * announcements:send artisan command and the CasualOS admin compose screen
 * (AnnouncementController). Same hand-built-schema approach as
 * tests/Feature/Api/AnnouncementTest.php: several historical migrations use
 * MySQL-only raw ENUM DDL that breaks under the SQLite in-memory DB this
 * suite runs on.
 */
class AnnouncementServiceTest extends TestCase
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

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->text('image_paths')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['announcements', 'expo_push_tokens', 'notifications', 'customers', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    protected function makeCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name'         => 'Ayesha Khan',
            'email'        => 'ayesha' . uniqid() . '@example.com',
            'portal_token' => (string) \Illuminate\Support\Str::uuid(),
        ], $attributes));
    }

    public function test_send_creates_one_announcement_row_with_the_correct_recipient_count(): void
    {
        $this->makeCustomer();
        $this->makeCustomer();
        $this->makeCustomer();

        $admin = User::forceCreate(['id' => 1]);

        $announcement = app(AnnouncementService::class)->send('Sale', 'Everything 20% off.', [], $admin);

        $this->assertInstanceOf(Announcement::class, $announcement);
        $this->assertSame(3, $announcement->recipient_count);
        $this->assertSame($admin->id, $announcement->sent_by);
        $this->assertDatabaseCount('announcements', 1);
    }

    public function test_send_notifies_every_customer(): void
    {
        $customerA = $this->makeCustomer();
        $customerB = $this->makeCustomer();

        app(AnnouncementService::class)->send('New Catalogue', 'Check it out.', [], null);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $customerA->id,
            'notifiable_type' => Customer::class,
            'type'            => AnnouncementNotification::class,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $customerB->id,
            'notifiable_type' => Customer::class,
            'type'            => AnnouncementNotification::class,
        ]);
    }

    public function test_send_with_no_customers_still_creates_a_record_with_zero_recipients(): void
    {
        $announcement = app(AnnouncementService::class)->send('Nobody Yet', 'Body', [], null);

        $this->assertSame(0, $announcement->recipient_count);
        $this->assertDatabaseCount('notifications', 0);
    }
}
