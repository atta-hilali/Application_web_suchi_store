<?php

namespace App\Providers;

use App\Models\Order;
use App\Policies\OrderPolicy;
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
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('account:'.hash('sha256', Str::lower((string) $request->input('email')).'|'.$request->ip())),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('quotes', fn (Request $request) => Limit::perMinute(60)->by($request->user()->id));
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)->by($request->user()->id));
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::define('access-admin', fn ($user) => $user->isAdmin());
    }
}
