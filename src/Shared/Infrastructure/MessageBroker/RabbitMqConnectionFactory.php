<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\MessageBroker;

use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Builds a fresh AMQP connection from config/rabbitmq.php. Not used until
 * Fase 5 (publishing) and Fase 6 (consuming) wire up real exchanges/queues.
 */
final class RabbitMqConnectionFactory
{
    public function make(): AMQPStreamConnection
    {
        return new AMQPStreamConnection(
            host: config('rabbitmq.host'),
            port: config('rabbitmq.port'),
            user: config('rabbitmq.user'),
            password: config('rabbitmq.password'),
            vhost: config('rabbitmq.vhost'),
        );
    }
}
