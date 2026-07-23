<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entities;

use App\Modules\User\Domain\ValueObjects\Email;

/**
 * Read-oriented domain representation of a user, deliberately excluding
 * secrets (password hash, remember token) — those stay an Infrastructure
 * concern of App\Modules\User\Infrastructure\Persistence\Models\User.
 */
final class UserProfile
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly Email $email,
        private readonly ?string $avatarPath,
        private readonly bool $isBlocked,
    ) {
    }

    /**
     * @param  array{id: int, name: string, email: string, avatar_path: ?string, is_blocked: bool}  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: $attributes['id'],
            name: $attributes['name'],
            email: Email::fromString($attributes['email']),
            avatarPath: $attributes['avatar_path'] ?? null,
            isBlocked: (bool) $attributes['is_blocked'],
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function avatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }
}
