<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus;

use Illuminate\Contracts\Container\Container;
use RuntimeException;

final class CommandBus
{
    /** @var array<class-string<Command>, class-string<CommandHandler>> */
    private array $handlers = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param  class-string<Command>  $commandClass
     * @param  class-string<CommandHandler>  $handlerClass
     */
    public function register(string $commandClass, string $handlerClass): void
    {
        $this->handlers[$commandClass] = $handlerClass;
    }

    public function dispatch(Command $command): mixed
    {
        $commandClass = $command::class;

        if (! isset($this->handlers[$commandClass])) {
            throw new RuntimeException("No handler registered for command [{$commandClass}].");
        }

        /** @var CommandHandler $handler */
        $handler = $this->container->make($this->handlers[$commandClass]);

        return $handler->handle($command);
    }
}
