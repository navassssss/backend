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
        \Illuminate\Support\Facades\Gate::define('viewWebTinker', function ($user = null) {
            // WARNING: For production, you MUST change this to a secure check!
            // e.g., return $user && $user->email === 'your-email@example.com';
            // Returning true here allows ANYONE to access the tinker route. 
            // In a real environment with no SSH, you might want to uncomment this 
            // temporarily, or use an env variable check.
            return env('APP_ENV') === 'local' || env('ALLOW_WEB_TINKER') === true; 
        });

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
