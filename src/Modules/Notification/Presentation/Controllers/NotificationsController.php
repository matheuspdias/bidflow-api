<?php

declare(strict_types=1);

namespace App\Modules\Notification\Presentation\Controllers;

use App\Modules\Notification\Domain\Repositories\NotificationRepository;
use App\Modules\Notification\Presentation\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationsController
{
    public function __construct(private readonly NotificationRepository $notifications)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->integer('page', 1));
        $perPage = min(50, max(1, $request->integer('per_page', 15)));

        $result = $this->notifications->paginateForUser($request->user()->id, $page, $perPage);

        return response()->json([
            'data' => NotificationResource::collection($result->items),
            'meta' => [
                'total' => $result->total,
                'per_page' => $result->perPage,
                'current_page' => $result->currentPage,
            ],
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->notifications->findById($id);

        if ($notification === null || $notification->userId() !== $request->user()->id) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->markAsRead();
        $this->notifications->save($notification);

        return response()->json(['data' => new NotificationResource($notification)]);
    }
}
