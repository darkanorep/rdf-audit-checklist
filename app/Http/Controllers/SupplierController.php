<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Services\SupplierService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ApiResponse;
    protected SupplierService $supplierService;
    public function __construct(SupplierService $supplierService) {
        $this->supplierService = $supplierService;
    }

    public function index() {
        $suppliers = $this->supplierService->getSuppliers();

        if ($suppliers->isEmpty()) {
            return $this->responseNotFound('No suppliers found.');
        }

        return $this->responseSuccess('Suppliers retrieved successfully.', $suppliers);
    }

    public function store(SupplierRequest $request) {
        $validated = $request->validated();
        return $this->responseCreated('Supplier created successfully.', new SupplierResource($this->supplierService->createSupplier($validated)));
    }

    public function show($id) {
        $supplier = $this->findUserOrFail($id);
        if ($supplier instanceof JsonResponse) return $supplier;

        return $this->responseSuccess('Supplier retrieved successfully.', new SupplierResource($supplier));
    }

    public function update(SupplierRequest $request, $id) {
        $supplier = $this->findUserOrFail($id);
        if ($supplier instanceof JsonResponse) return $supplier;

        $validated = $request->validated();
        return $this->responseSuccess('Supplier updated successfully.', new SupplierResource($this->supplierService->updateSupplier($validated, $supplier)));
    }

    public function destroy($id) {
        $supplier = $this->findUserOrFail($id);
        if ($supplier instanceof JsonResponse) return $supplier;

        return $this->responseSuccess('Supplier status successfully changed.', $this->supplierService->deleteSupplier($id));
    }

    private function findUserOrFail($id) {
        $supplier = $this->supplierService->getSupplierbyId($id);
        return $supplier ?: $this->responseNotFound('Supplier not found.');
    }
}
