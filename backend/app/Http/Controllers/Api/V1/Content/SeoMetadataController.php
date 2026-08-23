<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeoMetadataResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\SeoMetadata;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoMetadataController extends Controller
{
    /** Deliberately allow-listed - never an arbitrary morph type from the client. */
    private const SEOABLE_TYPES = ['category' => Category::class, 'product' => Product::class];

    public function show(string $type, int $id): JsonResponse
    {
        $model = $this->resolve($type, $id);
        $seo = $model->seoMetadata;

        return $seo ? SeoMetadataResource::make($seo)->response() : response()->json(['data' => null]);
    }

    public function upsert(Request $request, string $type, int $id): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['content.manage']), 403);
        $model = $this->resolve($type, $id);

        $data = $request->validate([
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_keywords' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $seo = SeoMetadata::updateOrCreate(
            ['seoable_type' => $model->getMorphClass(), 'seoable_id' => $model->id],
            $data,
        );

        AuditLogger::log('seo_metadata.updated', $seo, after: $seo->toArray());

        return SeoMetadataResource::make($seo)->response();
    }

    private function resolve(string $type, int $id): Category|Product
    {
        abort_unless(isset(self::SEOABLE_TYPES[$type]), 404);

        return self::SEOABLE_TYPES[$type]::findOrFail($id);
    }
}
