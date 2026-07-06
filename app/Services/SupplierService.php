<?php

namespace App\Services;

use App\Models\Supplier;
use Maatwebsite\Excel\Facades\Excel;

class SupplierService
{
    public function getSuppliers() {
        return Supplier::orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function createSupplier(array $data) {
        return Supplier::create($data);
    }
    public function getSupplierbyId($id) {
        return Supplier::withTrashed()->findOrFail($id);
    }

    public function updateSupplier(array $data, Supplier $supplier) {
        $supplier->update($data);
        return $supplier->fresh();
    }

    public function deleteSupplier(int $id): void
    {
        $supplier = Supplier::withTrashed()->find($id);

        if ($supplier->trashed()) {
            $supplier->restore();
        } else {
            $supplier->delete();
        }
    }

    public function importSupplier($data): void
    {
        Excel::import(new \App\Imports\SupplierImport, $data['file']);
    }
}
