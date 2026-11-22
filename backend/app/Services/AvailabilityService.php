<?php

namespace App\Services;

use App\Models\ReservationStatus;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;

class AvailabilityService
{
    /** @return Builder<Vehicle> */
    public function availableVehicles(string $pickupDate, string $returnDate): Builder
    {
        $period = RentalPeriod::fromDates(
            $pickupDate,
            $returnDate,
            today(config('rentgrid.business_timezone'))->toDateString(),
        );

        return Vehicle::query()
            ->where('is_published', true)
            ->whereDoesntHave('reservations', function (Builder $query) use ($period) {
                $query->where('status', ReservationStatus::Confirmed->value);
                $this->applyOverlap($query, $period, 'pickup_date', 'return_date');
            })
            ->whereDoesntHave('availabilityBlocks', function (Builder $query) use ($period) {
                $this->applyOverlap($query, $period, 'starts_on', 'ends_on');
            });
    }

    private function applyOverlap(Builder $query, RentalPeriod $period, string $startColumn, string $endColumn): void
    {
        $query->where($startColumn, '<=', $period->returnDate)
            ->where($endColumn, '>=', $period->pickupDate);
    }
}
