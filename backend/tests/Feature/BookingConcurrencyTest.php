<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        config(['rentgrid.booking' => [
            'pickup_location' => 'Demo Rental Desk',
            'currency' => 'PHP',
            'daily_rate_minor' => 150_000,
            'fee_minor' => 0,
            'deposit_minor' => 0,
        ]]);
    }

    public function test_overlapping_requests_commit_only_one_booking_for_the_same_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $first = $this->prepareBooking($vehicle, 'rentgrid-booking-first');
        $second = $this->prepareBooking($vehicle, 'rentgrid-booking-second');

        try {
            DB::beginTransaction();
            try {
                Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                $first->start();
                $second->start();
                $this->waitForDatabaseLocks([
                    'rentgrid-booking-first' => $first,
                    'rentgrid-booking-second' => $second,
                ]);
            } finally {
                DB::rollBack();
            }

            $responses = array_column([$this->workerResponse($first), $this->workerResponse($second)], 'body', 'status');
            $this->assertEqualsCanonicalizing([201, 409], array_keys($responses));
            $this->assertSame('vehicle_unavailable', $responses[409]['code']);
            $this->assertDatabaseCount('reservations', 1);
            $this->assertDatabaseHas('reservations', [
                'vehicle_id' => $vehicle->id,
                'status' => 'confirmed',
                'reference' => $responses[201]['reference'],
            ]);
        } finally {
            $first->stop(0);
            $second->stop(0);
        }
    }

    public function test_a_locked_vehicle_does_not_delay_booking_another_vehicle(): void
    {
        $firstVehicle = Vehicle::factory()->create(['is_published' => true]);
        $otherVehicle = Vehicle::factory()->create(['is_published' => true]);
        $first = $this->prepareBooking($firstVehicle, 'rentgrid-booking-first');
        $other = $this->prepareBooking($otherVehicle, 'rentgrid-booking-other');

        try {
            DB::beginTransaction();
            try {
                Vehicle::query()->lockForUpdate()->findOrFail($firstVehicle->id);
                $first->start();
                $this->waitForDatabaseLocks(['rentgrid-booking-first' => $first]);
                $other->start();
                $otherResult = $this->workerResponse($other);
                $this->assertSame(201, $otherResult['status']);
            } finally {
                DB::rollBack();
            }

            $firstResult = $this->workerResponse($first);
            $this->assertSame(201, $firstResult['status']);
            $this->assertNotSame($firstResult['body']['reference'], $otherResult['body']['reference']);
            $this->assertDatabaseCount('reservations', 2);
        } finally {
            $first->stop(0);
            $other->stop(0);
        }
    }

    public function test_simultaneous_retries_return_the_same_reference(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $quote = $this->quote($vehicle);
        $first = $this->prepareBooking($vehicle, 'rentgrid-retry-first', $quote);
        $second = $this->prepareBooking($vehicle, 'rentgrid-retry-second', $quote);

        try {
            DB::beginTransaction();
            try {
                Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                $first->start();
                $second->start();
                $this->waitForDatabaseLocks([
                    'rentgrid-retry-first' => $first,
                    'rentgrid-retry-second' => $second,
                ]);
            } finally {
                DB::rollBack();
            }

            $firstResult = $this->workerResponse($first);
            $secondResult = $this->workerResponse($second);
            $this->assertEqualsCanonicalizing([200, 201], [$firstResult['status'], $secondResult['status']]);
            $this->assertSame($firstResult['body']['reference'], $secondResult['body']['reference']);
            $this->assertDatabaseCount('reservations', 1);
        } finally {
            $first->stop(0);
            $second->stop(0);
        }
    }

    public function test_a_staff_block_and_an_overlapping_booking_cannot_both_commit(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $date = today(config('rentgrid.business_timezone'))->addWeek()->toDateString();
        $booking = $this->prepareBooking($vehicle, 'rentgrid-booking');
        $block = $this->prepareStaffRequest(
            'rentgrid-block', "/api/staff/vehicles/{$vehicle->id}/availability/blocks", 'POST',
            ['starts_on' => $date, 'ends_on' => $date],
        );

        try {
            DB::beginTransaction();
            try {
                Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                $booking->start();
                $block->start();
                $this->waitForDatabaseLocks(['rentgrid-booking' => $booking, 'rentgrid-block' => $block]);
            } finally {
                DB::rollBack();
            }

            $bookingResult = $this->workerResponse($booking);
            $blockResult = $this->workerResponse($block);
            $this->assertEqualsCanonicalizing([201, 409], [$bookingResult['status'], $blockResult['status']]);
            $blockWon = $blockResult['status'] === 201;
            $this->assertDatabaseCount('availability_blocks', $blockWon ? 1 : 0);
            $this->assertDatabaseCount('reservations', $blockWon ? 0 : 1);
            $this->assertSame($blockWon ? 'vehicle_unavailable' : 'confirmed_booking_overlap',
                $blockWon ? $bookingResult['body']['code'] : $blockResult['body']['code']);
            $this->getJson("/api/vehicles/availability?pickup_date=$date&return_date=$date")
                ->assertOk()->assertExactJson(['data' => []]);
        } finally {
            $booking->stop(0);
            $block->stop(0);
        }
    }

    public function test_withdrawal_commits_before_a_waiting_booking_and_closes_the_listing(): void
    {
        $vehicle = Vehicle::factory()->create(['is_published' => true]);
        $booking = $this->prepareBooking($vehicle, 'rentgrid-booking');
        $withdrawal = $this->prepareStaffRequest(
            'rentgrid-withdrawal', "/api/staff/vehicles/{$vehicle->id}/publication", 'PUT',
            ['is_published' => false],
        );

        try {
            DB::beginTransaction();
            try {
                Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                $withdrawal->start();
                $this->waitForDatabaseLocks(['rentgrid-withdrawal' => $withdrawal]);
                $booking->start();
                $this->waitForDatabaseLocks(['rentgrid-withdrawal' => $withdrawal, 'rentgrid-booking' => $booking]);
            } finally {
                DB::rollBack();
            }

            $this->assertSame(200, $this->workerResponse($withdrawal)['status']);
            $bookingResult = $this->workerResponse($booking);
            $this->assertSame(409, $bookingResult['status']);
            $this->assertSame('vehicle_unavailable', $bookingResult['body']['code']);
            $this->assertFalse($vehicle->refresh()->is_published);
            $this->assertDatabaseCount('reservations', 0);
        } finally {
            $withdrawal->stop(0);
            $booking->stop(0);
        }
    }

    private function quote(Vehicle $vehicle): TestResponse
    {
        $this->app['session']->forgetDrivers();
        $pickup = today(config('rentgrid.business_timezone'))->addWeek();

        return $this->postJson('/api/booking-quotes', [
            'vehicle_id' => $vehicle->id,
            'pickup_date' => $pickup->toDateString(),
            'return_date' => $pickup->addDay()->toDateString(),
        ])->assertOk();
    }

    private function prepareBooking(Vehicle $vehicle, string $worker, ?TestResponse $quote = null): Process
    {
        $quote ??= $this->quote($vehicle);

        return $this->prepareRequest($worker, '/api/bookings', 'POST', $quote, [
            'quote_token' => $quote->json('quote_token'),
            'name' => 'Demo Customer',
            'email' => 'demo@example.test',
            'phone' => '+639175550100',
        ]);
    }

    private function prepareStaffRequest(string $worker, string $path, string $method, array $payload): Process
    {
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();
        $staff = User::factory()->create(['is_staff' => true]);
        $session = $this->postJson('/api/staff/sign-in', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertNoContent();

        return $this->prepareRequest($worker, $path, $method, $session, $payload);
    }

    private function prepareRequest(string $worker, string $path, string $method, TestResponse $session, array $payload): Process
    {
        $cookie = config('session.cookie');

        return new Process([PHP_BINARY, __DIR__.'/../Support/submit-json-request.php'], base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => 'rentgrid_test',
            'DB_URL' => '',
            'SESSION_DRIVER' => 'database',
            'RENTAL_PICKUP_LOCATION' => config('rentgrid.booking.pickup_location'),
            'RENTAL_CURRENCY' => config('rentgrid.booking.currency'),
            'RENTAL_DAILY_RATE_MINOR' => (string) config('rentgrid.booking.daily_rate_minor'),
            'RENTAL_FEE_MINOR' => '0',
            'RENTAL_DEPOSIT_MINOR' => '0',
        ], json_encode([
            'worker' => $worker,
            'cookies' => [$cookie => $session->getCookie($cookie, false)->getValue()],
            'path' => $path,
            'method' => $method,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR), 20);
    }

    /** @param array<string, Process> $workers */
    private function waitForDatabaseLocks(array $workers): void
    {
        $deadline = microtime(true) + 8;

        do {
            DB::selectOne('select pg_stat_clear_snapshot()');
            $waiting = DB::table('pg_stat_activity')
                ->whereIn('application_name', array_keys($workers))
                ->where('wait_event_type', 'Lock')
                ->where('query', 'ilike', '%"vehicles"%for update%')
                ->count();
            if ($waiting === count($workers)) {
                return;
            }

            foreach ($workers as $worker) {
                $this->assertTrue($worker->isRunning(), $worker->getErrorOutput().$worker->getOutput());
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        $this->fail('The concurrent requests did not wait on the vehicle lock.');
    }

    private function workerResponse(Process $worker): array
    {
        $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());

        return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
