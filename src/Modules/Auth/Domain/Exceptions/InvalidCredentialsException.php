<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exceptions;

use DomainException;

final class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }
}
