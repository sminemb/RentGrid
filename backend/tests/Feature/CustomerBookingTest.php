<?php

namespace Tests\Feature;

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CustomerBookingTest extends TestCase
{
    use RefreshDatabase;

    private const CONTACT = [
        'name' => 'Demo Customer',
        'email' => 'demo@example.test',
        'phone' => '+63 (917) 555-0100',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-01 00:00:00', 'UTC'));
        config(['rentgrid.booking' => [
            'pickup_location' => 'Demo Rental Desk',
            'currency' => 'PHP',
            'daily_rate_minor' => 150_000,
            'fee_minor' => 10_000,
            'deposit_minor' => 50_000,
        ]]);
    }

    public function test_quote_prices_inclusive_days_and_shows_the_exact_vehicle_and_pickup_location(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);

        $this->quote($vehicle, '2026-06-12')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertCookie(config('session.cookie'))
            ->assertCookie('XSRF-TOKEN')
            ->assertJsonPath('vehicle.id', $vehicle->id)
            ->assertJsonPath('vehicle.display_label', $vehicle->display_label)
            ->assertJsonPath('pickup_date', '2026-06-10')
            ->assertJsonPath('return_date', '2026-06-12')
            ->assertJsonPath('pickup_location', 'Demo Rental Desk')
            ->assertJsonPath('currency', 'PHP')
            ->assertJsonPath('days', 3)
            ->assertJsonPath('rental_minor', 450_000)
            ->assertJsonPath('fee_minor', 10_000)
            ->assertJsonPath('deposit_minor', 50_000)
            ->assertJsonPath('amount_due_minor', 510_000)
            ->assertJsonStructure(['quote_token']);
    }

    public function test_quote_refuses_unconfigured_terms_and_unavailable_vehicles(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->quote($vehicle)->assertConflict()->assertJsonPath('code', 'vehicle_unavailable');

        $vehicle->update(['is_published' => true]);
        config(['rentgrid.booking.pickup_location' => null]);
        $this->quote($vehicle)->assertServiceUnavailable();
    }

    public function test_contact_fields_are_validated_before_booking(): void
    {
        $this->postJson('/api/bookings', [
            'quote_token' => 'unused',
            'name' => ' ',
            'email' => 'demo.example.test',
            'phone' => '123-ABCD',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone']);
    }

    public function test_booking_commits_the_reviewed_amount_and_private_contact_details(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $quote = $this->quote($vehicle)->assertOk()->assertJsonPath('days', 1);

        $booking = $this->book($quote)->assertCreated()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('vehicle_id', $vehicle->id)
            ->assertJsonPath('amount_due_minor', 210_000)
            ->assertJsonMissingPath('guest_email');

        $this->assertDatabaseHas('reservations', [
            'vehicle_id' => $vehicle->id,
            'reference' => $booking->json('reference'),
            'guest_name' => self::CONTACT['name'],
            'guest_email' => self::CONTACT['email'],
            'guest_phone' => self::CONTACT['phone'],
            'amount_due_minor' => 210_000,
            'currency' => 'PHP',
            'status' => 'confirmed',
        ]);
    }

    public function test_a_quote_cannot_be_used_from_a_different_session(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $token = $this->quote($vehicle)->assertOk()->json('quote_token');

        $this->postJson('/api/bookings', [...self::CONTACT, 'quote_token' => $token])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quote_token');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_booking_rejects_a_changed_price(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $quote = $this->quote($vehicle)->assertOk();
        config(['rentgrid.booking.daily_rate_minor' => 160_000]);

        $this->book($quote)->assertConflict()->assertJsonPath('code', 'price_changed');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_booking_rechecks_publication_and_staff_blocks(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $quote = $this->quote($vehicle)->assertOk();

        $vehicle->update(['is_published' => false]);
        $this->book($quote)->assertConflict()->assertJsonPath('code', 'vehicle_unavailable');

        $vehicle->update(['is_published' => true]);
        $vehicle->availabilityBlocks()->create(['starts_on' => '2026-06-10', 'ends_on' => '2026-06-10']);
        $this->book($quote)->assertConflict()->assertJsonPath('code', 'vehicle_unavailable');
        $this->assertDatabaseCount('reservations', 0);
    }

    private function quote(Vehicle $vehicle, string $returnDate = '2026-06-10'): TestResponse
    {
        return $this->postJson('/api/booking-quotes', [
            'vehicle_id' => $vehicle->id,
            'pickup_date' => '2026-06-10',
            'return_date' => $returnDate,
        ]);
    }

    private function book(TestResponse $quote): TestResponse
    {
        $cookie = config('session.cookie');

        return $this->withCredentials()
            ->withUnencryptedCookie($cookie, $quote->getCookie($cookie, false)->getValue())
            ->postJson('/api/bookings', [...self::CONTACT, 'quote_token' => $quote->json('quote_token')]);
    }
}
