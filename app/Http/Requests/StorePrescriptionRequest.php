<?php

namespace App\Http\Requests;

use App\Enums\PrismBase;
use App\Models\Prescription;
use App\Rules\Diopter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePrescriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Prescription::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('company_id', $this->user()->company_id)),
            ],
            'exam_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subYears(2)->toDateString()],
            'prescriber_name' => ['required', 'string', 'max:255'],
            'prescriber_license' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],

            'od_sphere' => ['nullable', new Diopter(-20, 20)],
            'od_cylinder' => ['nullable', new Diopter(-10, 10)],
            'od_axis' => ['nullable', 'integer', 'between:1,180'],
            'od_add' => ['nullable', new Diopter(0.25, 4)],
            'od_prism' => ['nullable', new Diopter(0, 10)],
            'od_prism_base' => ['nullable', Rule::enum(PrismBase::class)],
            'od_pd' => ['nullable', new Diopter(20, 40, 0.5)],
            'od_va' => ['nullable', 'string', 'max:10'],

            'os_sphere' => ['nullable', new Diopter(-20, 20)],
            'os_cylinder' => ['nullable', new Diopter(-10, 10)],
            'os_axis' => ['nullable', 'integer', 'between:1,180'],
            'os_add' => ['nullable', new Diopter(0.25, 4)],
            'os_prism' => ['nullable', new Diopter(0, 10)],
            'os_prism_base' => ['nullable', Rule::enum(PrismBase::class)],
            'os_pd' => ['nullable', new Diopter(20, 40, 0.5)],
            'os_va' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * Cross-field rules that don't fit a single attribute: an eye's axis and
     * cylinder must travel together, and a prism needs its base.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['od', 'os'] as $eye) {
                $cylinder = $this->input("{$eye}_cylinder");
                $axis = $this->input("{$eye}_axis");

                if (filled($cylinder) && blank($axis)) {
                    $validator->errors()->add("{$eye}_axis", __('app.validation.axis_required_with_cylinder'));
                }

                if (filled($axis) && blank($cylinder)) {
                    $validator->errors()->add("{$eye}_cylinder", __('app.validation.cylinder_required_with_axis'));
                }

                $prism = $this->input("{$eye}_prism");
                $prismBase = $this->input("{$eye}_prism_base");

                if (filled($prism) && blank($prismBase)) {
                    $validator->errors()->add("{$eye}_prism_base", __('app.validation.prism_base_required_with_prism'));
                }
            }
        });
    }

    /**
     * Get the human-friendly attribute names used in fallback messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => __('app.fields.customer'),
            'exam_date' => __('app.fields.exam_date'),
            'prescriber_name' => __('app.fields.prescriber_name'),
            'prescriber_license' => __('app.fields.prescriber_license'),
            'notes' => __('app.fields.notes'),
            'attachment' => __('app.fields.attachment'),
            'od_sphere' => __('app.fields.sphere').' OD',
            'od_cylinder' => __('app.fields.cylinder').' OD',
            'od_axis' => __('app.fields.axis').' OD',
            'od_add' => __('app.fields.add').' OD',
            'od_prism' => __('app.fields.prism').' OD',
            'od_prism_base' => __('app.fields.prism_base').' OD',
            'od_pd' => __('app.fields.pd').' OD',
            'od_va' => __('app.fields.va').' OD',
            'os_sphere' => __('app.fields.sphere').' OS',
            'os_cylinder' => __('app.fields.cylinder').' OS',
            'os_axis' => __('app.fields.axis').' OS',
            'os_add' => __('app.fields.add').' OS',
            'os_prism' => __('app.fields.prism').' OS',
            'os_prism_base' => __('app.fields.prism_base').' OS',
            'os_pd' => __('app.fields.pd').' OS',
            'os_va' => __('app.fields.va').' OS',
        ];
    }
}
