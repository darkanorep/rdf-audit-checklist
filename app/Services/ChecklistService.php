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

    public function publishChecklist(array $data, Checklist $checklist) {
        $checklist->update(array_merge($data, [
            'is_published' => true,
            'published_at' => now(),
        ]));

        return $checklist->fresh();
    }

    public function getChecklistForUser(int $userId): ?Checklist
    {
        $checklist = Checklist::withTrashed()
            ->get()
            ->first(function ($checklist) use ($userId) {
                return collect($checklist->checklist)
                    ->contains(fn ($section) => ($section['user_id'] ?? null) == $userId);
            });

        if (!$checklist) {
            return null;
        }

        $filtered = collect($checklist->checklist)
            ->filter(fn ($section) => ($section['user_id'] ?? null) == $userId)
            ->values()
            ->all();

        $checklist->setAttribute('checklist', $filtered);

        return $checklist;
    }
}
