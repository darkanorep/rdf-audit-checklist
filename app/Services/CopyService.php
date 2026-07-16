<?php

namespace App\Services;

use AllowDynamicProperties;
use App\Http\Requests\ChecklistRequest;
use App\Models\Copy;

#[AllowDynamicProperties]
class CopyService
{
    public function publish(array $data)
    {
        return Copy::create($data);
    }

    public function getChecklistForUser(int $userId): ?Copy
    {
        $checklist = Copy::withTrashed()
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
