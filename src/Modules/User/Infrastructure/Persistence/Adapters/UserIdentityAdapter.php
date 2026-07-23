<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Adapters;

use App\Modules\User\Infrastructure\Persistence\Models\User;
use App\Shared\Domain\Contracts\UserIdentity;

/**
 * First concrete proof of the Shared\Domain\Contracts pattern (ADR-0003):
 * other modules depend on UserIdentity, never on this class or on
 * Modules\User\Infrastructure\Persistence\Models\User directly.
 */
final class UserIdentityAdapter implements UserIdentity
{
    public function __construct(private readonly User $user)
    {
    }

    public function id(): int
    {
        return $this->user->id;
    }

    public function isBlocked(): bool
    {
        return $this->user->is_blocked;
    }
}
