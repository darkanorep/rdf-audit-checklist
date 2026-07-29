<?php

namespace App\Services;

use AllowDynamicProperties;
use Illuminate\Database\QueryException;
use App\Models\Checklist;
use App\Models\Copy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[AllowDynamicProperties]
class CopyService
{
    public function __construct(ReferenceNumberService $referenceNumberService) {
        $this->referenceNumberService = $referenceNumberService;
    }

//    public function publish(array $data)
//    {
//        return Copy::create($data);
//    }

    public function publish(array $validated)
    {
        $location = $validated['information']['location'] ?? null;
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($validated, $location) {
                    $referenceNo = $this->referenceNumberService->generate($location);

                    // keep it in sync inside the JSON payload too, if the frontend reads it from there
                    $validated['information']['reference_no'] = $referenceNo;

                    return Copy::create([
                        'checklist_id' => $validated['checklist_id'],
                        'title' => $validated['title'],
                        'information'  => $validated['information'],
                        'checklist' => $validated['checklist'],
                        'reference_number' => $referenceNo,   // top-level, indexed, unique
                    ]);
                });
            } catch (QueryException $e) {
                if (! $this->isDuplicateReferenceNo($e) || $attempt === $maxAttempts) {
                    throw $e;
                }
                // Someone else won the race for this reference_no (first-of-year edge case).
                // Small jittered backoff, then loop and regenerate against the now-committed row.
                usleep(random_int(10, 50) * 1000);
            }
        }

        throw new RuntimeException('Failed to publish checklist: could not generate a unique reference number.');
    }

    public function getChecklistForUser(int $userId)
    {
        return Copy::withTrashed()
            ->get()
            ->filter(function ($copy) use ($userId) {
                return collect($copy->checklist)
                    ->contains(fn ($section) => ($section['user_id'] ?? null) == $userId);
            })
            ->map(function ($copy) use ($userId) {
                $filteredSections = collect($copy->checklist)
                    ->filter(fn ($section) => ($section['user_id'] ?? null) == $userId)
                    ->values()
                    ->all();

                $copy->setAttribute('checklist', $filteredSections);

                return $copy;
            })
            ->values();
    }

    private function isDuplicateReferenceNo(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'reference_no');
    }
}
