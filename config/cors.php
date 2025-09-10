<?php 

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Quien llama a esta API: landing, frontend socios, backoffice y api-usuarios
    'allowed_origins' => [
        'http://127.0.0.1:5500',  // landing + frontend socios (Live Server)
        'http://localhost:5500',

        'http://127.0.0.1:8001',  // api-usuarios (puede necesitar consultar datos)
        'http://localhost:8001',

        'http://127.0.0.1:8003',  // backoffice
        'http://localhost:8003',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Usamos Bearer tokens, no cookies cross-site
    'supports_credentials' => false,
];