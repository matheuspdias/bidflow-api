<?php

declare(strict_types=1);

use App\Shared\Infrastructure\MessageBroker\RabbitMqConnectionFactory;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Declares (idempotently) the same durable queue + binding a consumer's own
 * handle() would declare on first run, then purges it. Must be called
 * before publishing: these queues are durable and persist across runs once
 * created — both because a queue's very first-ever declaration means
 * anything published earlier is dropped by the topic exchange (no binding
 * yet to catch it), and because leftover messages from a previous run
 * (manual testing, an earlier failed assertion) would otherwise be
 * delivered ahead of the message this test just published, making the
 * count non-deterministic.
 */
function ensureConsumerQueueExists(string $consumerName, string $routingKey): void
{
    $connection = app(RabbitMqConnectionFactory::class)->make();
    $channel = $connection->channel();

    $exchange = config('rabbitmq.exchange');
    $queue = "domain_events.{$consumerName}";

    $channel->queue_declare(
        $queue,
        false,
        true,
        false,
        false,
        false,
        new AMQPTable(['x-dead-letter-exchange' => "{$exchange}.dlx"]),
    );
    $channel->queue_bind($queue, $exchange, $routingKey);
    $channel->queue_purge($queue);

    $channel->close();
    $connection->close();
}

/**
 * Publishes a raw payload directly to the domain_events exchange under the
 * given routing key — bypassing HTTP/use cases entirely, since these tests
 * exercise the consumer side of the pipeline in isolation.
 */
function publishRawIntegrationEvent(string $routingKey, array $payload): void
{
    $connection = app(RabbitMqConnectionFactory::class)->make();
    $channel = $connection->channel();

    $message = new AMQPMessage(json_encode($payload), [
        'content_type' => 'application/json',
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    ]);

    $channel->basic_publish($message, config('rabbitmq.exchange'), $routingKey);

    $channel->close();
    $connection->close();
}
