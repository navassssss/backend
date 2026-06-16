<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelescopeAuthController;

// Telescope dedicated login/logout routes (outside /telescope/* namespace)
Route::get('/telescopelogin', [TelescopeAuthController::class, 'showLoginForm'])->name('telescope.login');
Route::post('/telescopelogin', [TelescopeAuthController::class, 'login']);
Route::post('/telescopelogout', [TelescopeAuthController::class, 'logout'])->name('telescope.logout');

// Guard the /telescope dashboard browser entry point with session check.
// Telescope's own internal routes (/telescope/telescope-api/*) are guarded
// separately by the gate() in TelescopeServiceProvider.
Route::get('/telescope', function () {
    return redirect('/telescope/requests');
})->middleware(\App\Http\Middleware\AuthorizeTelescope::class);

// React SPA catch-all
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!telescope).*$');
