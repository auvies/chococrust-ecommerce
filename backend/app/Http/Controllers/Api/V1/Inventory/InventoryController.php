<?php

namespace App\Http\Controllers\Api\V1\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\InventoryService;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['inventory.manage', 'products.view']), 403);

        $records = ApiQuery::for($request, Inventory::query()->with('variant'))
            ->filters(['product_variant_id', 'location'])
            ->sorts(['quantity_on_hand', 'updated_at'])
            ->paginate();

        return InventoryResource::collection($records)->response();
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['inventory.manage']), 403);

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'location' => ['sometimes', 'string', 'max:255'],
            'quantity_on_hand' => ['sometimes', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
        ]);

        $inventory = Inventory::create($data + ['location' => $data['location'] ?? 'main']);

        AuditLogger::log('inventory.created', $inventory, after: $inventory->toArray());

        return InventoryResource::make($inventory)->response()->setStatusCode(201);
    }

    /**
     * Delta adjustment (never a direct "set to X") so every stock change
     * carries a reason and lands in inventory history - the same discipline
     * as payment status changes (CLAUDE.md §15). Routed through
     * InventoryService so a manual adjustment is guarded by the same
     * row-lock every reservation/sale change uses, and lands in
     * `inventory_movements` alongside them.
     */
    public function adjust(AdjustInventoryRequest $request, Inventory $inventory): JsonResponse
    {
        $before = $inventory->toArray();

        $inventory = $this->inventory->adjust(
            $inventory,
            $request->validated('delta'),
            $request->validated('reason'),
            $request->user()->id,
        );

        AuditLogger::log(
            'inventory.adjusted',
            $inventory,
            before: $before,
            after: array_merge($inventory->toArray(), ['reason' => $request->validated('reason')]),
        );

        return InventoryResource::make($inventory)->response();
    }

    /** "Inventory history" - the append-only movement ledger for one inventory row's variant, newest first. */
    public function history(Request $request, Inventory $inventory): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['inventory.manage', 'products.view']), 403);

        $query = InventoryMovement::query()
            ->where('product_variant_id', $inventory->product_variant_id)
            ->orderByDesc('created_at');

        $movements = ApiQuery::for($request, $query)
            ->sorts(['created_at'])
            ->defaultPerPage(50)
            ->paginate();

        return InventoryMovementResource::collection($movements)->response();
    }
}
