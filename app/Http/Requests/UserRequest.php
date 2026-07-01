<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
        $user = $this->route('user');

        return [
            'employee_id' => [
                'required',
                'string',
                Rule::unique('users', 'employee_id')->ignore($user),
            ],
            'first_name'          => 'required|string',
            'last_name'           => 'required|string',
            'middle_name'         => 'nullable|string',
            'suffix'              => 'nullable|string',
            'position'            => 'required|string',
            'charging_code'       => 'required|string',
            'charging_name'       => 'required|string',
            'company_code'        => 'required|string',
            'company_name'        => 'required|string',
            'business_unit_code'  => 'required|string',
            'business_unit_name'  => 'required|string',
            'department_code'     => 'required|string',
            'department_name'     => 'required|string',
            'unit_code'           => 'required|string',
            'unit_name'           => 'required|string',
            'sub_unit_code'       => 'required|string',
            'sub_unit_name'       => 'required|string',
            'location_code'       => 'required|string',
            'location_name'       => 'required|string',
            'username' => [
                'required',
                'string',
                Rule::unique('users', 'username')->ignore($user),
            ],
            'role_id' => 'required|exists:roles,id',
        ];
    }
}
