<?php

namespace Tests\Feature\Api;

use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\Design;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises POST /api/orders end to end — the mobile app's entry point into
 * OrderPlacementService::place(), the same service PublicOrderController::submit()
 * calls, so a passing test here is also a guarantee the two callers still
 * agree. Same hand-built-schema approach as the rest of tests/Feature/Api,
 * widened to include order_number_sequence (Order::boot() auto-generates
 * order_number from it) and customer_ledger/payments (place() writes both).
 */
class OrderPlacementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['activitylog.enabled' => false]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_login_token', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
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

        Schema::create('catalogues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cover_photo')->nullable();
            $table->unsignedInteger('qty_per_design')->default(0);
            $table->unsignedInteger('number_of_designs')->default(0);
            $table->unsignedInteger('quantity_benchmark')->nullable();
            $table->string('order_token', 64)->unique();
            $table->string('hd_gallery_token', 64)->unique();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('designs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->string('name');
            $table->string('photo')->nullable();
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('manufacturing_type')->default('in_house');
            $table->boolean('needs_naeem_pakki')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('order_number_sequence', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('last_number');
        });
        DB::table('order_number_sequence')->insert(['last_number' => 1005334]);

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->string('status')->default('received');
            $table->string('order_number')->unique();
            $table->string('submitted_name');
            $table->string('submitted_city');
            $table->string('submitted_email');
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('total_paid', 12, 2)->default(0.00);
            $table->decimal('outstanding_balance', 12, 2)->default(0.00);
            $table->boolean('is_flagged')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->unsignedSmallInteger('qty_xs')->default(0);
            $table->unsignedSmallInteger('qty_s')->default(0);
            $table->unsignedSmallInteger('qty_m')->default(0);
            $table->unsignedSmallInteger('qty_l')->default(0);
            $table->unsignedSmallInteger('qty_xl')->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('total_qty');
            $table->decimal('total_amount', 12, 2);
            $table->timestamps();
        });

        Schema::create('customer_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('transaction_type');
            $table->decimal('amount', 12, 2);
            $table->decimal('running_advance_balance', 12, 2)->default(0.00);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->string('payment_type');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('title_given')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->json('receipt_image')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->unique(['order_id', 'sequence_number']);
        });

        Schema::create('dispatch_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->unsignedSmallInteger('batch_number');
            $table->date('dispatch_date');
            $table->text('shipping_address');
            $table->string('cargo_document')->nullable();
            $table->foreignId('logged_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('dispatch_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_batch_id')->constrained('dispatch_batches')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->string('size');
            $table->unsignedSmallInteger('quantity');
            $table->timestamps();
        });

        Schema::create('order_reductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->timestamps();
        });

        Schema::create('order_reduction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_reduction_id')->constrained('order_reductions')->cascadeOnDelete();
            $table->foreignId('design_id')->constrained('designs');
            $table->string('size');
            $table->unsignedSmallInteger('qty_reduced');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ([
            'order_reduction_items', 'order_reductions',
            'dispatch_batch_items', 'dispatch_batches',
            'payments', 'customer_ledger',
            'order_items', 'orders', 'order_number_sequence',
            'designs', 'catalogues',
            'personal_access_tokens', 'customers', 'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    protected function makeCustomer(array $attributes = []): Customer
    {
        $admin = User::find(1) ?? User::forceCreate(['id' => 1]);

        return Customer::create(array_merge([
            'name'       => 'Ayesha Khan',
            'city'       => 'Lahore',
            'email'      => 'ayesha' . uniqid() . '@example.com',
            'created_by' => $admin->id,
        ], $attributes));
    }

    protected function authHeaders(Customer $customer): array
    {
        $token = $customer->createToken('mobile-app')->plainTextToken;

        return ['Authorization' => "Bearer {$token}"];
    }

    protected function makeCatalogue(array $attributes = []): Catalogue
    {
        $catalogue = Catalogue::create(array_merge([
            'name'               => 'SAKOON',
            'qty_per_design'     => 70,
            'number_of_designs'  => 1,
            'quantity_benchmark' => 10,
            'order_token'        => \Illuminate\Support\Str::random(32),
            'hd_gallery_token'   => \Illuminate\Support\Str::random(32),
            'status'             => 'open',
        ], $attributes));

        Design::create([
            'catalogue_id'       => $catalogue->id,
            'name'               => 'ISHQIA-1',
            'selling_price'      => 20000,
            'manufacturing_type' => 'in_house',
        ]);

        return $catalogue->fresh('designs');
    }

    public function test_places_an_order_for_the_authenticated_customer(): void
    {
        $customer  = $this->makeCustomer();
        $catalogue = $this->makeCatalogue();

        $response = $this->withHeaders($this->authHeaders($customer))->postJson('/api/orders', [
            'catalogue_id' => $catalogue->id,
            'qty_xs'       => 1,
            'qty_s'        => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('order.status', 'received')
            ->assertJsonPath('order.total_pieces', 3)
            ->assertJsonPath('order.total_amount', '60000.00');

        $order = Order::first();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertNotEmpty($order->order_number);
    }

    public function test_rejects_an_order_with_no_quantity(): void
    {
        $customer  = $this->makeCustomer();
        $catalogue = $this->makeCatalogue();

        $response = $this->withHeaders($this->authHeaders($customer))->postJson('/api/orders', [
            'catalogue_id' => $catalogue->id,
        ]);

        $response->assertStatus(422)->assertJsonPath('reason', 'no_quantity');
        $this->assertSame(0, Order::count());
    }

    public function test_rejects_an_order_on_a_closed_catalogue(): void
    {
        $customer  = $this->makeCustomer();
        $catalogue = $this->makeCatalogue(['status' => 'closed']);

        $response = $this->withHeaders($this->authHeaders($customer))->postJson('/api/orders', [
            'catalogue_id' => $catalogue->id,
            'qty_m'        => 1,
        ]);

        $response->assertStatus(422)->assertJsonPath('reason', 'catalogue_closed');
    }

    /**
     * Regression test for the sold-out gap: status is still 'open' but every
     * piece has already been ordered — must be rejected the same as a
     * manually closed catalogue (Catalogue::isSoldOut()).
     */
    public function test_rejects_an_order_on_a_catalogue_with_zero_available_pieces(): void
    {
        $buyer     = $this->makeCustomer(['email' => 'buyer@example.com']);
        $catalogue = $this->makeCatalogue(['qty_per_design' => 1, 'number_of_designs' => 1]);
        $design    = $catalogue->designs->first();

        // Consume the only available piece.
        $this->withHeaders($this->authHeaders($buyer))->postJson('/api/orders', [
            'catalogue_id' => $catalogue->id,
            'qty_xs'       => 1,
        ])->assertStatus(201);

        $secondCustomer = $this->makeCustomer(['email' => 'second@example.com']);

        $response = $this->withHeaders($this->authHeaders($secondCustomer))->postJson('/api/orders', [
            'catalogue_id' => $catalogue->id,
            'qty_s'        => 1,
        ]);

        $response->assertStatus(422)->assertJsonPath('reason', 'catalogue_closed');
    }

    public function test_rejects_a_duplicate_order_on_the_same_catalogue(): void
    {
        $customer  = $this->makeCustomer();
        $catalogue = $this->makeCatalogue(['qty_per_design' => 100]);

        $this->withHeaders($this->authHeaders($customer))->postJson('/api/orders', [
            'catalogue_id' => $catalogue->id,
            'qty_xs'       => 1,
        ])->assertStatus(201);

        $response = $this->withHeaders($this->authHeaders($customer))->postJson('/api/orders', [
            'catalogue_id' => $catalogue->id,
            'qty_s'        => 1,
        ]);

        $response->assertStatus(409)->assertJsonPath('reason', 'duplicate_order');
    }

    public function test_order_placement_requires_authentication(): void
    {
        $this->postJson('/api/orders', ['catalogue_id' => 1, 'qty_xs' => 1])->assertStatus(401);
    }
}
