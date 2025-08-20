<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check Role Middleware
 * 
 * Validates that the authenticated user has the required role(s)
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$roles
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'timestamp' => now()->toISOString()
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $request->user();

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Insufficient permissions. Required role(s): ' . implode(', ', $roles),
            'user_roles' => $user->getRoleNames(),
            'timestamp' => now()->toISOString()
        ], Response::HTTP_FORBIDDEN);
    }
}