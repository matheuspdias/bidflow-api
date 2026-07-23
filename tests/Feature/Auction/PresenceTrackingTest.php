<?php

use App\Modules\Auction\Domain\Events\UserJoinedAuction;
use App\Modules\Auction\Domain\Events\UserLeftAuction;
use App\Modules\Auction\Infrastructure\Listeners\RecordFirstPresenceMember;
use App\Modules\Auction\Infrastructure\Listeners\ReleasePresenceOnChannelEmpty;
use App\Modules\Auction\Infrastructure\Listeners\TrackPresenceChannelMembership;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\MessageSent;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use React\EventLoop\Loop;
use Tests\Support\FakeReverbChannel;
use Tests\Support\FakeReverbConnection;

/**
 * Reverb has no outbound webhook for presence join/leave (ADR-0012) — these
 * listeners substitute by observing Reverb's own internal Laravel events,
 * fired inside the reverb:start process. Constructing those events directly
 * here (rather than running a real WebSocket server) isolates the parsing
 * and Redis bookkeeping logic; the end-to-end wire behaviour is validated
 * separately with a real Reverb server (see the Fase 8 milestone note in
 * the README).
 */
beforeEach(function () {
    // RefreshDatabase resets Postgres, not the Redis connection these
    // listeners write to directly — clear the key explicitly so tests don't
    // see state left over by a previous test.
    Redis::del('auction:42:viewers');
});

function fakeMemberMessage(string $pusherEvent, string $channel, array $userData): string
{
    return json_encode([
        'event' => $pusherEvent,
        'channel' => $channel,
        'data' => json_encode($userData),
    ]);
}

test('a member_added frame adds the user to the Redis viewer set and fires UserJoinedAuction', function () {
    Event::fake(UserJoinedAuction::class);

    $connection = new FakeReverbConnection();
    $listener = new TrackPresenceChannelMembership();

    $listener->handle(new MessageSent(
        $connection,
        fakeMemberMessage('pusher_internal:member_added', 'presence-auction.42', ['user_id' => '7']),
    ));

    expect(Redis::sismember('auction:42:viewers', 7))->toBeTrue();
    Event::assertDispatched(UserJoinedAuction::class, fn ($event) => $event->auctionId === 42 && $event->userId === 7);
});

test('a duplicate member_added frame (fan-out to every other viewer) does not re-fire the event', function () {
    Event::fake(UserJoinedAuction::class);

    $connection = new FakeReverbConnection();
    $listener = new TrackPresenceChannelMembership();
    $message = fakeMemberMessage('pusher_internal:member_added', 'presence-auction.42', ['user_id' => '7']);

    $listener->handle(new MessageSent($connection, $message));
    $listener->handle(new MessageSent($connection, $message));
    $listener->handle(new MessageSent($connection, $message));

    Event::assertDispatchedTimes(UserJoinedAuction::class, 1);
});

test('a member_removed frame removes the user from the Redis set and fires UserLeftAuction', function () {
    Redis::sadd('auction:42:viewers', 7);
    Event::fake(UserLeftAuction::class);

    $connection = new FakeReverbConnection();
    $listener = new TrackPresenceChannelMembership();

    $listener->handle(new MessageSent(
        $connection,
        fakeMemberMessage('pusher_internal:member_removed', 'presence-auction.42', ['user_id' => '7']),
    ));

    expect(Redis::sismember('auction:42:viewers', 7))->toBeFalse();
    Event::assertDispatched(UserLeftAuction::class, fn ($event) => $event->auctionId === 42 && $event->userId === 7);
});

test('frames for other channels or events are ignored', function () {
    Event::fake([UserJoinedAuction::class, UserLeftAuction::class]);

    $connection = new FakeReverbConnection();
    $listener = new TrackPresenceChannelMembership();

    $listener->handle(new MessageSent($connection, fakeMemberMessage('pusher_internal:member_added', 'presence-auction.42', ['user_id' => '7'])));
    // not a member_added/removed event
    $listener->handle(new MessageSent($connection, json_encode(['event' => 'pusher:ping', 'channel' => 'presence-auction.42', 'data' => '{}'])));
    // not a presence-auction channel
    $listener->handle(new MessageSent($connection, fakeMemberMessage('pusher_internal:member_added', 'presence-user.99', ['user_id' => '7'])));

    Event::assertDispatchedTimes(UserJoinedAuction::class, 1);
    Event::assertNotDispatched(UserLeftAuction::class);
});

test('ChannelRemoved flushes whatever is left in the viewer set and fires UserLeftAuction for it', function () {
    Redis::sadd('auction:42:viewers', 9);
    Event::fake(UserLeftAuction::class);

    (new ReleasePresenceOnChannelEmpty())->handle(new ChannelRemoved(new FakeReverbChannel('presence-auction.42')));

    expect(Redis::smembers('auction:42:viewers'))->toBe([]);
    Event::assertDispatched(UserLeftAuction::class, fn ($event) => $event->auctionId === 42 && $event->userId === 9);
});

test('ChannelRemoved on an already-empty viewer set fires nothing', function () {
    Event::fake(UserLeftAuction::class);

    (new ReleasePresenceOnChannelEmpty())->handle(new ChannelRemoved(new FakeReverbChannel('presence-auction.42')));

    Event::assertNotDispatched(UserLeftAuction::class);
});

test('ChannelRemoved for an unrelated channel is ignored', function () {
    Redis::sadd('auction:42:viewers', 9);
    Event::fake(UserLeftAuction::class);

    (new ReleasePresenceOnChannelEmpty())->handle(new ChannelRemoved(new FakeReverbChannel('presence-user.9')));

    expect(Redis::smembers('auction:42:viewers'))->toBe(['9']);
    Event::assertNotDispatched(UserLeftAuction::class);
});

test('ChannelCreated records the first joiner once the deferred tick sees them subscribed', function () {
    Event::fake(UserJoinedAuction::class);

    $connection = new ChannelConnection(new FakeReverbConnection(), ['user_id' => '11']);
    $channel = new FakeReverbChannel('presence-auction.42', [$connection]);

    (new RecordFirstPresenceMember())->handle(new ChannelCreated($channel));
    // The listener only schedules a futureTick callback — nothing has run
    // yet, matching the real timing (channel is still empty when
    // ChannelCreated fires; see the listener's docblock).
    Event::assertNotDispatched(UserJoinedAuction::class);

    Loop::run();

    expect(Redis::sismember('auction:42:viewers', 11))->toBeTrue();
    Event::assertDispatched(UserJoinedAuction::class, fn ($event) => $event->auctionId === 42 && $event->userId === 11);
});

test('ChannelCreated for an unrelated channel schedules nothing', function () {
    Event::fake(UserJoinedAuction::class);

    $connection = new ChannelConnection(new FakeReverbConnection(), ['user_id' => '11']);
    $channel = new FakeReverbChannel('presence-user.11', [$connection]);

    (new RecordFirstPresenceMember())->handle(new ChannelCreated($channel));
    Loop::run();

    Event::assertNotDispatched(UserJoinedAuction::class);
});
