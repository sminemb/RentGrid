<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, BookingService $bookings): JsonResponse
    {
        $input = $request->validated();
        $reservation = $bookings->confirm(
            $input['quote_token'],
            $request->session()->getId(),
            $input['name'],
            $input['email'],
            $input['phone'],
        );

        return response()->json([
            'reference' => $reservation->reference,
            'status' => $reservation->status->value,
            'vehicle_id' => $reservation->vehicle_id,
            'pickup_date' => $reservation->pickup_date->toDateString(),
            'return_date' => $reservation->return_date->toDateString(),
            'pickup_location' => $reservation->pickup_location,
            'currency' => $reservation->currency,
            'amount_due_minor' => $reservation->amount_due_minor,
        ], 201, ['Cache-Control' => 'private, no-store']);
    }
}
