<?php

namespace App\Services;

use App\Models\AvailabilityBlock;
use App\Models\ReservationStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    /** @return Builder<Vehicle> */
    public function availableVehicles(string $pickupDate, string $returnDate): Builder
    {
        return $this->vehiclesAvailableFor($this->period($pickupDate, $returnDate));
    }

    public function period(string $pickupDate, string $returnDate): RentalPeriod
    {
        $today = $this->businessToday();
        $period = RentalPeriod::fromDates($pickupDate, $returnDate, $today);
        $period->validateBookingLimits(
            $today,
            max(1, (int) config('rentgrid.max_rental_days')),
            max(0, (int) config('rentgrid.max_advance_days')),
        );

        return $period;
    }

    public function businessToday(): string
    {
        return today(config('rentgrid.business_timezone'))->toDateString();
    }

    /** @return Builder<Vehicle> */
    public function vehiclesAvailableFor(RentalPeriod $period): Builder
    {
        return Vehicle::query()
            ->where('is_published', true)
            ->whereDoesntHave('reservations', function (Builder $query) use ($period) {
                $this->applyConfirmedOverlap($query, $period);
            })
            ->whereDoesntHave('availabilityBlocks', function (Builder $query) use ($period) {
                $this->applyOverlap($query, $period, 'starts_on', 'ends_on');
            });
    }

    public function addBlock(int $vehicleId, RentalPeriod $period): ?AvailabilityBlock
    {
        return DB::transaction(function () use ($vehicleId, $period): ?AvailabilityBlock {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
            $bookings = $vehicle->reservations()->getQuery();
            $this->applyConfirmedOverlap($bookings, $period);
            if ($bookings->exists()) {
                return null;
            }

            return $vehicle->availabilityBlocks()->firstOrCreate([
                'starts_on' => $period->pickupDate,
                'ends_on' => $period->returnDate,
            ]);
        });
    }

    public function removeBlock(int $vehicleId, int $blockId): void
    {
        DB::transaction(function () use ($vehicleId, $blockId): void {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
            $vehicle->availabilityBlocks()->findOrFail($blockId)->delete();
        });
    }

    private function applyConfirmedOverlap(Builder $query, RentalPeriod $period): void
    {
        $query->where('status', ReservationStatus::Confirmed->value);
        $this->applyOverlap($query, $period, 'pickup_date', 'return_date');
    }

    private function applyOverlap(Builder $query, RentalPeriod $period, string $startColumn, string $endColumn): void
    {
        $query->where($startColumn, '<=', $period->returnDate)
            ->where($endColumn, '>=', $period->pickupDate);
    }
}
