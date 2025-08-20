<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for different API endpoints
     */
    protected function configureRateLimiting(): void
    {
        // Authentication rate limits
        RateLimiter::for('auth:login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many login attempts. Please try again later.',
                        'retry_after' => 60
                    ], 429);
                });
        });

        RateLimiter::for('auth:register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many registration attempts. Please try again later.',
                        'retry_after' => 60
                    ], 429);
                });
        });

        RateLimiter::for('auth:forgot-password', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many password reset attempts. Please try again in 5 minutes.',
                        'retry_after' => 300
                    ], 429);
                });
        });

        RateLimiter::for('auth:change-password', function (Request $request) {
            return Limit::perMinutes(5, 3)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many password change attempts. Please try again in 5 minutes.',
                        'retry_after' => 300
                    ], 429);
                });
        });

        // General API rate limits
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            $limit = $request->user() ? 60 : 20; // 60 for authenticated, 20 for guests
            
            return Limit::perMinute($limit)->by($key)
                ->response(function () use ($limit) {
                    return response()->json([
                        'message' => "Rate limit exceeded. Maximum {$limit} requests per minute.",
                        'retry_after' => 60
                    ], 429);
                });
        });

        // RBAC operation rate limits
        RateLimiter::for('rbac:sensitive', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many role/permission operations. Please slow down.',
                        'retry_after' => 60
                    ], 429);
                });
        });

        // Super admin operations (more permissive)
        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Admin rate limit exceeded. Please slow down.',
                        'retry_after' => 60
                    ], 429);
                });
        });
    }
}
