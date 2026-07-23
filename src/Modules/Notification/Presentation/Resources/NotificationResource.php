<?php

declare(strict_types=1);

namespace App\Modules\Notification\Presentation\Resources;

use App\Modules\Notification\Domain\Aggregates\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class NotificationResource extends JsonResource
{
    public function __construct(private readonly Notification $notification)
    {
        parent::__construct($notification);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->notification->id(),
            'type' => $this->notification->type(),
            'data' => $this->notification->data(),
            'read_at' => $this->notification->readAt()?->format(DATE_ATOM),
            'created_at' => $this->notification->createdAt()->format(DATE_ATOM),
        ];
    }
}
