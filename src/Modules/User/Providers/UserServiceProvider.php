<?php

declare(strict_types=1);

namespace App\Modules\User\Providers;

use App\Modules\User\Domain\Repositories\UserRepository;
use App\Modules\User\Infrastructure\Persistence\Adapters\SanctumTokenIssuer;
use App\Modules\User\Infrastructure\Persistence\Adapters\UserLookupAdapter;
use App\Modules\User\Infrastructure\Repositories\EloquentUserRepository;
use App\Shared\Domain\Contracts\BidderLookup;
use App\Shared\Domain\Contracts\SellerLookup;
use App\Shared\Domain\Contracts\TokenIssuer;
use App\Shared\Domain\Contracts\UserAuthenticator;
use App\Shared\Domain\Contracts\UserRegistrar;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(UserRegistrar::class, EloquentUserRepository::class);
        $this->app->bind(UserAuthenticator::class, EloquentUserRepository::class);
        $this->app->bind(SellerLookup::class, UserLookupAdapter::class);
        $this->app->bind(BidderLookup::class, UserLookupAdapter::class);
        $this->app->bind(TokenIssuer::class, SanctumTokenIssuer::class);
    }

    public function boot(): void
    {
        //
    }
}
