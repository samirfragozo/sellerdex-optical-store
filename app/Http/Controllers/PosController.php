<?php

namespace App\Http\Controllers;

use App\Enums\LensType;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PosController extends Controller
{
    public function index(Request $request): Response
    {
        $withCatalogRelations = [
            'category:id,name,key',
            'optionGroups' => fn ($q) => $q->where('option_groups.is_active', true),
            'optionGroups.options' => fn ($q) => $q->where('is_active', true),
            'variants' => fn ($q) => $q->where('is_active', true),
            'variants.variantOptions',
        ];
        $catalogColumns = ['id', 'name', 'price', 'cost', 'tax_rate', 'is_stockable', 'stock', 'product_category_id', 'specs'];

        $products = Product::query()->where('is_active', true)
            ->where('is_pos_selectable', true)
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('category'), fn ($q) => $q->whereHas(
                'category',
                fn ($cq) => $cq->where('key', $request->string('category')),
            ))
            // Lens products always carry option groups and must go through the
            // armado wizard (see $armadoProducts below) to enforce the
            // prescription requirement — never click-to-added from this grid.
            // Frames with option groups (type/material variants) are allowed
            // through: a frame can be sold on its own, unlike a lens.
            ->where(fn ($q) => $q
                ->whereDoesntHave('optionGroups', fn ($oq) => $oq->where('option_groups.is_active', true))
                ->orWhereHas('category', fn ($cq) => $cq->where('key', 'frame')))
            ->with($withCatalogRelations)
            ->orderBy('name')
            ->paginate(20, $catalogColumns)
            ->withQueryString()
            ->through(fn (Product $p) => $this->mapCatalogProduct($p));

        // Frame and lens products keep their option groups regardless of the
        // exclusion above: the armado wizard (StepFrame/StepLens) needs the
        // full, unpaginated set to let sellers pick a color/filter variant.
        $armadoProducts = Product::query()->where('is_active', true)
            ->where('is_pos_selectable', true)
            ->whereHas('category', fn ($cq) => $cq->whereIn('key', ['frame', 'lens']))
            ->with($withCatalogRelations)
            ->orderBy('name')
            ->get($catalogColumns)
            ->map(fn (Product $p) => $this->mapCatalogProduct($p))
            ->values();

        return Inertia::render('Pos', [
            'products' => [
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                    'total' => $products->total(),
                ],
            ],
            'armadoProducts' => $armadoProducts,
            'categories' => ProductCategory::query()->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'key']),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)
                ->orderBy('sort_order')->get(['id', 'name', 'surcharge_percent']),
            'lensTypes' => LensType::options(),
            'prescriptions' => Prescription::query()
                ->orderByDesc('exam_date')
                ->limit(200)
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

    /**
     * Shape a Product (with its category/optionGroups/variants eager-loaded)
     * into the array the Pos page's catalog and armado pickers expect.
     *
     * @return array<string, mixed>
     */
    private function mapCatalogProduct(Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'price' => $p->price,
            'cost' => $p->cost,
            'tax_rate' => (float) $p->tax_rate,
            'is_stockable' => $p->is_stockable,
            'stock' => $p->stock,
            'category_name' => $p->category?->name,
            'category_key' => $p->category?->key,
            'specs' => $p->specs,
            'option_groups' => $p->optionGroups
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'is_required' => $g->is_required,
                    'options' => $g->options->map(fn ($o) => [
                        'id' => $o->id,
                        'name' => $o->name,
                        'price' => $o->price,
                        'cost' => $o->cost,
                    ])->values(),
                ]),
            'variants' => $p->variants
                ->map(fn (Product $v) => [
                    'id' => $v->id,
                    'price' => $v->price,
                    'cost' => $v->cost,
                    'stock' => $v->stock,
                    'is_stockable' => $v->is_stockable,
                    'option_ids' => $v->variantOptions->pluck('id')->sort()->values(),
                ])
                ->values(),
        ];
    }
}
