<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationStatus;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingQuoteService $quotes,
        private readonly RentalPricing $pricing,
    ) {}

    public function confirm(string $quoteToken, string $sessionId, string $name, string $email, string $phone): Reservation
    {
        $quote = $this->quotes->read($quoteToken, $sessionId);

        return DB::transaction(function () use ($quote, $name, $email, $phone): Reservation {
            $vehicle = Vehicle::query()->lockForUpdate()->find($quote['vehicle_id'])
                ?? throw BookingConflict::unavailable();

            // Under Read Committed this query sees bookings committed by the previous lock holder.
            $period = $this->availability->period($quote['pickup_date'], $quote['return_date']);
            if (! $this->availability->vehiclesAvailableFor($period)->whereKey($vehicle->id)->exists()) {
                throw BookingConflict::unavailable();
            }

            $terms = $this->pricing->termsFor($period);
            if ($terms !== $quote['terms']) {
                throw BookingConflict::priceChanged();
            }

            return $vehicle->reservations()->create([
                'pickup_date' => $period->pickupDate,
                'return_date' => $period->returnDate,
                'status' => ReservationStatus::Confirmed,
                'reference' => 'RG-'.Str::ulid(),
                'guest_name' => $name,
                'guest_email' => $email,
                'guest_phone' => $phone,
                'pickup_location' => $terms['pickup_location'],
                'currency' => $terms['currency'],
                'amount_due_minor' => $terms['amount_due_minor'],
            ]);
        });
    }
}
