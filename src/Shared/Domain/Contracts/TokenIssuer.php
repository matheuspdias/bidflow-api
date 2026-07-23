<?php

declare(strict_types=1);

namespace App\Shared\Domain\Contracts;

/**
 * Lets Modules\Auth mint an API token for a user id without depending on
 * Modules\User internals (the concrete Sanctum-backed model) directly.
 */
interface TokenIssuer
{
    /**
     * @param  list<string>  $abilities
     */
    public function issue(int $userId, string $tokenName, array $abilities): string;
}
