<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Repositories;

use App\Modules\User\Domain\Entities\UserProfile;
use App\Modules\User\Domain\ValueObjects\Email;

interface UserRepository
{
    public function findById(int $id): ?UserProfile;

    public function findByEmail(string $email): ?UserProfile;

    public function existsById(int $id): bool;

    public function create(string $name, Email $email, string $plainPassword): UserProfile;

    public function updateProfile(int $id, string $name, Email $email): UserProfile;

    public function updateAvatarPath(int $id, ?string $avatarPath): UserProfile;

    public function verifyCredentials(string $email, string $plainPassword): ?UserProfile;
}
