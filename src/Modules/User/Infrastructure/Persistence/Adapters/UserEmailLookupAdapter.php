<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Adapters;

use App\Modules\User\Domain\Repositories\UserRepository;
use App\Shared\Domain\Contracts\UserEmailLookup;

final class UserEmailLookupAdapter implements UserEmailLookup
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function emailOf(int $userId): ?string
    {
        return $this->users->findById($userId)?->email()->value();
    }
}
