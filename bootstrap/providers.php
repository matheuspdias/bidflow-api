<?php

use App\Modules\Auction\Providers\AuctionServiceProvider;
use App\Modules\Auth\Providers\AuthServiceProvider;
use App\Modules\Dashboard\Providers\DashboardServiceProvider;
use App\Modules\Notification\Providers\NotificationServiceProvider;
use App\Modules\User\Providers\UserServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,

    // Modules
    AuctionServiceProvider::class,
    AuthServiceProvider::class,
    DashboardServiceProvider::class,
    NotificationServiceProvider::class,
    UserServiceProvider::class,
];
