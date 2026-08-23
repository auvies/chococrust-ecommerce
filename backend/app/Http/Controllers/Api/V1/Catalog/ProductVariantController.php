<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductVariantRequest;
use App\Http\Requests\Products\UpdateProductVariantRequest;
use App\Http\Resources\ProductVariantResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Phase 02 put price/SKU/stock at the variant level, but Phase 04's
 * ProductController never exposed a way to add/edit/remove a variant after
 * the product itself was created - a real gap for "manage business
 * operations without code changes" (Phase 06), since price changes are one
 * of the most common admin actions. Gated the same as the parent product
 * (`products.manage`), audit-logged the same way.
 */
class ProductVariantController extends Controller
{
    public function store(StoreProductVariantRequest $request, Product $product): JsonResponse
    {
        $variant = $product->variants()->create($request->validated());

        AuditLogger::log('product_variant.created', $variant, after: $variant->toArray());

        return ProductVariantResource::make($variant)->response()->setStatusCode(201);
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($variant->product_id === $product->id, 404);

        $before = $variant->toArray();
        $variant->update($request->validated());

        AuditLogger::log('product_variant.updated', $variant, before: $before, after: $variant->fresh()->toArray());

        return ProductVariantResource::make($variant)->response();
    }

    public function destroy(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['products.manage']), 403);
        abort_unless($variant->product_id === $product->id, 404);

        // Phase 02 invariant: every product needs at least one variant.
        if ($product->variants()->count() <= 1) {
            throw ValidationException::withMessages(['variant' => 'A product must keep at least one variant.']);
        }

        $before = $variant->toArray();
        $variant->delete();

        AuditLogger::log('product_variant.deleted', null, before: $before);

        return response()->json(null, 204);
    }
}
