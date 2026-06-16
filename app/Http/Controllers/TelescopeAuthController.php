<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TelescopeAuthController extends Controller
{
    /**
     * Show the custom login form.
     */
    public function showLoginForm()
    {
        if (session('telescope_authenticated') === true) {
            return redirect('/telescope');
        }

        return view('telescope-login');
    }

    /**
     * Authenticate the telescope session.
     */
    public function login(Request $request)
    {
        $password = env('TELESCOPE_PASSWORD');

        if (empty($password)) {
            return back()->withErrors([
                'password' => 'Telescope password is not configured in .env file (TELESCOPE_PASSWORD).',
            ]);
        }

        $request->validate([
            'password' => 'required|string',
        ]);

        if ($request->input('password') === $password) {
            session(['telescope_authenticated' => true]);
            return redirect('/telescope');
        }

        return back()->withErrors([
            'password' => 'The password you entered is incorrect.',
        ]);
    }

    /**
     * Clear the telescope session and logout.
     */
    public function logout()
    {
        session()->forget('telescope_authenticated');
        return redirect()->route('telescope.login');
    }
}
