<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('coupon-validation', fn (Request $request): array => [
            Limit::perMinute(10)->by('coupon-session:'.hash('sha256', $request->session()->getId())),
            Limit::perMinute(10)->by('coupon-ip:'.hash('sha256', (string) $request->ip())),
        ]);
    }
}
