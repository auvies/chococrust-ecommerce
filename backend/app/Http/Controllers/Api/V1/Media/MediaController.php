<?php

namespace App\Http\Controllers\Api\V1\Media;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Resources\ProductMediaResource;
use App\Models\ProductMedia;
use App\Services\Audit\AuditLogger;
use App\Services\Media\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(private readonly MediaUploadService $uploads) {}

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $media = ProductMedia::create([
            'product_id' => $request->validated('product_id'),
            'product_variant_id' => $request->validated('product_variant_id'),
            'type' => 'image',
            'url' => $this->uploads->store($request->file('file'), 'media'),
            'alt_text' => $request->validated('alt_text'),
            'is_primary' => $request->boolean('is_primary'),
        ]);

        AuditLogger::log('media.uploaded', $media, after: $media->toArray());

        return ProductMediaResource::make($media)->response()->setStatusCode(201);
    }

    public function destroy(Request $request, ProductMedia $media): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['products.manage', 'content.manage']), 403);

        $before = $media->toArray();
        $media->delete();

        AuditLogger::log('media.deleted', null, before: $before);

        return response()->json(null, 204);
    }
}
