<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryTypeRequest;
use App\Http\Resources\CategoryTypeResource;
use App\Services\CategoryTypeService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryTypeController extends Controller
{
    use ApiResponse;
    protected CategoryTypeService $categoryTypeService;
    public function __construct(CategoryTypeService $categoryTypeService) {
        $this->categoryTypeService = $categoryTypeService;
    }

    public function index() {
        $categoryTypes = $this->categoryTypeService->getRoles();

        if ($categoryTypes->isEmpty()) {
            return $this->responseNotFound('No Category Types found.');
        }

        return CategoryTypeResource::collection($categoryTypes);
    }

    public function store(CategoryTypeRequest $request) {
        $validated = $request->validated();
        return $this->responseCreated('Category Type created successfully.', new CategoryTypeResource($this->categoryTypeService->createCategoryType($validated)));
    }

    public function show($id) {
        $categoryTypes = $this->findUserOrFail($id);
        if ($categoryTypes instanceof JsonResponse) return $categoryTypes;

        return $this->responseSuccess('Category Type retrieved successfully.', new CategoryTypeResource($categoryTypes));
    }

    public function update(CategoryTypeRequest $request, $id) {
        $categoryType = $this->findUserOrFail($id);
        if ($categoryType instanceof JsonResponse) return $categoryType;

        $validated = $request->validated();
        return $this->responseSuccess('Category Type updated successfully.', new CategoryTypeResource($this->categoryTypeService->updateCategoryType($validated, $categoryType)));
    }

    public function destroy($id) {
        $categoryType = $this->findUserOrFail($id);
        if ($categoryType instanceof JsonResponse) return $categoryType;

        return $this->responseSuccess('Category Type status successfully changed.', $this->categoryTypeService->deleteCategoryType($id));
    }

    private function findUserOrFail($id) {
        $role = $this->categoryTypeService->getCategoryTypebyId($id);
        return $role ?: $this->responseNotFound('Category Type not found.');
    }
}
