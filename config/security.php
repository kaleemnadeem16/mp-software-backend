<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security settings for the MP-Software API including
    | rate limiting, security headers, and other security policies.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Define rate limits for different types of API operations to prevent
    | abuse and ensure fair usage of the API.
    |
    */
    'rate_limits' => [
        // Authentication endpoints
        'auth' => [
            'login' => '5,1',           // 5 attempts per minute
            'register' => '3,1',        // 3 attempts per minute
            'forgot_password' => '3,5', // 3 attempts per 5 minutes
            'reset_password' => '3,5',  // 3 attempts per 5 minutes
        ],

        // General API access
        'api' => [
            'general' => '60,1',        // 60 requests per minute for authenticated users
            'guest' => '20,1',          // 20 requests per minute for unauthenticated users
        ],

        // Sensitive operations
        'sensitive' => [
            'password_change' => '3,5', // 3 password changes per 5 minutes
            'role_assignment' => '10,1', // 10 role assignments per minute
            'permission_changes' => '20,1', // 20 permission changes per minute
        ],

        // RBAC operations
        'rbac' => [
            'role_creation' => '5,1',   // 5 role creations per minute
            'permission_creation' => '10,1', // 10 permission creations per minute
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Configure security headers that should be included in all API responses
    | to enhance security and prevent common attacks.
    |
    */
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'",
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTPS Configuration
    |--------------------------------------------------------------------------
    |
    | Configure HTTPS enforcement and related security policies.
    |
    */
    'https' => [
        'enforce' => env('ENFORCE_HTTPS', false),
        'hsts_max_age' => 31536000, // 1 year
        'include_subdomains' => true,
        'preload' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Security
    |--------------------------------------------------------------------------
    |
    | Configure API token security settings including expiration and
    | refresh policies.
    |
    */
    'tokens' => [
        'expiration' => env('SANCTUM_TOKEN_EXPIRES_IN', 1440), // 24 hours in minutes
        'max_per_user' => 5, // Maximum tokens per user
        'auto_revoke_old' => true, // Auto-revoke old tokens when limit reached
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for common security-sensitive fields.
    |
    */
    'validation' => [
        'password' => [
            'min_length' => 8,
            'max_length' => 64,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_symbols' => false,
        ],
        'email' => [
            'max_length' => 255,
            'require_verification' => env('REQUIRE_EMAIL_VERIFICATION', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configure what events should be logged for security auditing.
    |
    */
    'audit' => [
        'enabled' => env('AUDIT_LOGGING_ENABLED', true),
        'events' => [
            'login_attempts',
            'role_changes',
            'permission_changes',
            'password_changes',
            'admin_actions',
        ],
        'store_ip' => true,
        'store_user_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Attempt Tracking
    |--------------------------------------------------------------------------
    |
    | Track and respond to failed authentication attempts.
    |
    */
    'failed_attempts' => [
        'enabled' => true,
        'max_attempts' => 5,
        'lockout_duration' => 300, // 5 minutes in seconds
        'track_by_ip' => true,
        'track_by_email' => true,
    ],
];