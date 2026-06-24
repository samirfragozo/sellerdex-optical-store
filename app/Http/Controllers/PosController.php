<?php

namespace App\Http\Controllers;

use App\Actions\RegisterSale;
use App\Http\Requests\StorePosSaleRequest;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Pos', [
            'products' => Product::query()->where('is_active', true)
                ->with('category:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'price', 'is_stockable', 'stock', 'product_category_id'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'is_stockable' => $p->is_stockable,
                    'stock' => $p->stock,
                    'category_name' => $p->category?->name,
                ]),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'name', 'surcharge_percent']),
            'customers' => Customer::query()->orderBy('name')
                ->limit(50)->get(['id', 'name', 'last_name', 'id_number']),
        ]);
    }

    public function store(StorePosSaleRequest $request, RegisterSale $registerSale): RedirectResponse
    {
        $data = $request->validated();

        // Create the customer inline when no existing one was selected.
        if (empty($data['customer_id'])) {
            $data['customer_id'] = Customer::create($data['customer'])->id;
        }

        $sale = $registerSale->handle($data, $request->user());

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
}
