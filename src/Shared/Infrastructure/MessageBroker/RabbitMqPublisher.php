<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\MessageBroker;

use App\Shared\Domain\Events\IntegrationEvent;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Publishes an IntegrationEvent to the topic exchange configured in
 * config/rabbitmq.php ("domain_events" by default), using the event's own
 * routing key. Callers (Infrastructure listeners, from Fase 5 onward) are
 * responsible for catching failures and persisting them for replay — a
 * publish failure must never roll back the transaction that already
 * committed the triggering domain event.
 */
final class RabbitMqPublisher
{
    public function __construct(private readonly RabbitMqConnectionFactory $connectionFactory)
    {
    }

    public function publish(IntegrationEvent $event, ?string $exchange = null): void
    {
        $exchange ??= config('rabbitmq.exchange');

        $connection = $this->connectionFactory->make();
        $channel = $connection->channel();

        try {
            $message = new AMQPMessage(
                json_encode($event->toArray(), JSON_THROW_ON_ERROR),
                [
                    'content_type' => 'application/json',
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'message_id' => $event->eventId(),
                ],
            );

            $channel->basic_publish($message, $exchange, $event->routingKey());
        } finally {
            $channel->close();
            $connection->close();
        }
    }
}
