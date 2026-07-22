<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    /*
    |--------------------------------------------------------------------------
    | Domain events exchange
    |--------------------------------------------------------------------------
    |
    | All integration events are published to a single topic exchange, using
    | a "{module}.{event_snake_case}" routing key convention. Declared by
    | `php artisan rabbitmq:setup` (Fase 5).
    |
    */
    'exchange' => env('RABBITMQ_EXCHANGE', 'domain_events'),
];
