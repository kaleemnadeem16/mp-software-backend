<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check Permission Middleware
 * 
 * Validates that the authenticated user has the required permission(s)
 */
class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string ...$permissions
     * @return Response
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'timestamp' => now()->toISOString()
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $request->user();

        // Check if user has any of the required permissions
        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Insufficient permissions. Required permission(s): ' . implode(', ', $permissions),
            'user_permissions' => $user->getAllPermissions()->pluck('name'),
            'timestamp' => now()->toISOString()
        ], Response::HTTP_FORBIDDEN);
    }
}