<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', Rule::unique('suppliers', 'name')->ignore($this->supplier)],
            'contact_person' => 'required|array',
            'address' => 'required',
            'tin_no' => 'nullable',
            'contact_no' => 'required|array',
            'products_offered' => 'nullable|array',
            'email' => ['required', 'email', Rule::unique('suppliers', 'email')->ignore($this->supplier)],
            'remarks' => 'nullable',
        ];
    }
}
