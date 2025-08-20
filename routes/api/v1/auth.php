<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
|
| All authentication related routes including registration, login,
| logout, profile management, and password operations.
|
*/

Route::prefix('auth')->group(function () {
    // Public authentication routes with rate limiting
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:auth:register')
        ->name('auth.register');
        
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:auth:login')
        ->name('auth.login');
        
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:auth:forgot-password')
        ->name('auth.forgot-password');
        
    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:auth:forgot-password')
        ->name('auth.reset-password');

    // Protected authentication routes with general API rate limiting
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::get('profile', [AuthController::class, 'profile'])->name('auth.profile');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('auth.update-profile');
        
        Route::post('change-password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:auth:change-password')
            ->name('auth.change-password');
    });
});