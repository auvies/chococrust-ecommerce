<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ReorderHeroBannersRequest;
use App\Http\Requests\Content\UploadHeroBannerImageRequest;
use App\Http\Resources\HeroBannerResource;
use App\Models\HeroBanner;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HeroBannerController extends Controller
{
    public function __construct(private readonly MediaUploadService $uploads) {}

    public function index(Request $request): JsonResponse
    {
        $isStaff = $request->user()?->hasAnyPermission(['content.manage']);

        // Archived (soft-deleted) banners are staff-only, and only ever
        // shown on request - the default view (and every public view)
        // never includes them.
        $query = $isStaff && $request->boolean('archived')
            ? HeroBanner::onlyTrashed()
            : HeroBanner::query();

        $query->orderBy('sort_order');

        if (! $isStaff) {
            $query->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
        }

        return HeroBannerResource::collection($query->get())->response();
    }

    public function show(Request $request, HeroBanner $heroBanner): JsonResponse
    {
        if (! $heroBanner->is_active && ! $request->user()?->hasAnyPermission(['content.manage'])) {
            abort(404);
        }

        return HeroBannerResource::make($heroBanner)->response();
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            // Nullable on create (unlike the original design): a banner is
            // typically created text-first, then given its image via the
            // dedicated, secure uploadImage() endpoint below - matching the
            // existing product-creation-then-media-upload flow rather than
            // forcing a client-supplied URL just to satisfy validation.
            'image_url' => ['nullable', 'url', 'max:2048'],
            'mobile_image_url' => ['nullable', 'url', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'cta_text' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $banner = HeroBanner::create($data);
        AuditLogger::log('hero_banner.created', $banner, after: $banner->toArray());

        return HeroBannerResource::make($banner)->response()->setStatusCode(201);
    }

    public function update(Request $request, HeroBanner $heroBanner): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'image_url' => ['sometimes', 'url', 'max:2048'],
            'mobile_image_url' => ['nullable', 'url', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'cta_text' => ['nullable', 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $heroBanner->update($data);
        AuditLogger::log('hero_banner.updated', $heroBanner);

        return HeroBannerResource::make($heroBanner)->response();
    }

    /**
     * Secure file upload (CLAUDE.md §12) replacing the desktop and/or
     * mobile image - re-uploading through this same endpoint IS how an
     * admin "replaces" an image; there's no separate replace action.
     */
    public function uploadImage(UploadHeroBannerImageRequest $request, HeroBanner $heroBanner): JsonResponse
    {
        $before = $heroBanner->only(['image_url', 'mobile_image_url', 'alt_text']);
        $updates = [];

        if ($request->hasFile('desktop_image')) {
            $updates['image_url'] = $this->uploads->store($request->file('desktop_image'), 'hero-banners');
        }
        if ($request->hasFile('mobile_image')) {
            $updates['mobile_image_url'] = $this->uploads->store($request->file('mobile_image'), 'hero-banners');
        }
        if ($request->filled('alt_text')) {
            $updates['alt_text'] = $request->validated('alt_text');
        }

        $heroBanner->update($updates);

        AuditLogger::log('hero_banner.image_uploaded', $heroBanner, before: $before, after: $heroBanner->fresh()->only(['image_url', 'mobile_image_url', 'alt_text']));

        return HeroBannerResource::make($heroBanner->fresh())->response();
    }

    /** Drag-and-drop reordering: the client sends the full desired id order, sort_order becomes each id's position. */
    public function reorder(ReorderHeroBannersRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('order') as $index => $id) {
                HeroBanner::whereKey($id)->update(['sort_order' => $index]);
            }
        });

        AuditLogger::log('hero_banner.reordered', null, after: ['order' => $request->validated('order')]);

        return HeroBannerResource::collection(HeroBanner::orderBy('sort_order')->get())->response();
    }

    /** Soft-delete = archive (CLAUDE.md-style recoverability), not a hard delete - see restore() below. */
    public function destroy(Request $request, HeroBanner $heroBanner): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $heroBanner->delete();
        AuditLogger::log('hero_banner.archived', null, before: $heroBanner->toArray());

        return response()->json(null, 204);
    }

    public function restore(Request $request, int $heroBanner): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);

        $banner = HeroBanner::onlyTrashed()->findOrFail($heroBanner);
        $banner->restore();

        AuditLogger::log('hero_banner.restored', $banner, after: $banner->toArray());

        return HeroBannerResource::make($banner)->response();
    }
}
