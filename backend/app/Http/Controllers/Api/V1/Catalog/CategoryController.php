<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Http\Requests\Categories\UploadCategoryImageRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploadService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public read (product listing needs categories - CLAUDE.md §3's own
 * example of a deliberately public route); categories.manage-gated writes.
 */
class CategoryController extends Controller
{
    public function __construct(private readonly MediaUploadService $uploads) {}

    public function index(Request $request): JsonResponse
    {
        $categories = ApiQuery::for($request, Category::query()->with('children'))
            ->filters(['parent_id', 'is_active'])
            ->sorts(['name', 'sort_order', 'created_at'])
            ->searchable(['name', 'description'])
            ->defaultPerPage(50)
            ->paginate();

        return CategoryResource::collection($categories)->response();
    }

    public function show(Category $category): JsonResponse
    {
        return CategoryResource::make($category->load('children'))->response();
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        AuditLogger::log('category.created', $category, after: $category->toArray());

        return CategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $before = $category->toArray();
        $category->update($request->validated());

        AuditLogger::log('category.updated', $category, before: $before, after: $category->fresh()->toArray());

        return CategoryResource::make($category)->response();
    }

    /**
     * A dedicated upload endpoint rather than accepting a raw `image_url`
     * string here - a client-supplied URL is never "secure image upload"
     * (CLAUDE.md §12: MIME-validated server-side, size-capped, renamed to a
     * generated identifier). Re-uploading is how an admin replaces the
     * image; there's no separate "replace" action.
     */
    public function uploadImage(UploadCategoryImageRequest $request, Category $category): JsonResponse
    {
        $before = $category->only(['image_url', 'alt_text']);

        $category->update([
            'image_url' => $this->uploads->store($request->file('file'), 'categories'),
            'alt_text' => $request->validated('alt_text') ?? $category->alt_text,
        ]);

        AuditLogger::log('category.image_uploaded', $category, before: $before, after: $category->fresh()->only(['image_url', 'alt_text']));

        return CategoryResource::make($category->fresh())->response();
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['categories.manage']), 403);

        $before = $category->toArray();
        $category->delete();

        AuditLogger::log('category.deleted', $category, before: $before);

        return response()->json(null, 204);
    }
}
