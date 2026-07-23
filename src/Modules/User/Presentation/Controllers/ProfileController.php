<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Controllers;

use App\Modules\User\Domain\Repositories\UserRepository;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Presentation\Requests\UpdateProfileRequest;
use App\Modules\User\Presentation\Resources\UserProfileResource;
use Illuminate\Http\Request;

final class ProfileController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function show(Request $request): UserProfileResource
    {
        $profile = $this->users->findById($request->user()->id);

        return new UserProfileResource($profile);
    }

    public function update(UpdateProfileRequest $request): UserProfileResource
    {
        $profile = $this->users->updateProfile(
            $request->user()->id,
            (string) $request->validated('name'),
            Email::fromString((string) $request->validated('email')),
        );

        return new UserProfileResource($profile);
    }
}
