<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Repositories;

use App\Modules\Notification\Domain\Aggregates\Notification;
use App\Modules\Notification\Domain\Repositories\NotificationPage;
use App\Modules\Notification\Domain\Repositories\NotificationRepository;
use App\Modules\Notification\Infrastructure\Persistence\Models\Notification as NotificationModel;

final class EloquentNotificationRepository implements NotificationRepository
{
    public function create(Notification $notification): Notification
    {
        $model = NotificationModel::create([
            'user_id' => $notification->userId(),
            'type' => $notification->type(),
            'data' => $notification->data(),
            'read_at' => $notification->readAt(),
            'created_at' => $notification->createdAt(),
        ]);

        $notification->assignId($model->id);

        return $notification;
    }

    public function findById(int $id): ?Notification
    {
        $model = NotificationModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function save(Notification $notification): void
    {
        NotificationModel::whereKey($notification->id())->update([
            'read_at' => $notification->readAt(),
        ]);
    }

    public function paginateForUser(int $userId, int $page, int $perPage): NotificationPage
    {
        $paginator = NotificationModel::query()
            ->where('user_id', $userId)
            ->latest('created_at')
            ->paginate(perPage: $perPage, page: $page);

        return new NotificationPage(
            items: array_map($this->toDomain(...), $paginator->items()),
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
        );
    }

    private function toDomain(NotificationModel $model): Notification
    {
        return Notification::reconstitute(
            id: $model->id,
            userId: $model->user_id,
            type: $model->type,
            data: $model->data,
            createdAt: $model->created_at->toDateTimeImmutable(),
            readAt: $model->read_at?->toDateTimeImmutable(),
        );
    }
}
