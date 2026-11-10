<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'admin/*'],
    'allowed_methods' => ['*'],

    'allowed_origins' => [
        // L'URL Cloudflare du Frontend
        'https://sea-told-measures-documented.trycloudflare.com',

        // Tests locaux
        'http://localhost:8081',
        'http://127.0.0.1:8081',
    ],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
