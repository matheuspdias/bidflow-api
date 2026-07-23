<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Infrastructure\MessageBroker\Console\RabbitMqConsumerCommand;
use RuntimeException;

/**
 * Test-only consumer that always throws, used to exercise
 * RabbitMqConsumerCommand's retry + dead-letter mechanics in isolation from
 * any production consumer's own business logic (see BidConsumerTest).
 */
final class AlwaysFailingTestConsumer extends RabbitMqConsumerCommand
{
    protected $signature = 'test:always-failing-consumer {--limit=0} {--timeout=0}';

    protected $description = 'Test-only consumer that always throws';

    protected function consumerName(): string
    {
        return 'test_always_failing';
    }

    protected function routingKey(): string
    {
        return 'test.always_failing';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function process(array $payload): void
    {
        throw new RuntimeException('Simulated processing failure for test.');
    }
}
