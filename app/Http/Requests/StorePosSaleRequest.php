<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Enums\LensType;
use App\Enums\SaleDocumentType;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Sale;
use App\Rules\Diopter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePosSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Sale::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'max:255'],
            'customer.last_name' => ['required_with:customer', 'string', 'max:255'],
            'customer.document_type' => ['required_with:customer', Rule::enum(DocumentType::class)],
            'customer.id_number' => ['required_with:customer', 'string', 'max:255'],
            'customer.phone' => ['required_with:customer', 'string', 'max:255'],
            'customer.address' => ['nullable', 'string', 'max:255'],
            'customer.city' => ['nullable', 'string', 'max:255'],
            'customer.birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.notes' => ['nullable', 'string', 'max:1000'],
            'document_type' => ['required', Rule::enum(SaleDocumentType::class)],
            'prescription_id' => ['nullable', 'exists:prescriptions,id'],
            'prescription' => ['nullable', 'array'],
            'prescription.exam_date' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subYears(2)->toDateString()],
            'prescription.lens_type' => ['nullable', Rule::enum(LensType::class)],
            'prescription.diagnosis' => ['nullable', 'string', 'max:1000'],
            'prescription.od_sphere' => ['nullable', new Diopter(-20, 20)],
            'prescription.od_cylinder' => ['nullable', new Diopter(-10, 10)],
            'prescription.od_axis' => ['nullable', 'integer', 'between:1,180'],
            'prescription.od_add' => ['nullable', new Diopter(0.25, 4)],
            'prescription.od_pd' => ['nullable', 'numeric', 'between:20,40'],
            'prescription.os_sphere' => ['nullable', new Diopter(-20, 20)],
            'prescription.os_cylinder' => ['nullable', new Diopter(-10, 10)],
            'prescription.os_axis' => ['nullable', 'integer', 'between:1,180'],
            'prescription.os_add' => ['nullable', new Diopter(0.25, 4)],
            'prescription.os_pd' => ['nullable', 'numeric', 'between:20,40'],
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'tip_percent' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string'],
            'armados' => ['nullable', 'array'],
            'armados.*.lens.product_id' => ['required', 'exists:products,id'],
            'armados.*.lens.description' => ['required', 'string', 'max:255'],
            'armados.*.lens.quantity' => ['nullable', 'integer', 'min:1'],
            'armados.*.lens.unit_price' => ['required', 'integer', 'min:0'],
            'armados.*.lens.unit_cost' => ['nullable', 'integer', 'min:0'],
            'armados.*.lens.option_ids' => ['nullable', 'array'],
            'armados.*.lens.option_ids.*' => ['integer', 'exists:options,id'],
            'armados.*.frame' => ['nullable', 'array'],
            'armados.*.frame.product_id' => ['nullable', 'exists:products,id'],
            'armados.*.frame.description' => ['nullable', 'string', 'max:255'],
            'armados.*.frame.unit_price' => ['nullable', 'integer', 'min:0'],
            'armados.*.frame.unit_cost' => ['nullable', 'integer', 'min:0'],
            'armados.*.own_frame' => ['boolean'],
            'armados.*.combo' => ['nullable', 'array'],
            'armados.*.combo.with_exam' => ['boolean'],
            'armados.*.combo.include_liquid' => ['boolean'],
            'armados.*.combo.forro' => ['nullable', 'in:small,large'],
            'products' => ['nullable', 'array'],
            'products.*.product_id' => ['nullable', 'exists:products,id'],
            'products.*.description' => ['required', 'string', 'max:255'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.unit_price' => ['required', 'integer', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'surcharge_percent' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Enforce the optical rules that depend on the cart contents: selling a
     * lens requires both a customer and a prescription (existing or new).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (empty($this->input('armados')) && empty($this->input('products'))) {
                $validator->errors()->add('armados', 'Agrega al menos un lente o un producto a la venta.');
            }

            foreach ((array) $this->input('armados', []) as $index => $armado) {
                if (empty($armado['frame'])) {
                    continue;
                }
                if (blank($armado['frame']['description'] ?? null)) {
                    $validator->errors()->add("armados.{$index}.frame.description", 'La descripción de la montura es obligatoria.');
                }
                if (! isset($armado['frame']['unit_price'])) {
                    $validator->errors()->add("armados.{$index}.frame.unit_price", 'Indica el precio de la montura.');
                }
            }

            // The sum of every split payment cannot exceed the sale total.
            $paymentsTotal = collect($this->input('payments', []))->sum(fn ($p) => (int) ($p['amount'] ?? 0));
            if ($paymentsTotal > $this->saleTotal()) {
                $validator->errors()->add('payments', 'La suma de los abonos no puede superar el total de la venta.');
            }

            // An eye's axis and cylinder must be provided together.
            foreach (['od', 'os'] as $eye) {
                $cylinder = $this->input("prescription.{$eye}_cylinder");
                $axis = $this->input("prescription.{$eye}_axis");

                if (filled($cylinder) && blank($axis)) {
                    $validator->errors()->add("prescription.{$eye}_axis", 'Indica el eje cuando hay cilindro.');
                }

                if (filled($axis) && blank($cylinder)) {
                    $validator->errors()->add("prescription.{$eye}_cylinder", 'Indica el cilindro cuando hay eje.');
                }
            }

            if (! $this->cartHasLens()) {
                return;
            }

            if (empty($this->input('customer_id')) && empty($this->input('customer.name'))) {
                $validator->errors()->add('customer', 'La venta de lentes formulados requiere un cliente.');
            }

            $hasExisting = ! empty($this->input('prescription_id'));
            $hasNew = ! empty($this->input('prescription.exam_date'));

            if (! $hasExisting && ! $hasNew) {
                $validator->errors()->add('prescription', 'La venta de lentes formulados requiere una prescripción.');
            }

            if ($hasExisting && $this->filled('customer_id')) {
                $belongsToCustomer = Prescription::query()
                    ->whereKey($this->input('prescription_id'))
                    ->where('customer_id', $this->input('customer_id'))
                    ->exists();

                if (! $belongsToCustomer) {
                    $validator->errors()->add('prescription_id', 'La prescripción no pertenece al cliente seleccionado.');
                }
            }
        });
    }

    /** A lens sale is any sale that carries at least one armado. */
    protected function cartHasLens(): bool
    {
        return ! empty($this->input('armados'));
    }

    /**
     * Compute an estimated sale total from the submitted armados/products, discount,
     * tip, tax and surcharge — used only to bound the sum of split payments.
     *
     * Armado lens/frame lines and addition lines are never taxed (RegisterSale
     * stamps tax_amount = 0 on them — lenses/frames are tax-exempt in this
     * business), so only loose `products.*` lines contribute tax here, matching
     * what RegisterSale actually computes. This mirrors Sale::recalculateTotals().
     */
    protected function saleTotal(): int
    {
        $armados = collect($this->input('armados', []))->sum(function ($armado): int {
            $lens = (int) ($armado['lens']['unit_price'] ?? 0) * (int) ($armado['lens']['quantity'] ?? 1);
            $frame = empty($armado['own_frame']) && ! empty($armado['frame'])
                ? (int) ($armado['frame']['unit_price'] ?? 0) * (int) ($armado['frame']['quantity'] ?? 1)
                : 0;

            return $lens + $frame;
        });

        $productLines = collect($this->input('products', []));
        $products = $productLines->sum(fn ($p): int => (int) ($p['quantity'] ?? 0) * (int) ($p['unit_price'] ?? 0));

        $taxRates = Product::query()
            ->whereIn('id', $productLines->pluck('product_id')->filter()->unique())
            ->pluck('tax_rate', 'id');

        $rawTax = $productLines->sum(function ($p) use ($taxRates): int {
            $rate = (float) ($taxRates[$p['product_id'] ?? null] ?? 0);
            if ($rate <= 0) {
                return 0;
            }

            return (int) round((int) ($p['quantity'] ?? 0) * (int) ($p['unit_price'] ?? 0) * $rate / 100);
        });

        $subtotal = $armados + $products;
        $discount = (int) round($subtotal * ((float) $this->input('discount_percent', 0)) / 100);
        $base = max(0, $subtotal - $discount);
        $tax = $subtotal > 0 ? (int) round($rawTax * ($base / $subtotal)) : 0;
        $tip = (int) round($base * ((float) $this->input('tip_percent', 0)) / 100);

        return (int) round(($base + $tax + $tip) * (1 + ((float) $this->input('surcharge_percent', 0)) / 100));
    }

    /**
     * Get custom validation messages, in Spanish, for the POS form.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type.required' => 'Selecciona el tipo de documento.',
            'armados.*.lens.product_id.required' => 'Selecciona el lente del armado.',
            'armados.*.lens.unit_price.required' => 'Indica el precio del lente.',
            'products.*.description.required' => 'La descripción del producto es obligatoria.',
            'products.*.quantity.min' => 'La cantidad debe ser al menos 1.',
            'payments.*.payment_method_id.required' => 'Selecciona el método de pago.',
            'payments.*.amount.required' => 'Ingresa el monto del abono.',
            'payments.*.amount.min' => 'El monto del abono debe ser mayor a 0.',
            'prescription.exam_date.before_or_equal' => 'La fecha del examen no puede ser futura.',
            'prescription.exam_date.after_or_equal' => 'La fecha del examen no puede tener más de 2 años.',
        ];
    }

    /**
     * Get the human-friendly attribute names used in fallback messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'cliente',
            'customer.name' => 'nombre del cliente',
            'customer.last_name' => 'apellidos del cliente',
            'customer.document_type' => 'tipo de documento del cliente',
            'customer.id_number' => 'número de documento',
            'customer.phone' => 'celular',
            'document_type' => 'tipo de documento',
            'prescription_id' => 'prescripción',
            'prescription.exam_date' => 'fecha del examen',
            'prescription.lens_type' => 'tipo de lente',
            'prescription.od_sphere' => 'esfera OD',
            'prescription.od_cylinder' => 'cilindro OD',
            'prescription.od_axis' => 'eje OD',
            'prescription.od_add' => 'adición OD',
            'prescription.od_pd' => 'DP OD',
            'prescription.os_sphere' => 'esfera OS',
            'prescription.os_cylinder' => 'cilindro OS',
            'prescription.os_axis' => 'eje OS',
            'prescription.os_add' => 'adición OS',
            'prescription.os_pd' => 'DP OS',
            'discount_percent' => 'descuento',
            'tip_percent' => 'propina',
            'notes' => 'observaciones',
            'armados.*.lens.product_id' => 'lente',
            'armados.*.lens.description' => 'descripción del lente',
            'armados.*.lens.unit_price' => 'precio del lente',
            'armados.*.frame.description' => 'descripción de la montura',
            'armados.*.frame.unit_price' => 'precio de la montura',
            'products.*.description' => 'descripción del producto',
            'products.*.quantity' => 'cantidad',
            'products.*.unit_price' => 'precio unitario',
        ];
    }
}
