<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Adapters;

use App\Modules\Notification\Domain\Aggregates\Notification;
use App\Modules\Notification\Domain\Repositories\NotificationRepository;
use App\Modules\Notification\Infrastructure\Broadcast\NotificationCreatedBroadcastEvent;
use App\Modules\Notification\Infrastructure\Mail\NotificationMail;
use App\Shared\Domain\Contracts\NotificationDispatcher;
use App\Shared\Domain\Contracts\UserEmailLookup;
use Illuminate\Support\Facades\Mail;

final class NotificationDispatcherAdapter implements NotificationDispatcher
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly UserEmailLookup $emails,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(int $userId, string $type, array $data): void
    {
        $notification = $this->notifications->create(Notification::create($userId, $type, $data));

        broadcast(new NotificationCreatedBroadcastEvent($userId, (int) $notification->id(), $type, $data));

        $email = $this->emails->emailOf($userId);

        if ($email !== null) {
            Mail::to($email)->queue(new NotificationMail($type, $data));
        }
    }
}
