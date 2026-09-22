<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\SizeChart;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises GET /api/size-chart. Same hand-built-schema approach as the
 * rest of tests/Feature/Api — several historical migrations use MySQL-only
 * raw ENUM DDL that breaks under the SQLite in-memory DB this suite runs on.
 */
class SizeChartTest extends TestCase
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
            $table->string('app_platform')->nullable();
            $table->timestamp('app_last_seen_at')->nullable();
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

        Schema::create('size_chart', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['size_chart', 'personal_access_tokens', 'customers', 'users'] as $table) {
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

    protected function authHeaders(Customer $customer): array
    {
        $token = $customer->createToken('mobile-app')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_requires_a_bearer_token(): void
    {
        $this->getJson('/api/size-chart')->assertStatus(401);
    }

    public function test_returns_not_uploaded_reason_when_no_image_has_ever_been_uploaded(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/size-chart');

        $response->assertStatus(404)->assertJson(['reason' => 'not_uploaded']);
    }

    public function test_returns_a_presigned_url_when_an_image_has_been_uploaded(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('size-charts/chart.jpg', 'fake-image-bytes');

        SizeChart::current()->update(['image_path' => 'size-charts/chart.jpg']);

        $customer = $this->makeCustomer();

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/size-chart');

        $response->assertOk();
        $this->assertNotNull($response->json('url'));
        $this->assertStringContainsString('size-charts/chart.jpg', $response->json('url'));
    }

    public function test_never_creates_a_second_size_chart_row(): void
    {
        $customer = $this->makeCustomer();

        // Hitting the endpoint twice must resolve the same singleton row both
        // times (SizeChart::current()'s firstOrCreate([]) contract), not
        // silently accumulate rows.
        $this->withHeaders($this->authHeaders($customer))->getJson('/api/size-chart');
        $this->withHeaders($this->authHeaders($customer))->getJson('/api/size-chart');

        $this->assertDatabaseCount('size_chart', 1);
    }
}
