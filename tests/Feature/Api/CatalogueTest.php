<?php

namespace Tests\Feature\Api;

use App\Models\Catalogue;
use App\Models\Customer;
use App\Models\Design;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Exercises GET /api/catalogues, GET /api/catalogues/{id}, and
 * POST /api/catalogues/{id}/quote. Same hand-built-schema approach as
 * OrderTest/LedgerTest, for the same SQLite-vs-MySQL-enum-migration reason.
 */
class CatalogueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['activitylog.enabled' => false]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
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
    }

    protected function tearDown(): void
    {
        foreach ([
            'order_items', 'orders', 'designs', 'catalogues',
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

    /**
     * @param  array<int, array{selling: int, discount: int|null}>  $designs
     */
    protected function makeCatalogue(array $designs, array $attributes = []): Catalogue
    {
        $catalogue = Catalogue::create(array_merge([
            'name'               => 'SAKOON',
            'qty_per_design'     => 70,
            'number_of_designs'  => count($designs),
            'quantity_benchmark' => 10,
            'order_token'        => \Illuminate\Support\Str::random(32),
            'hd_gallery_token'   => \Illuminate\Support\Str::random(32),
            'status'             => 'open',
        ], $attributes));

        foreach ($designs as $i => $spec) {
            Design::create([
                'catalogue_id'       => $catalogue->id,
                'name'               => 'Design ' . ($i + 1),
                'selling_price'      => $spec['selling'],
                'discount_price'     => $spec['discount'] ?? null,
                'manufacturing_type' => 'in_house',
            ]);
        }

        return $catalogue->fresh();
    }

    public function test_index_lists_only_open_catalogues(): void
    {
        $customer = $this->makeCustomer();
        $open     = $this->makeCatalogue([['selling' => 1000, 'discount' => null]], ['name' => 'OPEN-ONE']);
        $this->makeCatalogue([['selling' => 1000, 'discount' => null]], ['name' => 'CLOSED-ONE', 'status' => 'closed']);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/catalogues');

        $response->assertOk();
        $catalogues = $response->json('catalogues');
        $this->assertCount(1, $catalogues);
        $this->assertSame($open->id, $catalogues[0]['id']);
    }

    public function test_index_marks_already_ordered_for_a_catalogue_the_customer_has_ordered(): void
    {
        $customer  = $this->makeCustomer();
        $catalogue = $this->makeCatalogue([['selling' => 1000, 'discount' => null]]);

        Order::create([
            'customer_id'         => $customer->id,
            'catalogue_id'        => $catalogue->id,
            'status'              => 'received',
            'order_number'        => '1005400',
            'submitted_name'      => $customer->name,
            'submitted_city'      => $customer->city,
            'submitted_email'     => $customer->email,
            'total_amount'        => 1000,
            'outstanding_balance' => 1000,
        ]);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/catalogues');

        $response->assertOk()->assertJsonPath('catalogues.0.already_ordered', true);
    }

    public function test_index_reports_available_pieces_and_sold_out_correctly(): void
    {
        $customer  = $this->makeCustomer();
        // 1 design, 5 pieces per design => 5 total pieces.
        $catalogue = $this->makeCatalogue([['selling' => 1000, 'discount' => null]], ['qty_per_design' => 5]);
        $design    = $catalogue->designs->first();

        $order = Order::create([
            'customer_id'         => $this->makeCustomer(['email' => 'buyer@example.com'])->id,
            'catalogue_id'        => $catalogue->id,
            'status'              => 'confirmed',
            'order_number'        => '1005401',
            'submitted_name'      => 'Buyer',
            'submitted_city'      => 'Lahore',
            'submitted_email'     => 'buyer@example.com',
            'total_amount'        => 5000,
            'outstanding_balance' => 5000,
        ]);

        OrderItem::create([
            'order_id'     => $order->id,
            'design_id'    => $design->id,
            'qty_xs'       => 5,
            'unit_price'   => 1000,
            'total_qty'    => 5,
            'total_amount' => 5000,
        ]);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson('/api/catalogues');

        $response->assertOk()
            ->assertJsonPath('catalogues.0.available_pieces', 0)
            ->assertJsonPath('catalogues.0.sold_out', true);
    }

    public function test_show_returns_designs(): void
    {
        $customer  = $this->makeCustomer();
        $catalogue = $this->makeCatalogue([
            ['selling' => 1000, 'discount' => 800],
            ['selling' => 1500, 'discount' => null],
        ]);

        $response = $this->withHeaders($this->authHeaders($customer))->getJson("/api/catalogues/{$catalogue->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('catalogue.designs'));
    }

    public function test_quote_matches_the_collective_quantity_pricing_model(): void
    {
        $customer  = $this->makeCustomer();
        // 3 designs, no benchmark — mirrors OrderPlacementServiceTest's basic case.
        $catalogue = $this->makeCatalogue(array_fill(0, 3, ['selling' => 500, 'discount' => null]));

        $response = $this->withHeaders($this->authHeaders($customer))
            ->postJson("/api/catalogues/{$catalogue->id}/quote", [
                'qty_xs' => 1, 'qty_s' => 2, 'qty_m' => 3, 'qty_l' => 4, 'qty_xl' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('quote.pieces_per_design', 15)
            ->assertJsonPath('quote.total_pieces', 45)
            ->assertJsonPath('quote.total_amount', 15 * 500 * 3);
    }

    public function test_quote_rejects_all_zero_quantities(): void
    {
        $customer  = $this->makeCustomer();
        $catalogue = $this->makeCatalogue([['selling' => 1000, 'discount' => null]]);

        $response = $this->withHeaders($this->authHeaders($customer))
            ->postJson("/api/catalogues/{$catalogue->id}/quote", []);

        $response->assertStatus(422)->assertJsonPath('reason', 'no_quantity');
    }

    public function test_catalogue_endpoints_require_authentication(): void
    {
        $this->getJson('/api/catalogues')->assertStatus(401);
    }
}
