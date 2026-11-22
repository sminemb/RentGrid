<?php

namespace Tests\Feature;

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

            $responses = array_column([$this->bookingResponse($first), $this->bookingResponse($second)], 'body', 'status');
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
                $otherResult = $this->bookingResponse($other);
                $this->assertSame(201, $otherResult['status']);
            } finally {
                DB::rollBack();
            }

            $firstResult = $this->bookingResponse($first);
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

            $firstResult = $this->bookingResponse($first);
            $secondResult = $this->bookingResponse($second);
            $this->assertEqualsCanonicalizing([200, 201], [$firstResult['status'], $secondResult['status']]);
            $this->assertSame($firstResult['body']['reference'], $secondResult['body']['reference']);
            $this->assertDatabaseCount('reservations', 1);
        } finally {
            $first->stop(0);
            $second->stop(0);
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
        $cookie = config('session.cookie');

        return new Process([PHP_BINARY, __DIR__.'/../Support/submit-booking.php'], base_path(), [
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
            'cookies' => [$cookie => $quote->getCookie($cookie, false)->getValue()],
            'booking' => [
                'quote_token' => $quote->json('quote_token'),
                'name' => 'Demo Customer',
                'email' => 'demo@example.test',
                'phone' => '+639175550100',
            ],
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
                ->count();
            if ($waiting === count($workers)) {
                return;
            }

            foreach ($workers as $worker) {
                $this->assertTrue($worker->isRunning(), $worker->getErrorOutput().$worker->getOutput());
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        $this->fail('The booking requests did not wait on the vehicle lock.');
    }

    private function bookingResponse(Process $worker): array
    {
        $this->assertSame(0, $worker->wait(), $worker->getErrorOutput());

        return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
