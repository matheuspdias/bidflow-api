<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Controllers;

use App\Modules\Auth\Application\UseCases\RegisterUseCase;
use App\Modules\Auth\Domain\ValueObjects\TokenAbility;
use App\Modules\Auth\Presentation\Requests\RegisterRequest;
use App\Shared\Domain\Contracts\TokenIssuer;
use Illuminate\Http\JsonResponse;

final class RegisterController
{
    public function __construct(
        private readonly RegisterUseCase $registerUseCase,
        private readonly TokenIssuer $tokenIssuer,
    ) {
    }

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = $this->registerUseCase->execute(
            (string) $request->validated('name'),
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        $token = $this->tokenIssuer->issue($user['id'], 'api', TokenAbility::all());

        return response()->json(['user' => $user, 'token' => $token], 201);
    }
}
