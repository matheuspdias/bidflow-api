<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\MessageBroker\Console;

use App\Shared\Infrastructure\MessageBroker\RabbitMqConnectionFactory;
use Illuminate\Console\Command;

/**
 * Declares the domain_events topic exchange and its dead-letter fanout
 * exchange. Idempotent — safe to run every deploy. Queues are declared by
 * each consumer (Fase 6), not here.
 */
final class RabbitMqSetupCommand extends Command
{
    protected $signature = 'rabbitmq:setup';

    protected $description = 'Declare the domain_events exchange and its dead-letter exchange';

    public function handle(RabbitMqConnectionFactory $connectionFactory): int
    {
        $connection = $connectionFactory->make();
        $channel = $connection->channel();

        $exchange = config('rabbitmq.exchange');
        $deadLetterExchange = "{$exchange}.dlx";

        $channel->exchange_declare($deadLetterExchange, 'fanout', false, true, false);
        $channel->exchange_declare($exchange, 'topic', false, true, false);

        $channel->close();
        $connection->close();

        $this->info("Declared exchange [{$exchange}] (topic) and dead-letter exchange [{$deadLetterExchange}] (fanout).");

        return self::SUCCESS;
    }
}
