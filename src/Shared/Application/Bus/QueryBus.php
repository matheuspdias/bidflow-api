<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

final class QueryBus
{
    /** @var array<class-string<Query>, class-string<QueryHandler>> */
    private array $handlers = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param  class-string<Query>  $queryClass
     * @param  class-string<QueryHandler>  $handlerClass
     */
    public function register(string $queryClass, string $handlerClass): void
    {
        $this->handlers[$queryClass] = $handlerClass;
    }

    public function dispatch(Query $query): mixed
    {
        $queryClass = $query::class;

        if (! isset($this->handlers[$queryClass])) {
            throw new RuntimeException("No handler registered for query [{$queryClass}].");
        }

        /** @var QueryHandler $handler */
        $handler = $this->container->make($this->handlers[$queryClass]);

        return $handler->handle($query);
    }
}
