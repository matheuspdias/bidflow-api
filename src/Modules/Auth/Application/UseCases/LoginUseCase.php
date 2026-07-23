<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Domain\Exceptions\UserBlockedException;
use App\Shared\Domain\Contracts\UserAuthenticator;

final class LoginUseCase
{
    public function __construct(private readonly UserAuthenticator $authenticator)
    {
    }

    /**
     * @return array{id: int, name: string, email: string, is_blocked: bool}
     */
    public function execute(string $email, string $plainPassword): array
    {
        $user = $this->authenticator->authenticate($email, $plainPassword);

        if ($user === null) {
            throw new InvalidCredentialsException();
        }

        if ($user['is_blocked']) {
            throw new UserBlockedException();
        }

        return $user;
    }
}
