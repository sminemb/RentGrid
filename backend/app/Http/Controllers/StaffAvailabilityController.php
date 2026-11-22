<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityBlock;
use App\Models\Reservation;
use App\Models\ReservationStatus;
use App\Models\Vehicle;
use App\Services\AvailabilityService;
use App\Services\RentalPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class StaffAvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function index(Vehicle $vehicle): JsonResponse
    {
        $today = $this->availability->businessToday();
        $bookings = $vehicle->reservations()
            ->where('status', ReservationStatus::Confirmed->value)
            ->where('return_date', '>=', $today)
            ->orderBy('pickup_date')
            ->get(['pickup_date', 'return_date'])
            ->map(fn (Reservation $booking) => [
                'starts_on' => $booking->pickup_date->toDateString(),
                'ends_on' => $booking->return_date->toDateString(),
            ]);
        $blocks = $vehicle->availabilityBlocks()
            ->orderBy('starts_on')->orderBy('id')->get()
            ->map($this->blockData(...));

        return response()->json([
            'today' => $today,
            'blocks' => $blocks,
            'bookings' => $bookings,
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $today = $this->availability->businessToday();
        $dates = $request->validate([
            'starts_on' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'ends_on' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);
        $period = RentalPeriod::fromDates($dates['starts_on'], $dates['ends_on'], $today);
        $block = $this->availability->addBlock($vehicle->id, $period);
        if ($block === null) {
            return response()->json([
                'code' => 'confirmed_booking_overlap',
                'message' => 'These dates overlap a confirmed booking. Choose a different date range.',
            ], 409);
        }

        return response()->json(
            ['data' => $this->blockData($block)],
            $block->wasRecentlyCreated ? 201 : 200,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    public function destroy(Vehicle $vehicle, string $block): Response
    {
        $this->availability->removeBlock($vehicle->id, (int) $block);

        return response()->noContent();
    }

    private function blockData(AvailabilityBlock $block): array
    {
        return [
            'id' => $block->id,
            'starts_on' => $block->starts_on->toDateString(),
            'ends_on' => $block->ends_on->toDateString(),
        ];
    }
}
