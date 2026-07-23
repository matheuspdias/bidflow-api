<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\Auth verify credentials without depending on Modules\User
 * internals directly — implemented by Modules\User's Infrastructure layer.
 */
interface UserAuthenticator
{
    /**
     * @return array{id: int, name: string, email: string, is_blocked: bool}|null
     */
    public function authenticate(string $email, string $plainPassword): ?array;
}
