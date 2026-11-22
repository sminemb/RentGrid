<?php

return [
    'business_timezone' => env('BUSINESS_TIMEZONE', 'Asia/Manila'),
    'booking' => [
        'pickup_location' => env('RENTAL_PICKUP_LOCATION'),
        'currency' => env('RENTAL_CURRENCY'),
        'daily_rate_minor' => env('RENTAL_DAILY_RATE_MINOR'),
        'fee_minor' => env('RENTAL_FEE_MINOR', 0),
        'deposit_minor' => env('RENTAL_DEPOSIT_MINOR', 0),
    ],
];
