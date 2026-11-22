<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FleetService
{
    /** @param array{display_label: string, make: string, model: string} $details */
    public function saveDetails(Vehicle $vehicle, array $details): Vehicle
    {
        try {
            DB::transaction(fn () => $vehicle->fill($details)->save());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'display_label' => 'Choose a display label that identifies this vehicle uniquely.',
            ]);
        }

        return $vehicle->refresh();
    }

    public function setPublication(int $vehicleId, bool $published): Vehicle
    {
        return DB::transaction(function () use ($vehicleId, $published): Vehicle {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
            $vehicle->is_published = $published;
            $vehicle->save();

            return $vehicle;
        });
    }
}
