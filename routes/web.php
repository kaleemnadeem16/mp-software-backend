<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is for web routes. Since this is an API-only backend,
| web routes are minimal. All API routes should be defined in api.php
|
*/

// Health check endpoint for monitoring
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'MP-Software Backend',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString()
    ]);
});
