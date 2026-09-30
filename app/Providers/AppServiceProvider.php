<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(User::class, UserPolicy::class);
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login-account:'.hash('sha256', mb_strtolower(trim((string) $request->input('email'))).'|'.$request->ip())),
            Limit::perMinute(30)->by('login-ip:'.$request->ip()),
        ]);
        RateLimiter::for('auth-sensitive', fn (Request $request) => Limit::perMinute(5)->by('auth:'.$request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by('api:'.($request->user()?->getKey() ?? $request->ip())));
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('commerce.reset_url').'?'.http_build_query([
            'token' => $token, 'email' => $user->email,
        ]));
    }
}
