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
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.last_name' => ['nullable', 'string', 'max:255'],
            'customer.document_type' => ['nullable', Rule::enum(DocumentType::class)],
            'customer.id_number' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:255'],
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
            'discount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
            'payment' => ['nullable', 'array'],
            'payment.payment_method_id' => ['required_with:payment', 'exists:payment_methods,id'],
            'payment.amount' => ['required_with:payment', 'integer', 'min:1'],
            'payment.reference' => ['nullable', 'string', 'max:255'],
            'combo' => ['nullable', 'array'],
            'combo.with_exam' => ['boolean'],
            'combo.include_liquid' => ['boolean'],
            'combo.forro' => ['nullable', 'in:small,large'],
            'combo.own_frame' => ['boolean'],
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
            // A payment cannot exceed the sale total.
            if (! empty($this->input('payment')) && (int) $this->input('payment.amount', 0) > $this->saleTotal()) {
                $validator->errors()->add('payment.amount', 'El abono no puede superar el total de la venta.');
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

    /**
     * Determine whether any submitted item is a lens product.
     */
    protected function cartHasLens(): bool
    {
        $productIds = collect($this->input('items', []))
            ->pluck('product_id')
            ->filter()
            ->all();

        if (empty($productIds)) {
            return false;
        }

        return Product::query()
            ->whereIn('id', $productIds)
            ->whereHas('category', fn ($query) => $query->where('key', 'lens'))
            ->exists();
    }

    /**
     * Compute the sale total from the submitted items, discount and surcharge.
     */
    protected function saleTotal(): int
    {
        $subtotal = collect($this->input('items', []))
            ->sum(fn ($item): int => (int) ($item['quantity'] ?? 0) * (int) ($item['unit_price'] ?? 0));

        $base = max(0, $subtotal - (int) $this->input('discount', 0));

        return (int) round($base * (1 + ((float) $this->input('surcharge_percent', 0)) / 100));
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
            'items.required' => 'Agrega al menos un ítem a la venta.',
            'items.min' => 'Agrega al menos un ítem a la venta.',
            'items.*.description.required' => 'La descripción del ítem es obligatoria.',
            'items.*.quantity.required' => 'Indica la cantidad del ítem.',
            'items.*.quantity.min' => 'La cantidad debe ser al menos 1.',
            'items.*.unit_price.required' => 'Indica el precio unitario del ítem.',
            'items.*.unit_price.min' => 'El precio unitario no puede ser negativo.',
            'payment.payment_method_id.required_with' => 'Selecciona el método de pago.',
            'payment.amount.required_with' => 'Ingresa el monto del abono.',
            'payment.amount.min' => 'El monto del abono debe ser mayor a 0.',
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
            'discount' => 'descuento',
            'notes' => 'observaciones',
            'items.*.description' => 'descripción',
            'items.*.quantity' => 'cantidad',
            'items.*.unit_price' => 'precio unitario',
        ];
    }
}
