<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreThemeRequest;
use App\Http\Requests\Content\UpdateThemeRequest;
use App\Http\Resources\ThemeResource;
use App\Models\Theme;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    public function index(): JsonResponse
    {
        return ThemeResource::collection(Theme::orderBy('name')->get())->response();
    }

    public function active(): JsonResponse
    {
        $theme = Theme::where('is_active', true)->first();

        return $theme ? ThemeResource::make($theme)->response() : response()->json(['data' => null]);
    }

    /** A single theme's full config, so the admin panel can preview it (colors/typography/buttons) before activating. */
    public function show(Theme $theme): JsonResponse
    {
        return ThemeResource::make($theme)->response();
    }

    public function store(StoreThemeRequest $request): JsonResponse
    {
        $theme = Theme::create($request->validated());
        AuditLogger::log('theme.created', $theme, after: $theme->toArray());

        return ThemeResource::make($theme)->response()->setStatusCode(201);
    }

    public function update(UpdateThemeRequest $request, Theme $theme): JsonResponse
    {
        $before = $theme->toArray();
        $theme->update($request->validated());

        AuditLogger::log('theme.updated', $theme, before: $before, after: $theme->fresh()->toArray());

        return ThemeResource::make($theme->fresh())->response();
    }

    public function activate(Request $request, Theme $theme): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $theme->update(['is_active' => true]);
        AuditLogger::log('theme.activated', $theme);

        return ThemeResource::make($theme)->response();
    }

    /** Turns the active theme off without activating another - the storefront falls back to its own default styling. */
    public function deactivate(Request $request, Theme $theme): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $theme->update(['is_active' => false]);
        AuditLogger::log('theme.deactivated', $theme);

        return ThemeResource::make($theme)->response();
    }
}
