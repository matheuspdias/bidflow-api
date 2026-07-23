<?php

declare(strict_types=1);

namespace Tests\Support;

use Laravel\Reverb\Protocols\Pusher\Channels\Channel;

/**
 * Channel's real constructor resolves ChannelConnectionManager out of the
 * container — a binding only registered when an actual reverb:start server
 * boots, not in a normal test app. ChannelRemoved's listener only ever
 * calls ->name(), so this skips the parent constructor and sets the
 * (protected, constructor-promoted) $name property directly instead.
 *
 * RecordFirstPresenceMember also calls ->connections(), so this accepts an
 * optional fixed list to return instead of touching the real connection
 * manager.
 *
 * @param  list<\Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection>  $fakeConnections
 */
final class FakeReverbChannel extends Channel
{
    public function __construct(string $name, private readonly array $fakeConnections = [])
    {
        $this->name = $name;
    }

    /**
     * @return list<\Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection>
     */
    public function connections(): array
    {
        return $this->fakeConnections;
    }
}
