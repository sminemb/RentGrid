<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

final class BookingQuoteService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly RentalPricing $pricing,
        private readonly StringEncrypter $encrypter,
    ) {}

    public function review(int $vehicleId, string $pickupDate, string $returnDate, Session $session): array
    {
        $period = $this->availability->period($pickupDate, $returnDate);
        $vehicle = $this->availability->vehiclesAvailableFor($period)->find($vehicleId)
            ?? throw BookingConflict::unavailable();
        $terms = $this->pricing->termsFor($period);
        $selection = [
            'vehicle_id' => $vehicleId,
            'pickup_date' => $period->pickupDate,
            'return_date' => $period->returnDate,
        ];

        return [
            'vehicle' => $vehicle,
            'pickup_date' => $period->pickupDate,
            'return_date' => $period->returnDate,
            ...$terms,
            'quote_token' => $this->encrypter->encryptString(json_encode([
                ...$selection,
                'terms' => $terms,
                'session' => self::sessionKey($session),
                'attempt' => (string) Str::uuid(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)),
        ];
    }

    public function read(string $token, Session $session): array
    {
        try {
            $quote = json_decode($this->encrypter->decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $quote = null;
        }

        if (! isset($quote['attempt'], $quote['session']) || ! hash_equals($quote['session'], self::sessionKey($session))) {
            throw ValidationException::withMessages(['quote_token' => 'Review this rental again before booking.']);
        }

        return $quote;
    }

    public static function sessionKey(Session $session): string
    {
        // A new session token also denies access if an expired session cookie is presented again.
        return hash('sha256', $session->getId().':'.$session->token());
    }
}
