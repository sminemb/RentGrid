<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationStatus;
use App\Models\Vehicle;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingQuoteService $quotes,
        private readonly RentalPricing $pricing,
        private readonly Repository $config,
    ) {}

    public function confirm(string $quoteToken, Session $session, string $name, string $email, string $phone): Reservation
    {
        $quote = $this->quotes->read($quoteToken, $session);
        $attemptKey = hash('sha256', $quote['attempt']);

        return DB::transaction(function () use ($quote, $attemptKey, $name, $email, $phone): Reservation {
            $vehicle = Vehicle::query()->lockForUpdate()->find($quote['vehicle_id'])
                ?? throw BookingConflict::unavailable();

            // The same signed attempt always takes this lock before checking for a committed booking.
            $existing = $vehicle->reservations()->where('booking_attempt_key', $attemptKey)->first();
            if ($existing) {
                return $existing;
            }

            $period = $this->availability->period($quote['pickup_date'], $quote['return_date']);
            if (! $this->availability->vehiclesAvailableFor($period)->whereKey($vehicle->id)->exists()) {
                throw BookingConflict::unavailable();
            }

            $vehicleDetails = $quote['vehicle_details'] ?? null;
            if ($vehicle->details() !== $vehicleDetails) {
                throw BookingConflict::vehicleChanged();
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
                'vehicle_details' => $vehicleDetails,
                'pickup_location' => $terms['pickup_location'],
                'pickup_instructions' => $this->config->get('rentgrid.pickup_instructions'),
                'cancellation_terms' => $this->config->get('rentgrid.cancellation_terms'),
                'currency' => $terms['currency'],
                'amount_due_minor' => $terms['amount_due_minor'],
                'booking_attempt_key' => $attemptKey,
                'guest_session_key' => $quote['session'],
            ]);
        });
    }

    public function confirmation(string $reference, Session $session): ?Reservation
    {
        return Reservation::query()
            ->where('reference', $reference)
            ->where('guest_session_key', BookingQuoteService::sessionKey($session))
            ->first();
    }

    public function cancel(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($reservation->vehicle_id);
            $current = $vehicle->reservations()->findOrFail($reservation->id);
            $current->cancel();

            return $current;
        });
    }
}
