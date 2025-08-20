<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and add security headers to the response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Get security headers from config
        $headers = config('security.headers', []);

        // Add default security headers
        $defaultHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        // Add security headers to response
        foreach ($allHeaders as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Add HTTPS headers if configured
        if (config('security.https.enforce', false) || $request->isSecure()) {
            $hstsMaxAge = config('security.https.hsts_max_age', 31536000);
            $includeSubdomains = config('security.https.include_subdomains', true);
            $preload = config('security.https.preload', false);

            $hstsValue = "max-age={$hstsMaxAge}";
            if ($includeSubdomains) {
                $hstsValue .= '; includeSubDomains';
            }
            if ($preload) {
                $hstsValue .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hstsValue);
        }

        // Add API-specific headers
        if ($request->is('api/*')) {
            $response->headers->set('X-API-Version', 'v1');
            $response->headers->set('X-RateLimit-Remaining', $this->getRateLimitRemaining($request));
        }

        return $response;
    }

    /**
     * Get remaining rate limit for the current request
     */
    private function getRateLimitRemaining(Request $request): int
    {
        // This would normally be calculated based on the current rate limit state
        // For now, return a default value
        return 50;
    }
}
