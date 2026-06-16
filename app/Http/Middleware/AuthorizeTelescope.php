<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthorizeTelescope
{
    /**
     * Redirect unauthenticated browser visits to the Telescope dashboard
     * to the dedicated login page.
     *
     * This middleware is applied ONLY to the /telescope web route,
     * NOT to Telescope's internal telescope-api/* routes.
     */
    public function handle(Request $request, Closure $next)
    {
        if (session('telescope_authenticated') !== true) {
            return redirect()->route('telescope.login');
        }

        return $next($request);
    }
}
