<?php

namespace Tests\Unit;

use App\Exceptions\OrderPlacementException;
use App\Models\Catalogue;
use App\Models\Design;
use App\Services\OrderPlacementService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Locks the collective-quantity pricing rules.
 *
 * These tests hit no database — the designs relation is set by hand on an
 * unsaved Catalogue, so quote() never queries. That keeps them fast and
 * avoids the MySQL enum columns that SQLite can't model.
 *
 * When POST /api/orders is built, it must satisfy these same numbers. If a
 * change to the app makes one of these fail, the web order form's totals have
 * changed too — that is the point.
 */
class OrderPlacementServiceTest extends TestCase
{
    protected OrderPlacementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderPlacementService();
    }

    /**
     * @param  array<int, array{selling: int, discount: int|null}>  $designs
     */
    protected function catalogue(array $designs, ?int $benchmark = null): Catalogue
    {
        $catalogue = new Catalogue();
        $catalogue->quantity_benchmark = $benchmark;

        $models = [];

        foreach ($designs as $i => $spec) {
            $design = new Design();
            $design->id             = $i + 1;
            $design->name           = 'Design ' . ($i + 1);
            $design->selling_price  = $spec['selling'];
            $design->discount_price = $spec['discount'];

            $models[] = $design;
        }

        $catalogue->setRelation('designs', new Collection($models));

        return $catalogue;
    }

    public function test_one_size_unit_fans_out_across_every_design(): void
    {
        // 1 XS on a 7-design catalogue = 7 pieces manufactured, not 1.
        $catalogue = $this->catalogue(array_fill(0, 7, ['selling' => 1000, 'discount' => null]));

        $quote = $this->service->quote($catalogue, ['xs' => 1]);

        $this->assertSame(1, $quote['pieces_per_design']);
        $this->assertSame(7, $quote['total_pieces']);
        $this->assertSame(7000, $quote['total_amount']);
        $this->assertCount(7, $quote['lines']);
    }

    public function test_pieces_per_design_is_the_sum_of_all_five_sizes(): void
    {
        $catalogue = $this->catalogue(array_fill(0, 3, ['selling' => 500, 'discount' => null]));

        $quote = $this->service->quote($catalogue, [
            'xs' => 1, 's' => 2, 'm' => 3, 'l' => 4, 'xl' => 5,
        ]);

        $this->assertSame(15, $quote['pieces_per_design']);
        $this->assertSame(45, $quote['total_pieces']);
        $this->assertSame(15 * 500 * 3, $quote['total_amount']);
    }

    public function test_discount_requires_strictly_more_than_the_benchmark(): void
    {
        $catalogue = $this->catalogue(
            [['selling' => 1000, 'discount' => 800]],
            benchmark: 10
        );

        // Exactly at the benchmark — still full price. This is the shipped
        // behaviour and matches orderCalc() in public/order.blade.php.
        $atBenchmark = $this->service->quote($catalogue, ['m' => 10]);
        $this->assertFalse($atBenchmark['uses_discount']);
        $this->assertSame(10_000, $atBenchmark['total_amount']);

        // One piece over — discount applies.
        $overBenchmark = $this->service->quote($catalogue, ['m' => 11]);
        $this->assertTrue($overBenchmark['uses_discount']);
        $this->assertSame(8_800, $overBenchmark['total_amount']);
    }

    public function test_no_benchmark_means_no_discount_at_any_quantity(): void
    {
        $catalogue = $this->catalogue([['selling' => 1000, 'discount' => 800]]);

        $quote = $this->service->quote($catalogue, ['m' => 5000]);

        $this->assertFalse($quote['uses_discount']);
        $this->assertSame(5_000_000, $quote['total_amount']);
    }

    public function test_designs_without_a_discount_price_stay_on_selling_price(): void
    {
        $catalogue = $this->catalogue([
            ['selling' => 1000, 'discount' => 800],
            ['selling' => 1000, 'discount' => null],
        ], benchmark: 5);

        $quote = $this->service->quote($catalogue, ['l' => 10]);

        $this->assertTrue($quote['uses_discount']);
        $this->assertSame(800, $quote['lines'][0]['unit_price']);
        $this->assertSame(1000, $quote['lines'][1]['unit_price']);
        $this->assertSame(18_000, $quote['total_amount']);
    }

    public function test_prices_are_rounded_per_design_before_multiplying(): void
    {
        // 999.50 rounds to 1000 per design, then x 3 pieces — not 2998.5.
        $catalogue = $this->catalogue([['selling' => 999.50, 'discount' => null]]);

        $quote = $this->service->quote($catalogue, ['s' => 3]);

        $this->assertSame(1000, $quote['lines'][0]['unit_price']);
        $this->assertSame(3000, $quote['total_amount']);
    }

    public function test_an_empty_order_is_rejected(): void
    {
        $catalogue = $this->catalogue([['selling' => 1000, 'discount' => null]]);

        $this->expectException(OrderPlacementException::class);

        try {
            $this->service->quote($catalogue, []);
        } catch (OrderPlacementException $e) {
            $this->assertSame(OrderPlacementException::NO_QUANTITY, $e->reason());
            throw $e;
        }
    }

    public function test_size_input_is_normalised_from_either_key_style(): void
    {
        // The Blade form posts qty_xs; the mobile app will post xs.
        $this->assertSame(
            ['xs' => 1, 's' => 2, 'm' => 0, 'l' => 0, 'xl' => 0],
            $this->service->normaliseSizes(['qty_xs' => 1, 'qty_s' => '2'])
        );

        $this->assertSame(
            ['xs' => 1, 's' => 2, 'm' => 0, 'l' => 0, 'xl' => 0],
            $this->service->normaliseSizes(['xs' => 1, 's' => 2])
        );
    }

    public function test_negative_and_junk_quantities_are_floored_to_zero(): void
    {
        $this->assertSame(
            ['xs' => 0, 's' => 0, 'm' => 4, 'l' => 0, 'xl' => 0],
            $this->service->normaliseSizes(['xs' => -5, 's' => 'abc', 'm' => 4, 'l' => null])
        );
    }
}
