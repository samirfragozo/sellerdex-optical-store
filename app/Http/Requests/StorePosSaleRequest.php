<?php

namespace App\Http\Requests;

use App\Enums\SaleDocumentType;
use App\Models\Sale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'customer' => ['required_without:customer_id', 'array'],
            'customer.name' => ['required_without:customer_id', 'string', 'max:255'],
            'customer.last_name' => ['nullable', 'string', 'max:255'],
            'customer.document_type' => ['nullable', 'string'],
            'customer.id_number' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:255'],
            'document_type' => ['required', Rule::enum(SaleDocumentType::class)],
            'prescription_id' => ['nullable', 'exists:prescriptions,id'],
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
}
