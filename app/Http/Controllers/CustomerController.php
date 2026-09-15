<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'last_name' => $customer->last_name,
            'id_number' => $customer->id_number,
        ], 201);
    }
}
