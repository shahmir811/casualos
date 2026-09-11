<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use Tests\TestCase;

/**
 * GET /api/countries reads straight off Customer::COUNTRIES — no DB table
 * backs it, so unlike most of this project's Api\* tests, no hand-built
 * schema is needed here.
 */
class CountryTest extends TestCase
{
    public function test_returns_the_full_country_list_public_no_auth(): void
    {
        $response = $this->getJson('/api/countries');

        $response->assertOk()->assertJson([
            'countries' => Customer::COUNTRIES,
        ]);
    }
}
