<?php

namespace App\Providers;

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

    public function boot(): void
    {
        \Illuminate\Support\Carbon::macro('inUserTimezone', function () {
            /** @var \Illuminate\Support\Carbon $this */
            return $this->setTimezone(auth()->user()?->timezone ?? config('app.timezone', 'UTC'));
        });
    }
}
