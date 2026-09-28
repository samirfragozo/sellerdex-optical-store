<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Enums\LensKind;
use App\Enums\SaleDocumentType;
use App\Models\LensType;
use App\Models\Prescription;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
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
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'armados' => ['nullable', 'array'],
            'armados.*.lens.description' => ['required', 'string', 'max:255'],
            'armados.*.lens.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'armados.*.lens.price_override' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'armados.*.lens.lens_type_id' => ['required', 'exists:lens_types,id'],
            'armados.*.lens.lens_technology_id' => ['required', 'exists:lens_technologies,id'],
            'armados.*.lens.lens_material_id' => ['required', 'exists:lens_materials,id'],
            'armados.*.lens.treatment_ids' => ['nullable', 'array'],
            'armados.*.lens.treatment_ids.*' => ['integer', 'exists:lens_treatments,id'],
            'armados.*.frame' => ['nullable', 'array'],
            'armados.*.frame.product_id' => ['nullable', 'exists:products,id'],
            'armados.*.frame.description' => ['nullable', 'string', 'max:255'],
            'armados.*.frame.unit_price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'armados.*.frame.unit_cost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'armados.*.own_frame' => ['boolean'],
            'armados.*.slots' => ['nullable', 'array'],
            'armados.*.slots.*.kit_slot_id' => ['required', 'integer', 'exists:kit_slots,id'],
            'armados.*.slots.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'armados.*.slots.*.selected' => ['boolean'],
            'products' => ['nullable', 'array'],
            'products.*.product_id' => ['nullable', 'exists:products,id'],
            'products.*.description' => ['required', 'string', 'max:255'],
            'products.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'products.*.unit_price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:100000000'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'surcharge_percent' => ['nullable', 'numeric', 'between:0,100'],
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

            if (! $this->cartHasLens()) {
                return;
            }

            if (empty($this->input('customer_id')) && empty($this->input('customer.name'))) {
                $validator->errors()->add('customer', 'La venta de lentes formulados requiere un cliente.');
            }

            if (empty($this->input('prescription_id'))) {
                $validator->errors()->add('prescription_id', 'La venta de lentes formulados requiere una prescripción.');
            } elseif ($this->filled('customer_id')) {
                $belongsToCustomer = Prescription::query()
                    ->whereKey($this->input('prescription_id'))
                    ->where('customer_id', $this->input('customer_id'))
                    ->exists();

                if (! $belongsToCustomer) {
                    $validator->errors()->add('prescription_id', 'La prescripción no pertenece al cliente seleccionado.');
                }
            }

            $this->validateAdditionRequirement($validator);
        });
    }

    /**
     * Multifocal lens kinds (bifocal, progressive) require a prescription
     * with addition on at least one eye.
     */
    protected function validateAdditionRequirement(Validator $validator): void
    {
        $armados = (array) $this->input('armados', []);

        if (empty($armados)) {
            return;
        }

        $lensTypeIds = collect($armados)->pluck('lens.lens_type_id')->filter()->unique()->all();

        if (empty($lensTypeIds)) {
            return;
        }

        $kinds = LensType::query()->whereIn('id', $lensTypeIds)->pluck('kind', 'id');
        $hasAddition = $this->prescriptionHasAddition();

        foreach ($armados as $index => $armado) {
            $lensTypeId = $armado['lens']['lens_type_id'] ?? null;
            $kind = $lensTypeId !== null ? $kinds->get($lensTypeId) : null;

            if ($kind === null) {
                continue;
            }

            $kind = $kind instanceof LensKind ? $kind : LensKind::from($kind);

            if ($kind->requiresAddition() && ! $hasAddition) {
                $validator->errors()->add("armados.{$index}.lens.lens_type_id", __('app.pos.lens_form.requires_addition'));
            }
        }
    }

    /** Whether the selected prescription has addition on either eye. */
    protected function prescriptionHasAddition(): bool
    {
        $prescription = Prescription::find($this->input('prescription_id'));

        return $prescription !== null
            && ((float) ($prescription->od_add ?? 0) > 0 || (float) ($prescription->os_add ?? 0) > 0);
    }

    /** A lens sale is any sale that carries at least one armado. */
    protected function cartHasLens(): bool
    {
        return ! empty($this->input('armados'));
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
            'discount_percent' => 'descuento',
            'notes' => 'observaciones',
            'armados.*.lens.product_id' => 'lente',
            'armados.*.lens.description' => 'descripción del lente',
            'armados.*.frame.description' => 'descripción de la montura',
            'armados.*.frame.unit_price' => 'precio de la montura',
            'products.*.description' => 'descripción del producto',
            'products.*.quantity' => 'cantidad',
            'products.*.unit_price' => 'precio unitario',
        ];
    }
}
