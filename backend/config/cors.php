// <?php

// return [
//     'paths' => ['api/*'],
//     'allowed_methods' => ['*'],
//     'allowed_origins' => array_values(array_filter(array_map(
//         'trim',
//         explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
//     ))),
//     'allowed_origins_patterns' => [],
//     'allowed_headers' => ['Accept', 'Content-Type', 'X-Anonymous-Reporter'],
//     'exposed_headers' => ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'],
//     'max_age' => 0,
//     'supports_credentials' => false,
// ];

<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://achenaki.netlify.app',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'X-Anonymous-Reporter',
    ],

    'exposed_headers' => [
        'Retry-After',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];

