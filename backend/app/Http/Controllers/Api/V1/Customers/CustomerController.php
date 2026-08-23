<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['customers.view', 'customers.manage']), 403);

        $query = Customer::query()->with(['user', 'tags']);

        // Not a plain ApiQuery column filter - tags live on a pivot, not the
        // customers table itself.
        if ($tagId = $request->input('filter.tag_id')) {
            $query->whereHas('tags', fn ($tags) => $tags->where('customer_tags.id', (int) $tagId));
        }

        $customers = ApiQuery::for($request, $query)
            ->filters(['marketing_opt_in'])
            ->sorts(['created_at'])
            ->searchable(['phone'])
            ->paginate();

        return CustomerResource::collection($customers)->response();
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return CustomerResource::make($customer->load(['user', 'addresses', 'tags']))->response();
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'date_of_birth' => ['sometimes', 'nullable', 'date'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            // Staff-only field - a customer editing their own profile can't set it.
            'notes' => ['sometimes', 'string'],
        ]);

        if (! $request->user()->hasAnyPermission(['customers.manage'])) {
            unset($data['notes']);
        }

        $before = $customer->toArray();
        $customer->update($data);

        if (isset($data['notes'])) {
            AuditLogger::log('customer.updated', $customer, before: $before, after: $customer->fresh()->toArray());
        }

        return CustomerResource::make($customer->fresh(['user']))->response();
    }
}
