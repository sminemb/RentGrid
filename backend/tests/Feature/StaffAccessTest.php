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
            ->assertExactJson(['name' => $staff->name, 'can_create_staff' => false]);

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

    public function test_provisioned_staff_can_sign_in_with_a_password_that_looks_like_a_bcrypt_hash(): void
    {
        $email = 'new.staff@example.test';
        $password = password_hash('synthetic staff password', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->artisan('rentgrid:staff-create', ['email' => $email, 'name' => 'New Staff'])
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();

        $user = User::query()->where('email', $email)->firstOrFail();
        $this->assertTrue($user->is_staff);
        $this->assertFalse($user->canCreateStaff());
        $this->postJson('/api/staff/sign-in', ['email' => $email, 'password' => $password])
            ->assertNoContent();
        $this->assertAuthenticatedAs($user);
    }

    public function test_only_staff_administrators_can_create_staff_accounts(): void
    {
        $this->postJson('/api/staff/accounts')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/api/staff/accounts')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_staff' => true]))
            ->postJson('/api/staff/accounts')->assertForbidden();
    }

    public function test_a_provisioned_staff_administrator_can_create_a_staff_account(): void
    {
        $password = 'synthetic staff password';
        $this->artisan('rentgrid:staff-create', [
            'email' => 'administrator@example.test', 'name' => 'Administrator', '--admin' => true,
        ])->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertSuccessful();
        $admin = User::query()->where('email', 'administrator@example.test')->firstOrFail();
        $this->actingAs($admin)->getJson('/api/staff/session')->assertJsonPath('can_create_staff', true);

        $account = [
            'name' => ' New Staff ',
            'email' => ' New.Staff@Example.Test ',
            'password' => $password,
            'password_confirmation' => $password,
            'is_staff' => false,
            'is_staff_admin' => true,
        ];
        $this->postJson('/api/staff/accounts', $account)->assertCreated();
        $staff = User::query()->where('email', 'new.staff@example.test')->firstOrFail();
        $this->assertSame('New Staff', $staff->name);
        $this->assertTrue($staff->hasStaffAccess());
        $this->assertFalse($staff->is_staff_admin);
        $this->assertTrue(Hash::check($password, $staff->password));

        $this->postJson('/api/staff/accounts', $account)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_staff_creation_rejects_invalid_passwords(): void
    {
        $this->actingAs(User::factory()->create(['is_staff' => true, 'is_staff_admin' => true]));
        $account = ['name' => 'New Staff', 'email' => 'new.staff@example.test'];

        foreach (['too short', str_repeat('é', 37), str_repeat('a', 12)."\0"] as $password) {
            $this->postJson('/api/staff/accounts', $account + [
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
        }

        $this->postJson('/api/staff/accounts', $account + [
            'password' => 'synthetic staff password',
            'password_confirmation' => 'different password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password_confirmation');
        $this->assertDatabaseMissing('users', ['email' => $account['email']]);
    }

    private function useSession(TestResponse $response): static
    {
        $cookie = config('session.cookie');

        return $this->withCredentials()->withUnencryptedCookie($cookie, $response->getCookie($cookie, false)->getValue());
    }
}
