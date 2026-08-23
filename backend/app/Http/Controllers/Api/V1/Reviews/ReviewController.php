<?php

namespace App\Http\Controllers\Api\V1\Reviews;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiQuery;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /** Cross-product moderation queue for the Review Manager admin screen - reviews.moderate only. */
    public function all(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['reviews.moderate']), 403);

        $reviews = ApiQuery::for($request, Review::query())
            ->filters(['is_approved', 'rating', 'product_id'])
            ->sorts(['created_at', 'rating'])
            ->paginate();

        return ReviewResource::collection($reviews)->response();
    }

    public function index(Request $request, Product $product): JsonResponse
    {
        $query = $product->reviews();

        // Staff moderating the queue sees everything; the public storefront
        // only ever sees approved reviews.
        if (! $request->user()?->hasAnyPermission(['reviews.moderate'])) {
            $query->where('is_approved', true);
        }

        $reviews = ApiQuery::for($request, $query)
            ->filters(['is_approved', 'rating'])
            ->sorts(['created_at', 'rating'])
            ->paginate();

        return ReviewResource::collection($reviews)->response();
    }

    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        $customer = $request->user()->customer()->firstOrFail();
        $data = $request->validated();

        $isVerified = false;
        if (! empty($data['order_item_id'])) {
            $orderItem = OrderItem::with('order')->find($data['order_item_id']);
            $isVerified = $orderItem
                && $orderItem->product_id === $product->id
                && $orderItem->order?->customer_id === $customer->id;
        }

        try {
            $review = $product->reviews()->create($data + [
                'customer_id' => $customer->id,
                'is_verified_purchase' => $isVerified,
                'is_approved' => false,
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages(['product_id' => 'You have already reviewed this product.']);
        }

        return ReviewResource::make($review)->response()->setStatusCode(201);
    }

    public function approve(Request $request, Review $review): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['reviews.moderate']), 403);

        $review->update(['is_approved' => true, 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        AuditLogger::log('review.approved', $review);

        return ReviewResource::make($review)->response();
    }

    public function reject(Request $request, Review $review): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['reviews.moderate']), 403);

        $review->delete();
        AuditLogger::log('review.rejected', $review);

        return response()->json(null, 204);
    }
}
