<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with(['variants', 'media']);

        // Draft/archived products are staff-only; the public storefront
        // only ever sees active listings, regardless of what it asks for.
        if (! $request->user()?->hasAnyPermission(['products.view', 'products.manage'])) {
            $query->where('status', 'active');
        }

        // category_id is handled outside ApiQuery's plain equality filter so
        // that filtering by a parent category (e.g. "Cakes") also returns
        // products tagged into its subcategories - "Categories ->
        // Subcategories -> Products" needs to be a real, browsable
        // hierarchy, not just a display tree.
        if ($categoryId = $request->input('filter.category_id')) {
            $query->whereIn('category_id', $this->categoryAndDescendantIds((int) $categoryId));
        }

        // Price lives on variants, not the product row, so it can't be a
        // plain ApiQuery column filter - a product qualifies if any of its
        // variants falls inside the requested range.
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $query->whereHas('variants', function ($variants) use ($request): void {
                if ($request->filled('min_price')) {
                    $variants->where('price', '>=', (float) $request->query('min_price'));
                }
                if ($request->filled('max_price')) {
                    $variants->where('price', '<=', (float) $request->query('max_price'));
                }
            });
        }

        $products = ApiQuery::for($request, $query)
            ->filters(['status', 'is_featured'])
            ->sorts(['name', 'created_at'])
            ->searchable(['name', 'description', 'brand'])
            ->paginate();

        return ProductResource::collection($products)->response();
    }

    /**
     * Best sellers, for the homepage's "best_sellers" section. Ranked by
     * real sales data (summed quantity across non-cancelled/refunded order
     * items) rather than a proxy, with an optional admin override: staff
     * can pin an explicit product order via the `best_sellers`
     * homepage_sections row's `config.product_ids` (set through the
     * existing Homepage Manager - no new admin surface needed).
     */
    public function bestSellers(Request $request): JsonResponse
    {
        $limit = min($request->integer('limit', 8) ?: 8, 50);

        $products = $this->overriddenBestSellers($limit) ?? $this->computedBestSellers($limit);

        if ($products->isEmpty()) {
            // No sales data yet (fresh store) - fall back to featured
            // products, then to the newest active products, so the section
            // is never just empty before the first order comes in.
            $products = Product::query()->where('status', 'active')->where('is_featured', true)
                ->with(['variants', 'media'])->orderBy('name')->limit($limit)->get();
        }

        if ($products->isEmpty()) {
            $products = Product::query()->where('status', 'active')
                ->with(['variants', 'media'])->latest()->limit($limit)->get();
        }

        return ProductResource::collection($products)->response();
    }

    private function overriddenBestSellers(int $limit): ?Collection
    {
        $section = HomepageSection::query()->where('type', 'best_sellers')->first();

        if ($section === null || ! is_array($section->config)) {
            return null;
        }

        $productIds = $section->config['product_ids'] ?? null;

        if (! is_array($productIds) || $productIds === []) {
            return null;
        }

        $productIds = array_slice(array_map('intval', $productIds), 0, $limit);

        $products = Product::query()->whereIn('id', $productIds)->where('status', 'active')
            ->with(['variants', 'media'])->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $productIds, true))
            ->values();

        return $products->isEmpty() ? null : $products;
    }

    private function computedBestSellers(int $limit): Collection
    {
        // Deliberately .get()->pluck(), not Builder::pluck(): the latter
        // rewrites the SELECT clause down to just `product_id`, which would
        // drop the `total_quantity` alias that ORDER BY depends on.
        $rankedProductIds = OrderItem::query()
            ->whereNotNull('product_id')
            ->whereHas('order', fn ($orders) => $orders->whereNotIn('status', ['cancelled', 'refunded']))
            ->selectRaw('product_id, SUM(quantity) as total_quantity')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get()
            ->pluck('product_id')
            ->all();

        if ($rankedProductIds === []) {
            return collect();
        }

        return Product::query()->whereIn('id', $rankedProductIds)->where('status', 'active')
            ->with(['variants', 'media'])->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $rankedProductIds, true))
            ->values();
    }

    /** Arbitrary-depth descendant lookup so filtering by a parent category includes every subcategory's products. */
    private function categoryAndDescendantIds(int $categoryId): array
    {
        $ids = [$categoryId];
        $frontier = [$categoryId];

        while ($frontier !== []) {
            $children = Category::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        if ($product->status !== 'active' && ! $request->user()?->hasAnyPermission(['products.view', 'products.manage'])) {
            abort(404);
        }

        // Ratings, aggregated server-side across every approved review (not
        // just whatever page of reviews the client happens to have loaded -
        // the storefront review list only ever fetches the newest 10).
        $product->load(['variants', 'media', 'categories'])
            ->loadAvg(['reviews as rating_average' => fn ($reviews) => $reviews->where('is_approved', true)], 'rating')
            ->loadCount(['reviews as rating_count' => fn ($reviews) => $reviews->where('is_approved', true)]);

        return ProductResource::make($product)->response();
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $variants = $data['variants'];
        unset($data['variants']);

        $product = DB::transaction(function () use ($data, $variants) {
            $product = Product::create($data);

            foreach ($variants as $variant) {
                $product->variants()->create($variant);
            }

            return $product;
        });

        AuditLogger::log('product.created', $product, after: $product->toArray());

        return ProductResource::make($product->load('variants'))->response()->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $before = $product->toArray();
        $product->update($request->validated());

        AuditLogger::log('product.updated', $product, before: $before, after: $product->fresh()->toArray());

        return ProductResource::make($product->load('variants'))->response();
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['products.manage']), 403);

        $before = $product->toArray();
        $product->delete();

        AuditLogger::log('product.deleted', $product, before: $before);

        return response()->json(null, 204);
    }
}
