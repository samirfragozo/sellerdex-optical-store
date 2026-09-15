<?php

namespace App\Http\Controllers;

use App\Actions\RegisterSale;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function store(StoreSaleRequest $request, RegisterSale $registerSale): JsonResponse
    {
        if (CashRegisterSession::openFor($request->user()) === null) {
            return response()->json([
                'message' => __('app.pos.cash_session.required_notice'),
            ], 403);
        }

        $data = $request->validated();

        // Create the customer inline when new customer data was provided.
        if (empty($data['customer_id']) && ! empty($data['customer']['name'])) {
            $data['customer_id'] = Customer::create($data['customer'])->id;
        }

        // Create the prescription inline when new exam data was provided.
        $createdPrescriptionId = null;
        if (empty($data['prescription_id']) && ! empty($data['prescription']['exam_date'])) {
            $createdPrescriptionId = Prescription::create([
                ...$data['prescription'],
                'customer_id' => $data['customer_id'],
                'created_by' => $request->user()->id,
            ])->id;
            $data['prescription_id'] = $createdPrescriptionId;
        }

        $sale = $registerSale->handle($data, $request->user());

        // Link a freshly created prescription back to its sale.
        if ($createdPrescriptionId !== null) {
            Prescription::whereKey($createdPrescriptionId)->update(['sale_id' => $sale->id]);
        }

        return response()->json([
            'id' => $sale->id,
            'number' => $sale->number,
            'prescription_id' => $sale->prescription_id,
            'invoice_url' => route('documents.invoice', $sale),
            'invoice_pdf_url' => route('documents.invoice.pdf', $sale),
            'formula_url' => $sale->prescription_id ? route('documents.formula', $sale->prescription_id) : null,
            'has_pending_lab_order' => $sale->hasPendingLensWork(),
        ]);
    }
}
