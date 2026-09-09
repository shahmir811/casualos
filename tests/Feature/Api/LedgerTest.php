<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerLedger;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises GET /api/ledger — scoping to the authenticated customer, sign
 * preservation (Section 7 of CLAUDE.md — amounts are returned exactly as
 * stored, never flipped), and reference resolution (order_number/payment_id)
 * mirroring \App\Http\Controllers\LedgerController::show()'s $orderMap
 * pattern. Same hand-built-schema approach as OrderTest, for the same
 * SQLite-vs-MySQL-enum-migration reason.
 */
class LedgerTest extends TestCase
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
            $table->string('country')->nullable();
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
            $table->string('order_token', 64)->unique();
            $table->string('hd_gallery_token', 64)->unique();
            $table->string('status')->default('open');
            $table->timestamps();
        });

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
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedInteger('sequence_number')->nullable();
            $table->string('payment_type');
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->timestamps();
            $table->unique(['order_id', 'sequence_number']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable();
            $table->foreignId('order_reduction_id')->nullable();
            $table->string('order_number')->nullable();
            $table->string('catalogue_name')->nullable();
            $table->foreignId('customer_id')->constrained('customers');
            $table->decimal('amount', 12, 2);
            $table->string('refund_method');
            $table->date('refund_date');
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
    }

    protected function tearDown(): void
    {
        foreach ([
            'customer_ledger', 'refunds', 'payments', 'orders', 'catalogues',
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

    protected function makeOrder(Customer $customer): Order
    {
        $catalogue = \App\Models\Catalogue::create([
            'name'             => 'SAKOON',
            'order_token'      => \Illuminate\Support\Str::random(32),
            'hd_gallery_token' => \Illuminate\Support\Str::random(32),
        ]);

        return Order::create([
            'customer_id'          => $customer->id,
            'catalogue_id'         => $catalogue->id,
            'status'               => 'confirmed',
            'order_number'         => (string) random_int(1000000, 9999999),
            'submitted_name'       => $customer->name,
            'submitted_city'       => $customer->city,
            'submitted_email'      => $customer->email,
            'total_amount'         => 180000,
            'total_paid'           => 100000,
            'outstanding_balance'  => 80000,
        ]);
    }

    public function test_ledger_is_scoped_to_the_authenticated_customer(): void
    {
        $mine   = $this->makeCustomer(['email' => 'mine@example.com']);
        $theirs = $this->makeCustomer(['email' => 'theirs@example.com']);

        CustomerLedger::create([
            'customer_id'      => $mine->id,
            'transaction_type' => 'order_charged',
            'amount'           => 180000,
            'created_by'       => 1,
        ]);

        CustomerLedger::create([
            'customer_id'      => $theirs->id,
            'transaction_type' => 'order_charged',
            'amount'           => 999999,
            'created_by'       => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders($mine))->getJson('/api/ledger');

        $response->assertOk();
        $rows = $response->json('ledger');
        $this->assertCount(1, $rows);
        $this->assertSame('180000.00', $rows[0]['amount']);
    }

    public function test_ledger_preserves_stored_sign_and_resolves_order_and_payment_references(): void
    {
        $customer = $this->makeCustomer();
        $order    = $this->makeOrder($customer);

        $payment = Payment::create([
            'customer_id'     => $customer->id,
            'order_id'        => $order->id,
            'sequence_number' => 1,
            'payment_type'    => 'bank_transfer',
            'amount'          => 100000,
            'payment_date'    => now(),
        ]);

        // order_charged is positive (they owe), per Section 7's sign convention.
        CustomerLedger::create([
            'customer_id'      => $customer->id,
            'transaction_type' => 'order_charged',
            'amount'           => 180000,
            'reference_type'   => Order::class,
            'reference_id'     => $order->id,
            'created_by'       => 1,
        ]);

        // payment_received is negative (they owe less).
        CustomerLedger::create([
            'customer_id'      => $customer->id,
            'transaction_type' => 'payment_received',
            'amount'           => -100000,
            'reference_type'   => Payment::class,
            'reference_id'     => $payment->id,
            'created_by'       => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/ledger');

        $response->assertOk();
        $rows = collect($response->json('ledger'))->keyBy('transaction_type');

        $this->assertSame('180000.00', $rows['order_charged']['amount']);
        $this->assertSame($order->order_number, $rows['order_charged']['order_number']);
        $this->assertNull($rows['order_charged']['payment_id']);

        $this->assertSame('-100000.00', $rows['payment_received']['amount']);
        $this->assertSame($order->order_number, $rows['payment_received']['order_number']);
        $this->assertSame("{$order->order_number}p1", $rows['payment_received']['payment_id']);
    }

    public function test_ledger_resolves_refund_via_its_snapshotted_order_number_even_without_a_live_order(): void
    {
        $customer = $this->makeCustomer();

        // Simulates a refund created by Delete Order's full flow (rule 5.28/5.29)
        // whose order was later hard-deleted — order_id is null, but the
        // order_number snapshot column survives.
        $refund = Refund::create([
            'customer_id'    => $customer->id,
            'order_id'       => null,
            'order_number'   => '1005410',
            'catalogue_name' => 'SAKOON',
            'amount'         => 5000,
            'refund_method'  => 'cash',
            'refund_date'    => now(),
        ]);

        CustomerLedger::create([
            'customer_id'      => $customer->id,
            'transaction_type' => 'refund_issued',
            'amount'           => 5000,
            'reference_type'   => Refund::class,
            'reference_id'     => $refund->id,
            'created_by'       => 1,
        ]);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/ledger');

        $response->assertOk()->assertJsonPath('ledger.0.order_number', '1005410');
    }

    public function test_ledger_includes_advance_credit_balance(): void
    {
        $customer = $this->makeCustomer(['advance_credit_balance' => 2500]);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/ledger');

        $response->assertOk()->assertJsonPath('advance_credit_balance', '2500.00');
    }

    public function test_ledger_requires_authentication(): void
    {
        $this->getJson('/api/ledger')->assertStatus(401);
    }
}
