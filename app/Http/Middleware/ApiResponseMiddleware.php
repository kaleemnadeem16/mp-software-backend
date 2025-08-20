<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Response Middleware
 * 
 * Ensures all API responses follow a consistent format and include
 * proper headers for API consumers.
 */
class ApiResponseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set API headers for all API requests
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        // Add API headers to response if it's a JSON response
        if ($response instanceof JsonResponse) {
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('X-API-Version', 'v1');
            $response->headers->set('X-Service', 'MP-Software Backend');
        }

        return $response;
    }
}