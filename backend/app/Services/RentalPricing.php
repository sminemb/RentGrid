<?php

namespace App\Services;

use Illuminate\Contracts\Config\Repository;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

final class RentalPricing
{
    public function __construct(private readonly Repository $config) {}

    public function termsFor(RentalPeriod $period): array
    {
        $settings = $this->config->get('rentgrid.booking');
        $location = trim((string) $settings['pickup_location']);
        $currency = strtoupper(trim((string) $settings['currency']));
        $rate = $this->minorUnits($settings['daily_rate_minor']);
        $fee = $this->minorUnits($settings['fee_minor']);
        $deposit = $this->minorUnits($settings['deposit_minor']);

        if ($location === '' || mb_strlen($location) > 255 || ! preg_match('/\A[A-Z]{3}\z/', $currency)) {
            throw $this->unavailable();
        }

        $days = $period->days();
        $rental = $rate * $days;

        return [
            'pickup_location' => $location,
            'currency' => $currency,
            'days' => $days,
            'daily_rate_minor' => $rate,
            'rental_minor' => $rental,
            'fee_minor' => $fee,
            'deposit_minor' => $deposit,
            'amount_due_minor' => $rental + $fee + $deposit,
        ];
    }

    private function minorUnits(mixed $value): int
    {
        $amount = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 999_999_999],
        ]);

        if ($amount === false) {
            throw $this->unavailable();
        }

        return $amount;
    }

    private function unavailable(): ServiceUnavailableHttpException
    {
        return new ServiceUnavailableHttpException(null, 'Rental quotes are temporarily unavailable. Please try again later.');
    }
}
