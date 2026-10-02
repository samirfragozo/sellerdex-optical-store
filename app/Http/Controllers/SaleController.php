<?php

namespace App\Http\Controllers;

use App\Actions\RegisterSale;
use App\Enums\ReadinessSeverity;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\SaleItemLensConfig;
use App\Support\Readiness\ReadinessIssue;
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

        $lensBlockers = collect($request->user()->company->saleReadiness())
            ->filter(fn (ReadinessIssue $issue): bool => $issue->severity === ReadinessSeverity::Blocking && $issue->scope === 'lens');

        if (! empty($request->input('armados')) && $lensBlockers->isNotEmpty()) {
            return response()->json([
                'message' => __('app.readiness.lens_sale_blocked', ['reasons' => $lensBlockers->pluck('message')->join(' ')]),
            ], 422);
        }

        $data = $request->validated();

        // Create the customer inline when new customer data was provided.
        if (empty($data['customer_id']) && ! empty($data['customer']['name'])) {
            $data['customer_id'] = Customer::create($data['customer'])->id;
        }

        $sale = $registerSale->handle($data, $request->user());

        return response()->json([
            'id' => $sale->id,
            'number' => $sale->number,
            'invoice_url' => route('documents.invoice', $sale),
            'invoice_pdf_url' => route('documents.invoice.pdf', $sale),
            // One printable formula per distinct prescription, named after its patient.
            'formulas' => $sale->lensConfigs()->with('patient')->whereNotNull('prescription_id')->get()
                ->unique('prescription_id')
                ->map(fn (SaleItemLensConfig $config): array => [
                    'url' => route('documents.formula', $config->prescription_id),
                    'patient_name' => $config->patient?->full_name ?? '',
                ])
                ->values(),
            'has_pending_lab_order' => $sale->hasPendingLensWork(),
        ]);
    }
}
