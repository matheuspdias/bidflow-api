<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Controllers;

use App\Modules\User\Domain\Repositories\UserRepository;
use App\Modules\User\Presentation\Requests\UploadAvatarRequest;
use App\Modules\User\Presentation\Resources\UserProfileResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class AvatarController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function store(UploadAvatarRequest $request): UserProfileResource
    {
        $previous = $this->users->findById($request->user()->id);

        $path = $request->file('avatar')->store('avatars', 'public');

        if ($previous?->avatarPath()) {
            Storage::disk('public')->delete($previous->avatarPath());
        }

        $profile = $this->users->updateAvatarPath($request->user()->id, $path);

        return new UserProfileResource($profile);
    }

    public function destroy(Request $request): UserProfileResource
    {
        $profile = $this->users->findById($request->user()->id);

        if ($profile?->avatarPath()) {
            Storage::disk('public')->delete($profile->avatarPath());
        }

        $profile = $this->users->updateAvatarPath($request->user()->id, null);

        return new UserProfileResource($profile);
    }
}
