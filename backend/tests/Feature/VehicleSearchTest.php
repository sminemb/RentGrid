<?php

namespace Tests\Feature;

use App\Models\ReservationStatus;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VehicleSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-01 00:00:00', 'UTC'));
    }

    public function test_search_returns_distinct_public_vehicles_free_for_the_whole_period(): void
    {
        $first = Vehicle::factory()->create(['is_published' => true, 'display_label' => 'Unit A']);
        $second = Vehicle::factory()->create(['is_published' => true, 'display_label' => 'Unit B']);
        $second->reservations()->create([
            'pickup_date' => '2026-06-10',
            'return_date' => '2026-06-12',
            'status' => ReservationStatus::Cancelled,
        ]);
        $reserved = Vehicle::factory()->create(['is_published' => true]);
        $reserved->reservations()->create([
            'pickup_date' => '2026-06-09',
            'return_date' => '2026-06-10',
            'status' => ReservationStatus::Confirmed,
        ]);
        $blocked = Vehicle::factory()->create(['is_published' => true]);
        $blocked->availabilityBlocks()->create(['starts_on' => '2026-06-12', 'ends_on' => '2026-06-12']);
        Vehicle::factory()->create();

        $this->getJson('/api/vehicles/availability?pickup_date=2026-06-10&return_date=2026-06-12')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $first->id, 'display_label' => 'Unit A', 'make' => 'Toyota', 'model' => 'Vios'],
                ['id' => $second->id, 'display_label' => 'Unit B', 'make' => 'Toyota', 'model' => 'Vios'],
            ]]);
    }

    public function test_valid_search_can_return_no_matches(): void
    {
        Vehicle::factory()->create();

        $this->getJson('/api/vehicles/availability?pickup_date=2026-06-10&return_date=2026-06-10')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_anonymous_search_does_not_start_a_session(): void
    {
        config(['session.driver' => 'database']);

        $this->getJson('/api/vehicles/availability?pickup_date=2026-06-10&return_date=2026-06-10')
            ->assertOk()
            ->assertCookieMissing(config('session.cookie'))
            ->assertCookieMissing('XSRF-TOKEN');

        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_search_returns_date_field_errors(): void
    {
        $this->getJson('/api/vehicles/availability')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pickup_date', 'return_date']);

        $this->getJson('/api/vehicles/availability?pickup_date=2026-06-12&return_date=2026-06-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('return_date');

        $this->getJson('/api/vehicles/availability?pickup_date=2026-05-31&return_date=2026-06-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pickup_date');
    }
}
