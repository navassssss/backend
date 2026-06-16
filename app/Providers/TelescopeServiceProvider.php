<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return true; // Capture all request logs, queries, and errors in all environments
        });

        // Tag each entry with the authenticated user's name and role so they
        // appear inline in the Telescope list — no need to open the detail page.
        Telescope::tag(function (IncomingEntry $entry) {
            if (auth()->check()) {
                $user = auth()->user();
                $name = $user->name ?? $user->email ?? 'Unknown';
                $role = $user->role ?? null;

                return $role
                    ? ["{$name}", "role:{$role}"]
                    : ["{$name}"];
            }

            return ['guest'];
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null) {
            // Authorization is handled via a custom session-based password login.
            // The session key is set by TelescopeAuthController upon successful login.
            return session('telescope_authenticated') === true;
        });
    }
}
