<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Validation\ValidationException;

final readonly class RentalPeriod
{
    private function __construct(
        public string $pickupDate,
        public string $returnDate,
    ) {}

    public static function fromDates(string $pickupDate, string $returnDate, string $businessToday): self
    {
        self::validateDate($pickupDate, 'pickup_date', 'pickup');
        self::validateDate($returnDate, 'return_date', 'return');

        if ($pickupDate < $businessToday) {
            throw ValidationException::withMessages(['pickup_date' => 'Choose a pickup date of today or later.']);
        }

        if ($returnDate < $pickupDate) {
            throw ValidationException::withMessages(['return_date' => 'Choose a return date on or after pickup.']);
        }

        return new self($pickupDate, $returnDate);
    }

    public function days(): int
    {
        $pickup = new DateTimeImmutable($this->pickupDate.' UTC');
        $return = new DateTimeImmutable($this->returnDate.' UTC');

        return (int) $pickup->diff($return)->format('%a') + 1;
    }

    private static function validateDate(string $date, string $field, string $label): void
    {
        $valid = preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $date, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);

        if (! $valid) {
            throw ValidationException::withMessages([$field => "Enter a valid {$label} date in YYYY-MM-DD format."]);
        }
    }
}
