<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PrescriptionsController extends Controller
{
    /**
     * Every prescription of one customer, newest exam first, as POS options.
     * The POS asks when its armado wizard picks a patient, so a returning
     * patient's older prescriptions are offered no matter how many the shop has.
     */
    public function __invoke(Customer $customer): JsonResponse
    {
        Gate::authorize('viewAny', Prescription::class);

        return response()->json(
            $customer->prescriptions()
                ->orderByDesc('exam_date')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Prescription $prescription): array => $prescription->toPosOption()),
        );
    }
}
