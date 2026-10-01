<?php

namespace App\Imports;

use App\Models\Supplier;
use App\Rules\UniqueSupplierPerLocation;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class SupplierImport implements ToModel, WithHeadingRow, SkipsEmptyRows, WithValidation
{
    public function model(array $row)
    {
        return new Supplier([
            'name'             => $row['business_name'] ?? null,
            'contact_person'   => $this->splitToArray($row['contact_person'] ?? null, '/'),
            'address'          => $row['business_address'] ?? null,
            'tin_no'           => $row['tin'] ?? null,
            'contact_no'       => $this->splitToArray($row['contact'] ?? null, '/'),
            'products_offered' => $this->splitToArray($row['products_offered'] ?? null, ','),
            'email'            => $row['e_mail_address'] ?? null,
            'location'         => $row['location'] ?? null,
            'remarks'          => $row['remarks'] ?? null,
        ]);
    }

    private function splitToArray(?string $value, string $delimiter): ?array
    {
        if (empty($value)) {
            return null;
        }

        $parts = array_map('trim', explode($delimiter, $value));
        $parts = array_filter($parts, fn ($v) => $v !== '');

        return array_values($parts);
    }

    public function prepareForValidation(array $data, int $index): array
    {
        // Validate exactly what will be stored
        foreach (['business_name', 'location'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = trim($data[$key]);
            }
        }

        return $data;
    }

    public function rules(): array
    {
        return [
            'location'      => ['nullable', Rule::in(Supplier::LOCATIONS)],
            'business_name' => ['required', 'string', 'max:255', new UniqueSupplierPerLocation()],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'location.in'            => '"Location" must be exactly one of: ' . implode(', ', Supplier::LOCATIONS) . '.',
            'business_name.required' => 'The "Business Name" is required.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'location'      => 'Location',
            'business_name' => 'Business Name',
        ];
    }
}
