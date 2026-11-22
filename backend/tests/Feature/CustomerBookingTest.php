<?php

namespace Tests\Feature;

use App\Models\ReservationStatus;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Monolog\Handler\TestHandler;
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

    public function test_booking_insert_failure_logs_only_the_database_error_code(): void
    {
        config([
            'app.debug' => true,
            'logging.default' => 'booking_test',
            'logging.channels.booking_test' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        ]);
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $quote = $this->quote($vehicle)->assertOk();
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reject_test_booking CHECK (FALSE)');

        $response = $this->book($quote)->assertInternalServerError();

        $records = Log::channel('booking_test')->getHandlers()[0]->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('Booking confirmation failed.', $records[0]->message);
        $this->assertSame(['sqlstate' => '23514'], $records[0]->context);
        $response->assertExactJson(['message' => 'Unable to confirm this booking.']);
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_booking_accepts_a_quote_with_a_max_length_unicode_pickup_location(): void
    {
        $location = str_repeat("\u{20000}", 255);
        config(['rentgrid.booking.pickup_location' => $location]);
        $vehicle = Vehicle::factory()->create(['is_published' => true]);

        $quote = $this->quote($vehicle)->assertOk()->assertJsonPath('pickup_location', $location);

        $this->book($quote)->assertCreated()->assertJsonPath('pickup_location', $location);
        $this->assertDatabaseHas('reservations', ['vehicle_id' => $vehicle->id, 'pickup_location' => $location]);
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

    public function test_retry_recovers_the_reference_and_current_status_even_after_the_rental_date(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $quote = $this->quote($vehicle)->assertOk();
        $reference = $this->book($quote)->assertCreated()->json('reference');

        $this->book($quote)->assertOk()
            ->assertJsonPath('reference', $reference)
            ->assertJsonPath('status', 'confirmed');

        $vehicle->reservations()->update(['status' => ReservationStatus::Cancelled]);
        $vehicle->update(['is_published' => false]);
        $this->travelTo(Carbon::parse('2026-06-11 00:00:00', 'UTC'));
        $this->book($quote)->assertOk()
            ->assertJsonPath('reference', $reference)
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_booking_rejects_selection_changes_and_replays_from_another_session(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $quote = $this->quote($vehicle)->assertOk();
        $payload = [...self::CONTACT, 'quote_token' => $quote->json('quote_token')];
        $this->book($quote)->assertCreated();

        $this->postJson('/api/bookings', [...$payload, 'pickup_date' => '2026-06-11'])
            ->assertUnprocessable()->assertJsonValidationErrors('pickup_date');

        $this->withUnencryptedCookie(config('session.cookie'), 'other-guest')
            ->postJson('/api/bookings', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('quote_token');

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_confirmation_is_private_to_the_guest_session_and_uses_the_saved_terms(): void
    {
        config([
            'rentgrid.pickup_instructions' => 'Bring your booking reference to the main desk.',
            'rentgrid.cancellation_terms' => 'Call the demo desk to request a cancellation.',
        ]);
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $quote = $this->quote($vehicle)->assertOk();
        $reference = $this->book($quote)->assertCreated()->json('reference');
        $url = "/api/bookings/{$reference}";

        config([
            'rentgrid.booking.daily_rate_minor' => 999_000,
            'rentgrid.pickup_instructions' => 'New instructions.',
            'rentgrid.cancellation_terms' => 'New terms.',
        ]);
        $this->getJson($url)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('vehicle.id', $vehicle->id)
            ->assertJsonPath('vehicle.display_label', $vehicle->display_label)
            ->assertJsonPath('pickup_date', '2026-06-10')
            ->assertJsonPath('return_date', '2026-06-10')
            ->assertJsonPath('pickup_location', 'Demo Rental Desk')
            ->assertJsonPath('pickup_instructions', 'Bring your booking reference to the main desk.')
            ->assertJsonPath('cancellation_terms', 'Call the demo desk to request a cancellation.')
            ->assertJsonPath('amount_due_minor', 210_000)
            ->assertJsonMissingPath('guest_email')
            ->assertJsonMissingPath('guest_session_key');

        $vehicle->reservations()->update(['status' => ReservationStatus::Cancelled]);
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'cancelled');

        $this->withUnencryptedCookie(config('session.cookie'), 'other-guest')
            ->getJson($url)
            ->assertNotFound()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonMissingPath('reference');
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
