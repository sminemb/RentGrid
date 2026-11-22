<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Reservation;
use App\Services\BookingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, BookingService $bookings): JsonResponse
    {
        $input = $request->validated();
        try {
            $reservation = $bookings->confirm(
                $input['quote_token'],
                $request->session(),
                $input['name'],
                $input['email'],
                $input['phone'],
            );
        } catch (QueryException $exception) {
            Log::error('Booking confirmation failed.', ['sqlstate' => $exception->getCode()]);

            return response()->json(['message' => 'Unable to confirm this booking.'], 500, ['Cache-Control' => 'private, no-store']);
        }

        return $this->confirmation($reservation, $reservation->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, string $reference, BookingService $bookings): JsonResponse
    {
        $reservation = $bookings->confirmation($reference, $request->session());

        if (! $reservation) {
            return response()->json(['message' => 'Confirmation unavailable in this session.'], 404, ['Cache-Control' => 'private, no-store']);
        }

        return $this->confirmation($reservation, 200);
    }

    private function confirmation(Reservation $reservation, int $status): JsonResponse
    {
        return response()->json([
            'reference' => $reservation->reference,
            'status' => $reservation->status->value,
            'vehicle_id' => $reservation->vehicle_id,
            'vehicle' => new VehicleResource($reservation->vehicle),
            'pickup_date' => $reservation->pickup_date->toDateString(),
            'return_date' => $reservation->return_date->toDateString(),
            'pickup_location' => $reservation->pickup_location,
            'pickup_instructions' => $reservation->pickup_instructions,
            'cancellation_terms' => $reservation->cancellation_terms,
            'currency' => $reservation->currency,
            'amount_due_minor' => $reservation->amount_due_minor,
        ], $status, ['Cache-Control' => 'private, no-store']);
    }
}
