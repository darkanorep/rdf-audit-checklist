<?php

namespace App\Services;

use App\Models\CategoryType;

class CategoryTypeService
{
    public function getRoles() {
        return CategoryType::orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function createCategoryType(array $data) {
        return CategoryType::create($data);
    }

    public function getCategoryTypebyId($id) {
        return CategoryType::withTrashed()->findOrFail($id);
    }

    public function updateCategoryType(array $data, CategoryType $categoryType) {
        $categoryType->update($data);
        return $categoryType->fresh();
    }

    public function deleteCategoryType($categoryType) : void {
        $categoryType = CategoryType::withTrashed()->find($categoryType);

        if ($categoryType->trashed()) {
            $categoryType->restore();
        } else {
            $categoryType->delete();
        }
    }
}
