<?php

// return [
//     'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],
//     'allowed_methods' => ['*'],
//     'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS'))),
//     'allowed_origins_patterns' => [],
//     'allowed_headers' => ['*'],
//     'exposed_headers' => [],
//     'max_age' => 0,
//     'supports_credentials' => true,
// ];
// return [

//     'paths' => ['api/*', 'login'],

//     'allowed_methods' => ['*'],

//     'allowed_origins' => [
//         'http://localhost:8081',
//         'http://127.0.0.1:8081',
//     ],

//     'allowed_headers' => ['*'],

//     'supports_credentials' => true,

// ];

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // Use environment variable, fallback to localhost for local
    'allowed_origins' => env('APP_ENV') === 'local'
        ? ['http://localhost:8081', 'http://127.0.0.1:8081']
        : array_filter(explode(',', env('CORS_ALLOWED_ORIGINS'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];