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

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'auth/*'],

    'allowed_methods' => ['*'],

    /*
     * In development, allow all origins for flexibility.
     * In production, restrict to specific domains.
     */
    'allowed_origins' => [
        // Development origins
        'http://localhost:8081',
        'http://localhost:19006',
        'http://localhost:3000',
        'http://192.168.1.4:8000',
        'http://192.168.1.4:8081',
        'http://192.168.1.4:19006',
        // Expo development URLs
        'exp://192.168.1.4:8081',
        'exp://192.168.1.4:19000',
        // Allow all origins in development (safer than listing every possible origin)
        // Comment the line below and uncomment the specific origins above for production
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    /*
     * Cache preflight responses for 24 hours to reduce OPTIONS requests.
     * This improves performance significantly.
     */
    'max_age' => 86400,

    /*
     * Enable credentials support for authentication cookies/tokens.
     * Required for Sanctum SPA authentication if used.
     */
    'supports_credentials' => true,

];