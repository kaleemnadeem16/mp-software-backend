<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'health'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:3000',  // React development server
        'http://localhost:8080',  // Vue development server
        'http://localhost:4200',  // Angular development server
        // Add production frontend URLs here
    ],

    'allowed_origins_patterns' => [
        '/^https?:\/\/localhost(:[0-9]+)?$/',  // Allow all localhost with any port
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [
        'X-Pagination-Current-Page',
        'X-Pagination-Per-Page', 
        'X-Pagination-Total',
    ],

    'max_age' => 86400, // 24 hours

    'supports_credentials' => true,

];
