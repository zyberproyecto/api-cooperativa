<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // Landing
        'http://127.0.0.1:5500',
        'http://localhost:5500',
        // Frontend socios (¡nuevo!)
        'http://127.0.0.1:5501',
        'http://localhost:5501',
        // APIs / Backoffice si hacen fetch
        'http://127.0.0.1:8001',
        'http://localhost:8001',
        'http://127.0.0.1:8003',
        'http://localhost:8003',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],

    'max_age' => 3600,

    // Usamos Bearer, no cookies:
    'supports_credentials' => false,
];