<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Carbon::macro('inUserTimezone', function () {
            /** @var \Illuminate\Support\Carbon $this */
            return $this->setTimezone(auth()->user()?->timezone ?? config('app.timezone', 'UTC'));
        });

        // Add strict processing limits preventing queue workers from spiking CPU limits via rate throttling
        RateLimiter::for('push-notifications', function () {
            return Limit::perMinute(60);
        });
    }
}
