<?php

namespace App\Http\Controllers\Api\V1\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreAddressRequest;
use App\Http\Requests\Customers\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Self-service: a customer manages their own address book. */
class AddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = $request->user()->customer()->firstOrFail();

        return AddressResource::collection($customer->addresses)->response();
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $customer = $request->user()->customer()->firstOrFail();
        $address = $customer->addresses()->create($request->validated());

        return AddressResource::make($address)->response()->setStatusCode(201);
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $address->update($request->validated());

        return AddressResource::make($address)->response();
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $address->delete();

        return response()->json(null, 204);
    }
}
