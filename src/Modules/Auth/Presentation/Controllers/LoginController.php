<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Controllers;

use App\Modules\Auth\Application\UseCases\LoginUseCase;
use App\Modules\Auth\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Domain\Exceptions\UserBlockedException;
use App\Modules\Auth\Domain\ValueObjects\TokenAbility;
use App\Modules\Auth\Presentation\Requests\LoginRequest;
use App\Shared\Domain\Contracts\TokenIssuer;
use Illuminate\Http\JsonResponse;

final class LoginController
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
        private readonly TokenIssuer $tokenIssuer,
    ) {
    }

    public function __invoke(LoginRequest $request): JsonResponse
    {
        try {
            $user = $this->loginUseCase->execute(
                (string) $request->validated('email'),
                (string) $request->validated('password'),
            );
        } catch (InvalidCredentialsException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (UserBlockedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        $token = $this->tokenIssuer->issue($user['id'], 'api', TokenAbility::all());

        return response()->json([
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ],
            'token' => $token,
        ]);
    }
}
