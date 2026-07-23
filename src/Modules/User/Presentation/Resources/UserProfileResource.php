<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Resources;

use App\Modules\User\Domain\Entities\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserProfileResource extends JsonResource
{
    public function __construct(private readonly UserProfile $profile)
    {
        parent::__construct($profile);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->profile->id(),
            'name' => $this->profile->name(),
            'email' => $this->profile->email()->value(),
            'avatar_path' => $this->profile->avatarPath(),
            'is_blocked' => $this->profile->isBlocked(),
        ];
    }
}
