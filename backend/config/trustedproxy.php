<?php

return [
    'proxies' => array_values(array_filter(
        array_map(trim(...), explode(',', (string) env('TRUSTED_PROXY_IPS', ''))),
        fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false,
    )),
];
