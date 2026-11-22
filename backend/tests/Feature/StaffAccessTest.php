<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_api_rejects_guests_and_users_without_staff_access(): void
    {
        $this->get('/api/staff/session')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/staff/session')->assertForbidden();
    }

    public function test_staff_sign_in_and_sign_out_rotate_the_session_and_csrf_token(): void
    {
        config(['session.driver' => 'database']);
        $staff = User::factory()->create(['is_staff' => true]);
        $guest = $this->getJson('/api/session')->assertOk();
        $guestId = session()->getId();
        $guestToken = session()->token();

        $login = $this->useSession($guest)->postJson('/api/staff/sign-in', [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertNoContent();
        $staffId = session()->getId();
        $staffToken = session()->token();
        $this->assertNotSame($guestId, $staffId);
        $this->assertNotSame($guestToken, $staffToken);
        $this->assertDatabaseMissing('sessions', ['id' => $guestId]);

        $this->app['auth']->forgetGuards();
        $this->useSession($login)->getJson('/api/staff/session')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['name' => $staff->name]);

        $this->useSession($login)->postJson('/api/staff/sign-out')->assertNoContent();
        $this->assertNotSame($staffId, session()->getId());
        $this->assertNotSame($staffToken, session()->token());
        $this->assertDatabaseMissing('sessions', ['id' => $staffId]);
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->useSession($login)->getJson('/api/staff/session')->assertUnauthorized();
    }

    public function test_sign_in_rejects_non_staff_credentials_and_rate_limits_repeated_attempts(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/staff/sign-in', ['email' => $user->email, 'password' => 'password'])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'The email or password is incorrect.');
        }

        $this->assertGuest();
        $this->postJson('/api/staff/sign-in', ['email' => strtoupper($user->email), 'password' => 'password'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_provisioning_creates_a_staff_account_with_a_hashed_password(): void
    {
        $email = 'new.staff@example.test';
        $password = 'synthetic staff password';
        $this->artisan('rentgrid:staff-create', ['email' => $email, 'name' => 'New Staff'])
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($user->is_staff);
        $this->assertTrue(Hash::check($password, $user->password));
    }

    private function useSession(TestResponse $response): static
    {
        $cookie = config('session.cookie');

        return $this->withCredentials()->withUnencryptedCookie($cookie, $response->getCookie($cookie, false)->getValue());
    }
}
