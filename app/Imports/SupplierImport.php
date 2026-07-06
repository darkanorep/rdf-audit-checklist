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
            'contact_person'   => $row['contact_person']           ?? null,
            'address'          => $row['business_address']         ?? null,
            'tin_no'           => $row['tin']                      ?? null,
            'contact_no'       => $this->splitToJson($row['contact'] ?? null, '/'),
            'products_offered' => $this->splitToJson($row['products_offered'] ?? null, ','),
            'email'            => $row['e_mail_address']           ?? null,
            'remarks'          => $row['remarks']                  ?? null,
        ]);
    }

    private function splitToJson(?string $value, string $delimiter): ?string
    {
        if (empty($value)) {
            return null;
        }

        $parts = array_map('trim', explode($delimiter, $value));
        $parts = array_filter($parts, fn ($v) => $v !== '');

        return json_encode(array_values($parts));
    }
}
