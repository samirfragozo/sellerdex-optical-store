<?php

namespace App\Http\Controllers;

use App\Actions\RegisterSale;
use App\Enums\LensType;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Product;
use App\Support\Optics\LensRecommender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(): Response
    {
        $customers = Customer::query()->orderBy('name')
            ->limit(50)->get(['id', 'name', 'last_name', 'id_number']);

        return Inertia::render('Pos', [
            'products' => Product::query()->where('is_active', true)
                ->where('is_pos_selectable', true)
                ->with('category:id,name,key')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'is_stockable', 'stock', 'product_category_id', 'specs'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'is_stockable' => $p->is_stockable,
                    'stock' => $p->stock,
                    'category_name' => $p->category?->name,
                    'category_key' => $p->category?->key,
                    'specs' => $p->specs,
                ]),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'name', 'surcharge_percent']),
            'customers' => $customers,
            'lensTypes' => LensType::options(),
            'prescriptions' => Prescription::query()
                ->whereIn('customer_id', $customers->pluck('id'))
                ->orderByDesc('exam_date')
                ->get(['id', 'customer_id', 'exam_date', 'od_sphere', 'os_sphere', 'lens_type'])
                ->map(fn (Prescription $p) => [
                    'id' => $p->id,
                    'customer_id' => $p->customer_id,
                    'exam_date' => $p->exam_date?->toDateString(),
                    'lens_type' => $p->lens_type?->value,
                    'summary' => sprintf('OD %s / OS %s', $p->od_sphere ?? '—', $p->os_sphere ?? '—'),
                ]),
        ]);
    }

    public function store(StorePosSaleRequest $request, RegisterSale $registerSale): RedirectResponse
    {
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

        return back()
            ->with('success', __('app.pos.created', ['number' => $sale->number]))
            ->with('createdSale', [
                'id' => $sale->id,
                'number' => $sale->number,
                'prescription_id' => $sale->prescription_id,
                'invoice_url' => route('documents.invoice', $sale),
                'invoice_pdf_url' => route('documents.invoice.pdf', $sale),
                'formula_url' => $sale->prescription_id ? route('documents.formula', $sale->prescription_id) : null,
            ]);
    }

    public function lensRecommendation(Request $request, LensRecommender $recommender): JsonResponse
    {
        $prescription = (array) $request->input('prescription', []);
        $chosen = (array) $request->input('chosen', []);

        return response()->json([
            'recommended' => $recommender->recommend($prescription),
            'warnings' => $recommender->warningsFor($prescription, $chosen),
        ]);
    }
}
