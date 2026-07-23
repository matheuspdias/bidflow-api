<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Modules\Notification\Domain\Repositories\NotificationRepository;
use App\Modules\Notification\Infrastructure\Adapters\NotificationDispatcherAdapter;
use App\Modules\Notification\Infrastructure\Repositories\EloquentNotificationRepository;
use App\Shared\Domain\Contracts\NotificationDispatcher;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->bind(NotificationDispatcher::class, NotificationDispatcherAdapter::class);
    }

    public function boot(): void
    {
        //
    }
}
