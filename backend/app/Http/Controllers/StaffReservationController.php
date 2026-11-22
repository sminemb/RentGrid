<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;

final class StaffReservationController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function show(string $reference): JsonResponse
    {
        return $this->reservationResponse($this->findReservation($reference));
    }

    public function cancel(string $reference): JsonResponse
    {
        $reservation = $this->bookings->cancel($this->findReservation($reference));

        return $this->reservationResponse($reservation);
    }

    private function findReservation(string $reference): Reservation
    {
        return Reservation::query()->where('reference', $reference)->first()
            ?? abort(404, 'No reservation matches that booking reference.');
    }

    private function reservationResponse(Reservation $reservation): JsonResponse
    {
        return response()->json(['data' => [
            'reference' => $reservation->reference,
            'status' => $reservation->status->value,
            'vehicle' => $reservation->confirmedVehicle(),
            'pickup_date' => $reservation->pickup_date->toDateString(),
            'return_date' => $reservation->return_date->toDateString(),
            'guest' => [
                'name' => $reservation->guest_name,
                'email' => $reservation->guest_email,
                'phone' => $reservation->guest_phone,
            ],
        ]], headers: ['Cache-Control' => 'private, no-store']);
    }
}
