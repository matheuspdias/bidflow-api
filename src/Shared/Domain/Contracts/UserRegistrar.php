<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\Auth create a user account without depending on
 * Modules\User internals directly — implemented by Modules\User's
 * Infrastructure layer.
 */
interface UserRegistrar
{
    /**
     * @return array{id: int, name: string, email: string}
     */
    public function register(string $name, string $email, string $plainPassword): array;
}
