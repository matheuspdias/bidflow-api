<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\MessageBroker\Console;

use App\Shared\Infrastructure\MessageBroker\RabbitMqConnectionFactory;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * Base for every RabbitMQ consumer: declares its own queue bound to the
 * shared domain_events exchange, consumes with idempotent processing
 * (processed_events, keyed by event_id + consumer name — AMQP is
 * at-least-once, a message can be redelivered), and a bounded retry that
 * dead-letters after giving up (see ADR-0010).
 *
 * Also the single place Fase 15's processed-count/latency instrumentation
 * will be added — recordMetrics() is the extension point, built now so that
 * phase doesn't need to touch every concrete consumer.
 */
abstract class RabbitMqConsumerCommand extends Command
{
    private const MAX_RETRIES = 3;

    public function __construct(private readonly RabbitMqConnectionFactory $connectionFactory)
    {
        parent::__construct();
    }

    /**
     * Unique name for this consumer — the second half of the
     * processed_events idempotency key, and the queue name suffix.
     */
    abstract protected function consumerName(): string;

    /**
     * The domain_events routing key this consumer binds its queue to (e.g.
     * "auction.bid_placed"). One routing key per consumer keeps the binding
     * — and what queue a message ends up in — trivial to reason about.
     */
    abstract protected function routingKey(): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    abstract protected function process(array $payload): void;

    protected function recordMetrics(DateTimeImmutable $occurredAt): void
    {
        // Fase 15: processed-event counters + latency, added here once —
        // every consumer gets it for free.
    }

    public function handle(): int
    {
        $connection = $this->connectionFactory->make();
        $channel = $connection->channel();

        $exchange = config('rabbitmq.exchange');
        $queue = "domain_events.{$this->consumerName()}";

        $channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-dead-letter-exchange' => "{$exchange}.dlx"]),
        );
        $channel->queue_bind($queue, $exchange, $this->routingKey());
        $channel->basic_qos(0, 1, false);

        $limit = (int) $this->option('limit');
        $processedCount = 0;

        $channel->basic_consume($queue, '', false, false, false, false, function (AMQPMessage $message) use (&$processedCount) {
            $this->onMessage($message);
            $processedCount++;
        });

        $this->info("Consuming [{$this->routingKey()}] on queue [{$queue}] as [{$this->consumerName()}]...");

        $timeout = (float) $this->option('timeout');

        while ($channel->is_consuming()) {
            if ($limit > 0 && $processedCount >= $limit) {
                break;
            }

            try {
                $channel->wait(null, false, $timeout > 0 ? $timeout : 0);
            } catch (\PhpAmqpLib\Exception\AMQPTimeoutException) {
                if ($timeout > 0) {
                    break;
                }
            }
        }

        $channel->close();
        $connection->close();

        return self::SUCCESS;
    }

    private function onMessage(AMQPMessage $message): void
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($message->getBody(), true) ?? [];
        $eventId = $payload['event_id'] ?? null;

        if ($eventId !== null && ! $this->claim($eventId)) {
            // Another delivery — a genuine duplicate, a retry racing a
            // scaled-up sibling instance, or this exact event already fully
            // processed — got there first. Not ours to do; just ack.
            $message->ack();

            return;
        }

        try {
            $this->process($payload);

            if ($eventId !== null && isset($payload['occurred_at'])) {
                $this->recordMetrics(new DateTimeImmutable($payload['occurred_at']));
            }

            $message->ack();
        } catch (Throwable $exception) {
            if ($eventId !== null) {
                // We claimed it but failed — release the claim so the retry
                // this triggers below can claim and attempt it again.
                $this->releaseClaim($eventId);
            }

            $this->handleFailure($message, $exception);
        }
    }

    /**
     * Atomically claims (event_id, consumer_name) via the table's unique
     * constraint, so that two consumer instances racing the same delivery
     * — not just a sequential redelivery — can't both proceed to process()
     * before either has recorded anything. Whichever's INSERT wins is the
     * one that does the work; the other's insert fails and it backs off.
     */
    private function claim(string $eventId): bool
    {
        try {
            // Wrapped in its own transaction so a caught unique-violation
            // rolls back to a savepoint instead of poisoning whatever
            // transaction the caller might already be inside — Postgres (unlike
            // MySQL) aborts the entire enclosing transaction after any failed
            // statement unless the failure is contained by a savepoint.
            DB::transaction(function () use ($eventId) {
                DB::table('processed_events')->insert([
                    'event_id' => $eventId,
                    'consumer_name' => $this->consumerName(),
                    'processed_at' => now(),
                ]);
            });

            return true;
        } catch (QueryException) {
            return false;
        }
    }

    private function releaseClaim(string $eventId): void
    {
        DB::table('processed_events')
            ->where('event_id', $eventId)
            ->where('consumer_name', $this->consumerName())
            ->delete();
    }

    private function handleFailure(AMQPMessage $message, Throwable $exception): void
    {
        $retryCount = $this->retryCount($message);

        Log::warning("Consumer [{$this->consumerName()}] failed to process a message.", [
            'exception' => $exception->getMessage(),
            'retry_count' => $retryCount,
        ]);

        if ($retryCount < self::MAX_RETRIES) {
            $this->republishWithIncrementedRetry($message, $retryCount + 1);
            $message->ack();

            return;
        }

        Log::error("Consumer [{$this->consumerName()}] exhausted retries — dead-lettering.", [
            'exception' => $exception->getMessage(),
        ]);

        $message->nack(false);
    }

    private function retryCount(AMQPMessage $message): int
    {
        if (! $message->has('application_headers')) {
            return 0;
        }

        /** @var AMQPTable $headers */
        $headers = $message->get('application_headers');

        return (int) ($headers->getNativeData()['x-retry-count'] ?? 0);
    }

    private function republishWithIncrementedRetry(AMQPMessage $message, int $retryCount): void
    {
        $retryMessage = new AMQPMessage($message->getBody(), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => new AMQPTable(['x-retry-count' => $retryCount]),
        ]);

        $message->getChannel()?->basic_publish($retryMessage, config('rabbitmq.exchange'), $this->routingKey());
    }
}
