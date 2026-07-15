<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SupplierImport implements ToModel, WithHeadingRow
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
}
