<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Repositories;

use App\Modules\User\Domain\Entities\UserProfile;
use App\Modules\User\Domain\Repositories\UserRepository;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Infrastructure\Persistence\Models\User as UserModel;
use App\Shared\Domain\Contracts\UserAuthenticator;
use App\Shared\Domain\Contracts\UserRegistrar;
use Illuminate\Support\Facades\Hash;

final class EloquentUserRepository implements UserRepository, UserRegistrar, UserAuthenticator
{
    public function findById(int $id): ?UserProfile
    {
        $user = UserModel::find($id);

        return $user ? $this->toDomain($user) : null;
    }

    public function findByEmail(string $email): ?UserProfile
    {
        $user = UserModel::where('email', $email)->first();

        return $user ? $this->toDomain($user) : null;
    }

    public function existsById(int $id): bool
    {
        return UserModel::whereKey($id)->exists();
    }

    public function create(string $name, Email $email, string $plainPassword): UserProfile
    {
        $user = UserModel::create([
            'name' => $name,
            'email' => $email->value(),
            'password' => Hash::make($plainPassword),
        ]);

        return $this->toDomain($user);
    }

    public function updateProfile(int $id, string $name, Email $email): UserProfile
    {
        $user = UserModel::findOrFail($id);
        $user->update([
            'name' => $name,
            'email' => $email->value(),
        ]);

        return $this->toDomain($user);
    }

    public function updateAvatarPath(int $id, ?string $avatarPath): UserProfile
    {
        $user = UserModel::findOrFail($id);
        $user->update(['avatar_path' => $avatarPath]);

        return $this->toDomain($user);
    }

    public function verifyCredentials(string $email, string $plainPassword): ?UserProfile
    {
        $user = UserModel::where('email', $email)->first();

        if ($user === null || ! Hash::check($plainPassword, $user->password)) {
            return null;
        }

        return $this->toDomain($user);
    }

    public function register(string $name, string $email, string $plainPassword): array
    {
        $profile = $this->create($name, Email::fromString($email), $plainPassword);

        return [
            'id' => $profile->id(),
            'name' => $profile->name(),
            'email' => $profile->email()->value(),
        ];
    }

    public function authenticate(string $email, string $plainPassword): ?array
    {
        $profile = $this->verifyCredentials($email, $plainPassword);

        if ($profile === null) {
            return null;
        }

        return [
            'id' => $profile->id(),
            'name' => $profile->name(),
            'email' => $profile->email()->value(),
            'is_blocked' => $profile->isBlocked(),
        ];
    }

    private function toDomain(UserModel $user): UserProfile
    {
        return UserProfile::fromArray([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_path' => $user->avatar_path,
            'is_blocked' => $user->is_blocked,
        ]);
    }
}
