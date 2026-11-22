<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class BookingConflict extends RuntimeException
{
    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function unavailable(): self
    {
        return new self('vehicle_unavailable', 'This vehicle is no longer available for your dates.');
    }

    public static function priceChanged(): self
    {
        return new self('price_changed', 'The rental price or pickup details changed. Review the updated quote before booking.');
    }

    public function render(): JsonResponse
    {
        return response()->json(
            ['code' => $this->reason, 'message' => $this->getMessage()],
            409,
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
