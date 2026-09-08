<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Design;
use App\Models\DispatchBatch;
use App\Models\DispatchBatchItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises GET /api/orders and GET /api/orders/{order} end to end.
 *
 * Builds its own minimal schema, same approach as AuthTest and for the same
 * reason: several historical migrations use raw MySQL-only
 * `ALTER TABLE ... MODIFY COLUMN ... ENUM(...)`, which SQLite can't run, so
 * RefreshDatabase against the full migration set isn't viable here. This
 * suite needs a wider slice than AuthTest (orders, catalogues, designs,
 * order_items, payments, dispatch batches, plus empty order_reductions /
 * order_reduction_items tables so Order::netSizeQty()'s lazy relation query
 * doesn't hit a missing-table error under SQLite).
 */
class OrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Order/Payment/DispatchBatch/Catalogue all use Spatie's LogsActivity
        // trait, which would otherwise try to insert into an activity_log
        // table this minimal schema doesn't create.
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
            $table->foreignId('created_by')->nullable()->constrained('users');
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

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('catalogue_id')->constrained('catalogues');
            $table->foreignId('assigned_bank_account_id')->nullable();
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

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->string('payment_type');
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
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

        // Empty tables — Order::netSizeQty() lazily queries the reductions
        // relation even when no reduction ever happened, so the table just
        // needs to exist under SQLite.
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
            'payments', 'bank_accounts',
            'order_items', 'orders',
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

    protected function makeOrder(Customer $customer, array $attributes = []): Order
    {
        $catalogue = \App\Models\Catalogue::create([
            'name'               => 'SAKOON',
            'qty_per_design'     => 70,
            'number_of_designs'  => 1,
            'quantity_benchmark' => 10,
            'order_token'        => \Illuminate\Support\Str::random(32),
            'hd_gallery_token'   => \Illuminate\Support\Str::random(32),
            'status'             => 'open',
        ]);

        $design = Design::create([
            'catalogue_id'        => $catalogue->id,
            'name'                => 'ISHQIA-1',
            'selling_price'       => 20000,
            'manufacturing_type'  => 'in_house',
        ]);

        $order = Order::create(array_merge([
            'customer_id'     => $customer->id,
            'catalogue_id'    => $catalogue->id,
            'status'          => 'confirmed',
            'order_number'    => (string) random_int(1000000, 9999999),
            'submitted_name'  => $customer->name,
            'submitted_city'  => $customer->city,
            'submitted_email' => $customer->email,
            'total_amount'    => 180000,
            'total_paid'      => 100000,
            'outstanding_balance' => 80000,
        ], $attributes));

        OrderItem::create([
            'order_id'     => $order->id,
            'design_id'    => $design->id,
            'qty_xs'       => 1,
            'qty_s'        => 2,
            'qty_m'        => 3,
            'qty_l'        => 2,
            'qty_xl'       => 1,
            'unit_price'   => 20000,
            'total_qty'    => 9,
            'total_amount' => 180000,
        ]);

        Payment::create([
            'customer_id'     => $customer->id,
            'order_id'        => $order->id,
            'sequence_number' => 1,
            'payment_type'    => 'bank_transfer',
            'amount'          => 100000,
            'payment_date'    => now(),
        ]);

        return $order->fresh();
    }

    public function test_orders_index_lists_only_the_authenticated_customers_orders(): void
    {
        $mine   = $this->makeCustomer(['email' => 'mine@example.com']);
        $theirs = $this->makeCustomer(['email' => 'theirs@example.com']);

        $this->makeOrder($mine);
        $this->makeOrder($theirs);

        $response = $this->withHeaders($this->authHeaders($mine))->getJson('/api/orders');

        $response->assertOk();
        $orders = $response->json('orders');
        $this->assertCount(1, $orders);
        $this->assertSame($mine->id, Order::find($orders[0]['id'])->customer_id);
    }

    public function test_orders_index_returns_expected_summary_shape(): void
    {
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/orders');

        $response->assertOk()->assertJson([
            'orders' => [[
                'id'                  => $order->id,
                'order_number'        => $order->order_number,
                'status'              => 'confirmed',
                'catalogue'           => ['name' => 'SAKOON'],
                'total_pieces'        => 9,
                'is_fully_dispatched' => false,
            ]],
        ]);
    }

    public function test_orders_show_returns_full_detail_for_the_owning_customer(): void
    {
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson("/api/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('order.id', $order->id)
            ->assertJsonPath('order.size_breakdown', ['xs' => 1, 's' => 2, 'm' => 3, 'l' => 2, 'xl' => 1])
            ->assertJsonPath('order.total_pieces', 9)
            ->assertJsonPath('order.items.0.design.name', 'ISHQIA-1')
            ->assertJsonPath('order.payments.0.payment_id', "{$order->order_number}p1")
            ->assertJsonPath('order.payments.0.amount', '100000.00');
    }

    public function test_orders_show_404s_on_another_customers_order_not_403(): void
    {
        $mine   = $this->makeCustomer(['email' => 'mine@example.com']);
        $theirs = $this->makeCustomer(['email' => 'theirs@example.com']);

        $theirOrder = $this->makeOrder($theirs);

        $response = $this->withHeaders($this->authHeaders($mine))->getJson("/api/orders/{$theirOrder->id}");

        $response->assertStatus(404);
    }

    public function test_orders_show_404s_on_a_nonexistent_order(): void
    {
        $customer = $this->makeCustomer();

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/orders/999999');

        $response->assertStatus(404);
    }

    public function test_orders_endpoints_require_authentication(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
        $this->getJson('/api/orders/1')->assertStatus(401);
    }

    public function test_orders_show_reflects_dispatch_batches(): void
    {
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);
        $design   = $order->items->first()->design;

        $batch = DispatchBatch::create([
            'order_id'          => $order->id,
            'batch_number'      => 1,
            'dispatch_date'     => now(),
            'shipping_address'  => 'Lahore, Pakistan',
        ]);

        DispatchBatchItem::create([
            'dispatch_batch_id' => $batch->id,
            'design_id'         => $design->id,
            'size'              => 's',
            'quantity'          => 2,
        ]);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson("/api/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('order.dispatch_batches.0.total_pieces', 2)
            ->assertJsonPath('order.dispatch_batches.0.items.0.design', 'ISHQIA-1');
    }
}
