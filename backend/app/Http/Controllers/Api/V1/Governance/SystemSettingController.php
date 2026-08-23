<?php

namespace App\Http\Controllers\Api\V1\Governance;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemSettingController extends Controller
{
    /** Public consumers (the storefront) only ever see is_public settings. */
    public function index(Request $request): JsonResponse
    {
        $query = SystemSetting::query();

        if (! $request->user()?->hasAnyPermission(['settings.manage'])) {
            $query->where('is_public', true);
        }

        return SystemSettingResource::collection($query->get())->response();
    }

    /**
     * super_admin only (CLAUDE.md §6/§8: system configuration is never a
     * manager-level permission) - and this can never hold a secret, only
     * config toggles; secrets live exclusively in environment variables.
     */
    public function upsert(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['settings.manage']), 403);

        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required'],
            'type' => ['required', Rule::in(['string', 'number', 'boolean', 'json'])],
            'group' => ['nullable', 'string', 'max:255'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $setting = SystemSetting::updateOrCreate(
            ['key' => $data['key']],
            [...$data, 'updated_by' => $request->user()->id],
        );

        AuditLogger::log('system_setting.updated', $setting, after: $setting->toArray());

        return SystemSettingResource::make($setting)->response();
    }
}
