<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-staff', fn (User $user): bool => $user->hasStaffAccess());

        RateLimiter::for('staff-sign-in', function (Request $request): array {
            $email = $request->input('email');
            $account = is_string($email) ? Str::lower(trim($email)) : '';
            $ip = $request->ip();

            return [
                Limit::perMinute(30)->by('ip:'.$ip),
                Limit::perMinute(5)->by('account:'.hash('sha256', $account.'|'.$ip)),
            ];
        });
    }
}
