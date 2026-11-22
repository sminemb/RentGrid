<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionTest extends TestCase
{
    public function test_guests_can_start_a_session_and_receive_csrf_cookies(): void
    {
        $response = $this->getJson('/api/session')
            ->assertOk()
            ->assertExactJson(['ready' => true])
            ->assertCookie(config('session.cookie'))
            ->assertCookie('XSRF-TOKEN', encrypted: false);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}
