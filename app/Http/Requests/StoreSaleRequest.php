<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Enums\FrameType;
use App\Enums\LensKind;
use App\Enums\SaleDocumentType;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
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
            'customer_id' => ['nullable', 'integer', $this->companyCustomer()],
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
            'discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'approval_pin' => ['nullable', 'string', 'max:20'],
            'armados' => ['nullable', 'array'],
            'armados.*.patient_id' => ['nullable', 'integer', $this->companyCustomer()],
            'armados.*.prescription_id' => [
                'required',
                'integer',
                Rule::exists('prescriptions', 'id')->where('company_id', $this->user()->company_id)->withoutTrashed(),
            ],
            'armados.*.lens.description' => ['required', 'string', 'max:255'],
            'armados.*.lens.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'armados.*.lens.price_override' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'armados.*.lens.lens_type_id' => ['required', 'integer', 'exists:lens_types,id'],
            'armados.*.lens.lens_technology_id' => ['required', 'integer', 'exists:lens_technologies,id'],
            'armados.*.lens.lens_material_id' => ['required', 'integer', 'exists:lens_materials,id'],
            'armados.*.lens.supplier_id' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('company_id', $this->user()->company_id)
                    ->where('is_laboratory', true)->where('is_active', true)->withoutTrashed(),
            ],
            'armados.*.lens.treatment_ids' => ['nullable', 'array'],
            'armados.*.lens.treatment_ids.*' => ['integer', 'exists:lens_treatments,id'],
            'armados.*.frame' => ['nullable', 'array'],
            'armados.*.frame.product_id' => ['nullable', 'exists:products,id'],
            'armados.*.frame.description' => ['nullable', 'string', 'max:255'],
            'armados.*.frame.unit_price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'armados.*.frame.unit_cost' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'armados.*.own_frame' => ['boolean'],
            'armados.*.measurements' => ['nullable', 'array'],
            'armados.*.measurements.od_height' => ['nullable', 'numeric', 'between:10,40'],
            'armados.*.measurements.os_height' => ['nullable', 'numeric', 'between:10,40'],
            'armados.*.measurements.frame_a' => ['nullable', 'numeric', 'between:30,80'],
            'armados.*.measurements.frame_b' => ['nullable', 'numeric', 'between:15,60'],
            'armados.*.measurements.frame_dbl' => ['nullable', 'numeric', 'between:10,30'],
            'armados.*.measurements.frame_type' => ['nullable', Rule::enum(FrameType::class)],
            'armados.*.own_frame_description' => ['nullable', 'string', 'max:255'],
            'armados.*.own_frame_condition' => ['nullable', 'string', 'max:255'],
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

            $this->validateStoreCreditPayments($validator);

            if (! $this->cartHasLens()) {
                return;
            }

            if (empty($this->input('customer_id')) && empty($this->input('customer.name'))) {
                $validator->errors()->add('customer', 'La venta de lentes formulados requiere un cliente.');
            }

            $this->validateArmadoPrescriptions($validator);
        });
    }

    /** Store credit needs an existing customer whose balance covers the sum of those payments. */
    private function validateStoreCreditPayments(Validator $validator): void
    {
        $method = PaymentMethod::storeCreditFor($this->user()->company_id);

        $spent = $method === null ? 0 : collect((array) $this->input('payments', []))
            ->where('payment_method_id', $method->id)
            ->sum(fn (array $payment): int => (int) ($payment['amount'] ?? 0));

        if ($spent === 0 || $validator->errors()->has('customer_id')) {
            return;
        }

        $customer = is_numeric($this->input('customer_id')) ? Customer::find((int) $this->input('customer_id')) : null;

        if ($customer === null) {
            $validator->errors()->add('payments', __('app.validation.store_credit_needs_customer'));
        } elseif ($spent > $customer->creditBalance()) {
            $validator->errors()->add('payments', __('app.validation.store_credit_exceeds_balance', [
                'balance' => '$'.number_format($customer->creditBalance(), 0, ',', '.'),
            ]));
        }
    }

    /** A live customer of the seller's company — the payer or an armado's patient. */
    private function companyCustomer(): Exists
    {
        return Rule::exists('customers', 'id')->where('company_id', $this->user()->company_id)->withoutTrashed();
    }

    /**
     * Each armado's prescription must belong to that armado's patient (the
     * payer when no patient is given — a brand-new inline customer has no id
     * yet, so it owns none), and a multifocal lens needs addition on it.
     */
    protected function validateArmadoPrescriptions(Validator $validator): void
    {
        $armados = (array) $this->input('armados', []);

        $prescriptions = Prescription::query()
            ->whereKey(collect($armados)->pluck('prescription_id')->filter(fn (mixed $id): bool => is_numeric($id))->all())
            ->get()
            ->keyBy('id');

        $kinds = LensType::query()
            ->whereKey(collect($armados)->pluck('lens.lens_type_id')->filter(fn (mixed $id): bool => is_numeric($id))->all())
            ->pluck('kind', 'id');

        foreach ($armados as $index => $armado) {
            $prescriptionId = $armado['prescription_id'] ?? null;

            // Rules already reported a missing, foreign or malformed id; after-hooks still run.
            if (! is_numeric($prescriptionId) || ! is_numeric($armado['patient_id'] ?? 0) || ! $prescriptions->has((int) $prescriptionId)) {
                continue;
            }

            $prescription = $prescriptions->get((int) $prescriptionId);
            $patientId = $armado['patient_id'] ?? $this->input('customer_id');

            if ((int) $prescription->customer_id !== (int) $patientId) {
                $validator->errors()->add("armados.{$index}.prescription_id", __('app.validation.prescription_not_owned'));

                continue;
            }

            $lensTypeId = $armado['lens']['lens_type_id'] ?? null;
            $kind = is_numeric($lensTypeId) ? $kinds->get((int) $lensTypeId) : null;

            if ($kind !== null) {
                $kind = $kind instanceof LensKind ? $kind : LensKind::from($kind);
                $hasAddition = (float) ($prescription->od_add ?? 0) > 0 || (float) ($prescription->os_add ?? 0) > 0;

                if ($kind->requiresAddition() && ! $hasAddition) {
                    $validator->errors()->add("armados.{$index}.lens.lens_type_id", __('app.pos.lens_form.requires_addition'));
                }
            }

            $this->validateLensRange($validator, $index, (array) ($armado['lens'] ?? []), $prescription);
        }
    }

    /**
     * The chosen lab (or, when none is chosen, some lab) must price this
     * combination for the prescription's governing eye.
     *
     * @param  array<string, mixed>  $lens
     */
    protected function validateLensRange(Validator $validator, int|string $index, array $lens, Prescription $prescription): void
    {
        $ids = [$lens['lens_type_id'] ?? null, $lens['lens_technology_id'] ?? null, $lens['lens_material_id'] ?? null];
        $supplierId = $lens['supplier_id'] ?? null;

        // Malformed ids and foreign or inactive labs were already reported by their rules.
        if (in_array(false, array_map('is_numeric', $ids), true) || ($supplierId !== null && ! is_numeric($supplierId))
            || $validator->errors()->has("armados.{$index}.lens.supplier_id")) {
            return;
        }

        $combination = LensCombination::forSelection(...array_map('intval', $ids));

        if ($combination === null || LensCombinationPrice::resolve($combination, $prescription, $supplierId !== null ? (int) $supplierId : null) !== null) {
            return;
        }

        // With no priced lens at any active lab, SaleController answers with that readiness blocker instead.
        if (collect($this->user()->company->saleReadiness())->contains('key', 'lens_price')) {
            return;
        }

        $combination->load(['lensType', 'lensTechnology', 'lensMaterial']);
        $validator->errors()->add("armados.{$index}.lens", LensCombinationPrice::outOfRangeMessage(
            $combination,
            $supplierId !== null ? Supplier::find((int) $supplierId) : null,
        ));
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
            'armados.*.prescription_id.required' => __('app.validation.lens_requires_prescription'),
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
            'discount_percent' => 'descuento',
            'notes' => 'observaciones',
            'armados.*.patient_id' => __('app.fields.patient'),
            'armados.*.prescription_id' => __('app.fields.prescription'),
            'armados.*.lens.product_id' => 'lente',
            'armados.*.lens.supplier_id' => __('app.fields.laboratory'),
            'armados.*.lens.description' => 'descripción del lente',
            'armados.*.frame.description' => 'descripción de la montura',
            'armados.*.frame.unit_price' => 'precio de la montura',
            'armados.*.measurements.od_height' => __('app.fields.od_height'),
            'armados.*.measurements.os_height' => __('app.fields.os_height'),
            'armados.*.measurements.frame_a' => __('app.fields.frame_a'),
            'armados.*.measurements.frame_b' => __('app.fields.frame_b'),
            'armados.*.measurements.frame_dbl' => __('app.fields.frame_dbl'),
            'armados.*.measurements.frame_type' => __('app.fields.frame_type'),
            'armados.*.own_frame_description' => __('app.fields.customer_frame_description'),
            'armados.*.own_frame_condition' => __('app.fields.customer_frame_condition'),
            'products.*.description' => 'descripción del producto',
            'products.*.quantity' => 'cantidad',
            'products.*.unit_price' => 'precio unitario',
        ];
    }
}
