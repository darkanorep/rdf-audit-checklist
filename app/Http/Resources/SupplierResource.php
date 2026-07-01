<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'address' => $this->address,
            'tin_no' => $this->tin_no,
            'contact_no' => $this->contact_no,
            'products_offered' => $this->products_offered,
            'email' => $this->email,
            'remarks' => $this->remarks,
        ];
    }
}
