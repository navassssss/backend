<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TelescopeAuthController;

// Route::get('/system/optimize', [SystemController::class, 'optimize']);

// Telescope Login Routes
Route::get('/telescopelogin', [TelescopeAuthController::class, 'showLoginForm'])->name('telescope.login');
Route::post('/telescopelogin', [TelescopeAuthController::class, 'login']);
Route::post('/telescopelogout', [TelescopeAuthController::class, 'logout'])->name('telescope.logout');

Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');

