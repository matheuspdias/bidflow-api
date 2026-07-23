<?php

declare(strict_types=1);

namespace Tests\Support;

use Laravel\Reverb\Contracts\Connection;

/**
 * A bare-bones stand-in for Reverb's abstract Connection, used only to
 * construct MessageSent events in tests. Not a Mockery mock: Mockery's
 * eval-based proxy generation trips over a pre-existing type mismatch in
 * Connection::control()'s default value (Frame::OP_PING is an int against a
 * `string $type` parameter) — harmless for normal autoloading, fatal when
 * Mockery re-evals the generated subclass as a string. A plain, normally
 * autoloaded subclass sidesteps that entirely since we control the method
 * bodies (and don't reuse the offending default expression).
 */
final class FakeReverbConnection extends Connection
{
    public function __construct()
    {
        // Deliberately skips the parent constructor — none of these tests
        // need a real WebSocketConnection/Application/origin.
    }

    public function identifier(): string
    {
        return 'fake';
    }

    public function id(): string
    {
        return 'fake';
    }

    public function send(string $message): void
    {
    }

    public function control(string $type = 'ping'): void
    {
    }

    public function terminate(): void
    {
    }
}
