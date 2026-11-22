<?php

namespace Tests\Feature;

use Tests\TestCase;

class StaffSignInRateLimitTest extends TestCase
{
    public function test_clients_of_a_trusted_proxy_have_separate_sign_in_limits(): void
    {
        $original = $_ENV['TRUSTED_PROXY_IPS'] ?? null;
        $_ENV['TRUSTED_PROXY_IPS'] = '192.0.2.10';
        try {
            $this->refreshApplication();
        } finally {
            if ($original === null) {
                unset($_ENV['TRUSTED_PROXY_IPS']);
            } else {
                $_ENV['TRUSTED_PROXY_IPS'] = $original;
            }
        }
        $proxy = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $proxy->postJson('/api/staff/sign-in', ['email' => "staff{$attempt}@example.test"], [
                'X-Forwarded-For' => '198.51.100.10',
            ])->assertUnprocessable();
        }

        $proxy->postJson('/api/staff/sign-in', ['email' => 'staff@example.test'], [
            'X-Forwarded-For' => '198.51.100.10',
        ])->assertTooManyRequests();
        $proxy->postJson('/api/staff/sign-in', ['email' => 'staff@example.test'], [
            'X-Forwarded-For' => '198.51.100.11',
        ])->assertUnprocessable();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
            ->postJson('/api/staff/sign-in', ['email' => 'staff@example.test'], [
                'X-Forwarded-For' => '198.51.100.10',
            ])->assertUnprocessable();
    }
}
