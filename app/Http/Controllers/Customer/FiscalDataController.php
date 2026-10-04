<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FiscalDataController extends Controller
{
    /** What the checkout prefills when the customer asks for a factura electrónica, and what is still missing. */
    public function __invoke(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);

        return response()->json([
            'person_type' => $customer->person_type?->value,
            'email' => $customer->email,
            'dane_municipality_code' => $customer->dane_municipality_code,
            'fiscal_responsibilities' => $customer->fiscal_responsibilities ?? [],
            'missing' => $customer->missingFiscalData(),
        ]);
    }
}
