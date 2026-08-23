<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomepageSectionResource;
use App\Models\HomepageSection;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomepageSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = HomepageSection::query()->orderBy('sort_order');

        if (! $request->user()?->hasAnyPermission(['content.manage'])) {
            $query->where('is_active', true);
        }

        return HomepageSectionResource::collection($query->get())->response();
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $data = $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $section = HomepageSection::create($data);
        AuditLogger::log('homepage_section.created', $section, after: $section->toArray());

        return HomepageSectionResource::make($section)->response()->setStatusCode(201);
    }

    public function update(Request $request, HomepageSection $homepageSection): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config' => ['sometimes', 'nullable', 'array'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $homepageSection->update($data);
        AuditLogger::log('homepage_section.updated', $homepageSection);

        return HomepageSectionResource::make($homepageSection)->response();
    }

    public function destroy(Request $request, HomepageSection $homepageSection): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $homepageSection->delete();
        AuditLogger::log('homepage_section.deleted', null, before: $homepageSection->toArray());

        return response()->json(null, 204);
    }
}
