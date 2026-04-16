<?php

use Illuminate\Support\Facades\Route;

// Normalize accidental SPA URLs prefixed with /build.
// This keeps route links on app paths like /inventory instead of /build/inventory.
Route::get('/build/{any?}', function ($any = null) {
    $target = '/' . ltrim((string) $any, '/');
    return redirect($target === '/' ? '/' : $target);
})->where('any', '.*');

// Catch-all route for Vue.js SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

// Load API routes
Route::middleware('api')
    ->prefix('api')
    ->group(base_path('routes/api.php'));
