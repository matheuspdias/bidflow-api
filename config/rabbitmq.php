<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    /*
    |--------------------------------------------------------------------------
    | Management HTTP API
    |--------------------------------------------------------------------------
    |
    | The management plugin (already enabled — see the rabbitmq service's
    | image tag in docker-compose.yml) exposes per-queue depth over plain
    | HTTP Basic Auth, reusing the same broker credentials. Used by the
    | technical dashboard (Fase 15) to show queue depth per consumer.
    |
    */
    'management_port' => (int) env('RABBITMQ_MANAGEMENT_PORT', 15672),

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
