<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Repositories;

use App\Modules\Notification\Domain\Aggregates\Notification;

interface NotificationRepository
{
    public function create(Notification $notification): Notification;

    public function findById(int $id): ?Notification;

    public function save(Notification $notification): void;

    public function paginateForUser(int $userId, int $page, int $perPage): NotificationPage;
}
