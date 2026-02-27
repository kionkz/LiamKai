<?php

use Illuminate\Support\Facades\Route;

// Catch-all route for Vue.js SPA
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

// Load API routes
Route::middleware('api')
    ->prefix('api')
    ->group(base_path('routes/api.php'));
