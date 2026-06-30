<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix,
            'position' => $this->position,
            'charging_code' => $this->charging_code,
            'charging_name' => $this->charging_name,
            'company_code' => $this->company_code,
            'company_name' => $this->company_name,
            'business_unit_code' => $this->business_unit_code,
            'business_unit_name' => $this->business_unit_name,
            'department_code' => $this->department_code,
            'department_name' => $this->department_name,
            'unit_code' => $this->unit_code,
            'unit_name' => $this->unit_name,
            'sub_unit_code' => $this->sub_unit_code,
            'sub_unit_name' => $this->sub_unit_name,
            'location_code' => $this->location_code,
            'location_name' => $this->location_name,
            'username' => $this->username
        ];
    }
}
