<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PendingUserRequest extends FormRequest
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
            'id_prefix' => 'required|string',
            'id_no' => 'required|string',
            'employee_id' => 'nullable|string',
            'first_name' => 'nullable|string',
            'middle_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'suffix' => 'nullable|string',
            'position' => 'nullable|string',
            'charging_code' => 'nullable|string',
            'charging_name' => 'nullable|string',
            'company_code' => 'nullable|string',
            'company_name' => 'nullable|string',
            'business_unit_code' => 'nullable|string',
            'business_unit_name' => 'nullable|string',
            'department_code' => 'nullable|string',
            'department_name' => 'nullable|string',
            'unit_code' => 'nullable|string',
            'unit_name' => 'nullable|string',
            'sub_unit_code' => 'nullable|string',
            'sub_unit_name' => 'nullable|string',
            'location_code' => 'nullable|string',
            'location_name' => 'nullable|string',
            'username' => 'nullable|string|unique:pending_users,username'
        ];
    }
}
