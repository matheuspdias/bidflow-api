<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Adapters;

use App\Modules\User\Infrastructure\Persistence\Models\User;
use App\Shared\Domain\Contracts\TokenIssuer;

final class SanctumTokenIssuer implements TokenIssuer
{
    public function issue(int $userId, string $tokenName, array $abilities): string
    {
        $user = User::findOrFail($userId);

        return $user->createToken($tokenName, $abilities)->plainTextToken;
    }
}
