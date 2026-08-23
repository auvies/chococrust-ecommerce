<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\UpdateNotificationTemplateRequest;
use App\Http\Resources\NotificationTemplateResource;
use App\Models\NotificationTemplate;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Content, not taxonomy: templates are seeded by NotificationTemplateSeeder
 * (one row per event key x channel, matching what each Notification class
 * in app/Notifications actually renders) and only their subject/body/
 * is_active are admin-editable - there's deliberately no `store`/`destroy`
 * here, since a template an application notification class never looks up
 * would just be dead content, and the set of (key, channel) pairs is fixed
 * by code, not by admin input.
 */
class NotificationTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['notifications.manage']), 403);

        $templates = NotificationTemplate::query()->orderBy('key')->orderBy('channel')->get();

        return NotificationTemplateResource::collection($templates)->response();
    }

    public function update(UpdateNotificationTemplateRequest $request, NotificationTemplate $notificationTemplate): JsonResponse
    {
        $before = $notificationTemplate->toArray();
        $notificationTemplate->update($request->validated());

        AuditLogger::log('notification_template.updated', $notificationTemplate, before: $before, after: $notificationTemplate->fresh()->toArray());

        return NotificationTemplateResource::make($notificationTemplate->fresh())->response();
    }
}
