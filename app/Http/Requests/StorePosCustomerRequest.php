<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'id_number' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('app.fields.name'),
            'last_name' => __('app.fields.last_name'),
            'document_type' => __('app.fields.document_type'),
            'id_number' => __('app.fields.id_number'),
            'phone' => __('app.fields.phone'),
            'address' => __('app.fields.address'),
            'city' => __('app.fields.city'),
            'birth_date' => __('app.fields.birth_date'),
            'email' => __('app.fields.email'),
            'notes' => __('app.fields.notes'),
        ];
    }
}
