<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublishedChecklistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        $filteredChecklist = collect($this->checklist)
            ->filter(fn ($section) => ($section['user_id'] ?? null) == $userId)
            ->values();

        return [
            'title' => $this->title,
            'checklist' => $filteredChecklist
        ];
    }
}
