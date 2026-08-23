<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreCustomerTagRequest;
use App\Http\Resources\CustomerTagResource;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTagController extends Controller
{
    /** The full tag catalog, for an admin's "assign tag" dropdown - not scoped to any one customer. */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['customers.view', 'customers.manage']), 403);

        return CustomerTagResource::collection(CustomerTag::query()->orderBy('name')->get())->response();
    }

    public function store(StoreCustomerTagRequest $request): JsonResponse
    {
        $tag = CustomerTag::create($request->validated());

        AuditLogger::log('customer_tag.created', $tag, after: $tag->toArray());

        return CustomerTagResource::make($tag)->response()->setStatusCode(201);
    }

    public function destroy(Request $request, CustomerTag $tag): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['customers.manage']), 403);

        AuditLogger::log('customer_tag.deleted', $tag, before: $tag->toArray());
        $tag->delete();

        return response()->json(null, 204);
    }

    public function attach(Request $request, Customer $customer, CustomerTag $tag): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['customers.manage']), 403);

        $customer->tags()->syncWithoutDetaching([$tag->id => ['assigned_by' => $request->user()->id, 'created_at' => now()]]);

        AuditLogger::log('customer.tagged', $customer, after: ['tag_id' => $tag->id, 'tag_name' => $tag->name]);

        return CustomerTagResource::collection($customer->tags()->get())->response();
    }

    public function detach(Request $request, Customer $customer, CustomerTag $tag): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['customers.manage']), 403);

        $customer->tags()->detach($tag->id);

        AuditLogger::log('customer.untagged', $customer, before: ['tag_id' => $tag->id, 'tag_name' => $tag->name]);

        return CustomerTagResource::collection($customer->tags()->get())->response();
    }
}
