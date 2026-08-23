<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Every endpoint here is implicitly scoped to the caller's own notifications - never another user's. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = ApiQuery::for($request, $request->user()->notifications())
            ->sorts(['created_at'])
            ->paginate();

        return NotificationResource::collection($notifications)->response();
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        return NotificationResource::make($record)->response();
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(null, 204);
    }
}
