<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\Notification resolve an email address to send to, without
 * depending on Modules\User internals directly.
 */
interface UserEmailLookup
{
    public function emailOf(int $userId): ?string;
}
