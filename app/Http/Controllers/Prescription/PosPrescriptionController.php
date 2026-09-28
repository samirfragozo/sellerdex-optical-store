<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrescriptionRequest;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;

class PosPrescriptionController extends Controller
{
    /**
     * Save an external prescription from the POS, before the lens sale that
     * needs it. Returns the same option shape the POS prescriptions prop
     * uses, so the frontend can add it to the list without a reload.
     */
    public function store(StorePrescriptionRequest $request): JsonResponse
    {
        $prescription = Prescription::create([
            ...$request->validated(),
            'attachment' => $request->file('attachment')?->store('prescriptions', 'local'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($prescription->toPosOption(), 201);
    }
}
