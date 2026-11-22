<?php

namespace App\Http\Controllers;

use App\Services\BookingQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingQuoteController extends Controller
{
    public function __invoke(Request $request, BookingQuoteService $quotes): JsonResponse
    {
        $selection = $request->validate([
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'pickup_date' => ['required', 'string'],
            'return_date' => ['required', 'string'],
        ]);

        $quote = $quotes->review(
            (int) $selection['vehicle_id'],
            $selection['pickup_date'],
            $selection['return_date'],
            $request->session(),
        );

        return response()->json($quote, headers: ['Cache-Control' => 'private, no-store']);
    }
}
