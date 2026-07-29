<?php

namespace App\Services;

use App\Models\Checklist;

class ChecklistService
{
    public function getChecklist() {
        return Checklist::orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }
    public function createChecklist(array $data) {
        return Checklist::create($data);
    }

    public function getChecklistById(int $id) {
        return Checklist::withTrashed()->find($id);
    }

    public function updateChecklist(array $data, Checklist $checklist) {
        $checklist->update($data);
        return $checklist->fresh();
    }

    public function deleteChecklist($checklist) : void {
        $checklist = Checklist::withTrashed()->find($checklist);

        if ($checklist->trashed()) {
            $checklist->restore();
        } else {
            $checklist->delete();
        }
    }
}
