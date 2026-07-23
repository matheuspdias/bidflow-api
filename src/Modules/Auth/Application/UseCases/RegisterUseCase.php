<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\UseCases;

use App\Shared\Domain\Contracts\UserRegistrar;

final class RegisterUseCase
{
    public function __construct(private readonly UserRegistrar $registrar)
    {
    }

    /**
     * @return array{id: int, name: string, email: string}
     */
    public function execute(string $name, string $email, string $plainPassword): array
    {
        return $this->registrar->register($name, $email, $plainPassword);
    }
}
