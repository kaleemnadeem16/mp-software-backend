<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
| Route organization follows modular structure defined in GENERAL_RULES.md
|
*/

Route::prefix('v1')->group(function () {
    // API Info & Health Check
    Route::get('/', function () {
        return response()->json([
            'api' => 'MP Software Backend API',
            'version' => '1.0.0',
            'status' => 'active',
            'timestamp' => now()->toISOString()
        ]);
    });

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'MP-Software API',
            'version' => 'v1',
            'timestamp' => now()->toISOString(),
            'database' => 'connected' // You can add DB health check here
        ]);
    });

    // Load modular route files
    require __DIR__ . '/api/v1/auth.php';
    require __DIR__ . '/api/v1/rbac.php';
    
    // Future route modules will be added here:
    // require __DIR__ . '/api/v1/users.php';
    // require __DIR__ . '/api/v1/projects.php';
    // require __DIR__ . '/api/v1/reports.php';
});