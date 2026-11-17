<?php

$origins = array_map(
    trim(...),
    explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:9000,http://127.0.0.1:9000',
    )),
);

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter($origins)),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['traceparent'],

    'max_age' => 0,

    'supports_credentials' => false,

];
