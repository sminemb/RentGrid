<?php

namespace Tests\Feature;

use App\Models\ReservationStatus;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentgrid.business_timezone' => 'Asia/Manila']);
        $this->travelTo(Carbon::parse('2026-06-01 00:00:00', 'UTC'));
    }

    public function test_only_published_vehicles_without_overlaps_are_available(): void
    {
        $reserved = $this->publishedVehicle();
        $this->reserve($reserved, '2026-06-10', '2026-06-12');

        $cancelled = $this->publishedVehicle();
        $this->reserve($cancelled, '2026-06-10', '2026-06-12', ReservationStatus::Cancelled);

        $blocked = $this->publishedVehicle();
        $blocked->availabilityBlocks()->create([
            'starts_on' => '2026-06-11',
            'ends_on' => '2026-06-12',
        ]);

        Vehicle::factory()->create();

        $this->assertSame([$cancelled->id], $this->availableIds('2026-06-09', '2026-06-13'));
        $this->assertSame([$cancelled->id, $blocked->id], $this->availableIds('2026-06-10', '2026-06-10'));
    }

    public function test_a_reservation_occupies_both_pickup_and_return_days(): void
    {
        $vehicle = $this->publishedVehicle();
        $this->reserve($vehicle, '2026-06-10', '2026-06-12');

        $this->assertSame([$vehicle->id], $this->availableIds('2026-06-09', '2026-06-09'));
        $this->assertSame([], $this->availableIds('2026-06-09', '2026-06-10'));
        $this->assertSame([], $this->availableIds('2026-06-12', '2026-06-12'));
        $this->assertSame([$vehicle->id], $this->availableIds('2026-06-13', '2026-06-13'));
    }

    public function test_a_same_day_reservation_occupies_one_calendar_day(): void
    {
        $vehicle = $this->publishedVehicle();
        $this->reserve($vehicle, '2026-06-10', '2026-06-10');

        $this->assertSame([$vehicle->id], $this->availableIds('2026-06-09', '2026-06-09'));
        $this->assertSame([], $this->availableIds('2026-06-10', '2026-06-10'));
        $this->assertSame([$vehicle->id], $this->availableIds('2026-06-11', '2026-06-11'));
    }

    public function test_a_block_occupies_its_end_date(): void
    {
        $vehicle = $this->publishedVehicle();
        $vehicle->availabilityBlocks()->create([
            'starts_on' => '2026-06-10',
            'ends_on' => '2026-06-12',
        ]);

        $this->assertSame([], $this->availableIds('2026-06-12', '2026-06-12'));
        $this->assertSame([$vehicle->id], $this->availableIds('2026-06-13', '2026-06-13'));
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_are_rejected(string $pickup, string $return, string $field): void
    {
        try {
            $this->availableIds($pickup, $return);
            $this->fail('Expected the dates to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public static function invalidDates(): array
    {
        return [
            'past' => ['2026-05-31', '2026-06-01', 'pickup_date'],
            'reversed' => ['2026-06-12', '2026-06-10', 'return_date'],
            'wrong format' => ['2026-6-10', '2026-06-12', 'pickup_date'],
            'invalid calendar day' => ['2026-06-10', '2026-06-31', 'return_date'],
        ];
    }

    public function test_today_is_calculated_in_the_business_time_zone(): void
    {
        $vehicle = $this->publishedVehicle();
        $this->travelTo(Carbon::parse('2026-06-01 16:30:00', 'UTC'));

        $this->assertSame([$vehicle->id], $this->availableIds('2026-06-02', '2026-06-02'));
        $this->expectException(ValidationException::class);
        $this->availableIds('2026-06-01', '2026-06-02');
    }

    private function availableIds(string $pickup, string $return): array
    {
        return app(AvailabilityService::class)
            ->availableVehicles($pickup, $return)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    private function publishedVehicle(): Vehicle
    {
        return Vehicle::factory()->create(['is_published' => true]);
    }

    private function reserve(
        Vehicle $vehicle,
        string $pickup,
        string $return,
        ReservationStatus $status = ReservationStatus::Confirmed,
    ): void {
        $vehicle->reservations()->create([
            'pickup_date' => $pickup,
            'return_date' => $return,
            'status' => $status,
        ]);
    }
}
