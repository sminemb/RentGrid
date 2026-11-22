<?php

namespace Tests\Feature;

use App\Models\ReservationStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StaffReservationsTest extends TestCase
{
    use RefreshDatabase;

    private const CONTACT = [
        'name' => 'Demo Customer',
        'email' => 'customer@example.test',
        'phone' => '+639175550100',
    ];

    public function test_lookup_and_cancellation_require_staff_access(): void
    {
        $vehicle = Vehicle::factory()->create();
        $reservation = $vehicle->reservations()->create([
            'reference' => 'RG-EXISTING',
            'status' => ReservationStatus::Confirmed,
            'pickup_date' => '2026-06-10',
            'return_date' => '2026-06-10',
        ]);

        foreach ([$reservation->reference, 'RG-MISSING'] as $reference) {
            $path = "/api/staff/reservations/$reference";
            $this->getJson($path)->assertUnauthorized();
            $this->postJson("$path/cancel")->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create());
        foreach ([$reservation->reference, 'RG-MISSING'] as $reference) {
            $path = "/api/staff/reservations/$reference";
            $this->getJson($path)->assertForbidden();
            $this->postJson("$path/cancel")->assertForbidden();
        }

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    public function test_staff_can_find_cancel_and_rebook_a_reservation(): void
    {
        config(['rentgrid.booking' => [
            'pickup_location' => 'Demo Rental Desk',
            'currency' => 'PHP',
            'daily_rate_minor' => 150_000,
            'fee_minor' => 0,
            'deposit_minor' => 0,
        ]]);
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $reviewedVehicle = ['id' => $vehicle->id, ...$vehicle->details()];
        $day = today(config('rentgrid.business_timezone'))->addWeek()->toDateString();
        $dates = ['pickup_date' => $day, 'return_date' => $day];
        $search = '/api/vehicles/availability?'.http_build_query($dates);
        $quote = $this->postJson('/api/booking-quotes', ['vehicle_id' => $vehicle->id, ...$dates])->assertOk();
        $reference = $this->book($quote)->assertCreated()->json('reference');
        $reservationPath = "/api/staff/reservations/$reference";
        $this->getJson($search)->assertExactJson(['data' => []]);
        $vehicle->update(['display_label' => 'Renamed listing']);

        $this->actingAs(User::factory()->create(['is_staff' => true]));
        $this->getJson('/api/staff/reservations/RG-MISSING')->assertNotFound();
        $this->postJson('/api/staff/reservations/RG-MISSING/cancel')->assertNotFound();
        $this->getJson($reservationPath)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['data' => [
                'reference' => $reference,
                'status' => 'confirmed',
                'vehicle' => $reviewedVehicle,
                ...$dates,
                'guest' => self::CONTACT,
            ]]);

        $cancelled = $this->postJson("$reservationPath/cancel")->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.reference', $reference)
            ->assertJsonPath('data.status', 'cancelled')->json();
        $this->postJson("$reservationPath/cancel")->assertOk()->assertExactJson($cancelled);
        $this->getJson($reservationPath)->assertOk()->assertExactJson($cancelled);
        $this->assertDatabaseCount('reservations', 1);
        $this->getJson($search)->assertOk()->assertJsonPath('data.0.id', $vehicle->id);

        $this->book($quote)->assertOk()
            ->assertJsonPath('reference', $reference)->assertJsonPath('status', 'cancelled');
        $newQuote = $this->postJson('/api/booking-quotes', ['vehicle_id' => $vehicle->id, ...$dates])->assertOk();
        $replacement = $this->book($newQuote)->assertCreated()->assertJsonPath('status', 'confirmed');
        $this->assertNotSame($reference, $replacement->json('reference'));
        $this->postJson("$reservationPath/cancel")->assertOk()->assertExactJson($cancelled);
        $this->assertDatabaseCount('reservations', 2);
        $this->assertDatabaseHas('reservations', ['reference' => $replacement->json('reference'), 'status' => 'confirmed']);
        $this->getJson($search)->assertExactJson(['data' => []]);
    }

    private function book(TestResponse $quote): TestResponse
    {
        $cookie = config('session.cookie');

        return $this->withCredentials()
            ->withUnencryptedCookie($cookie, $quote->getCookie($cookie, false)->getValue())
            ->postJson('/api/bookings', ['quote_token' => $quote->json('quote_token'), ...self::CONTACT]);
    }
}
