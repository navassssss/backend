<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthorizeTelescope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (session('telescope_authenticated') !== true) {
            return redirect()->route('telescope.login');
        }

        return $next($request);
    }
}
