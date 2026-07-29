<?php

namespace App\Services;

use AllowDynamicProperties;
use App\Models\Response;
use Illuminate\Database\QueryException;
use App\Models\Checklist;
use App\Models\Copy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

#[AllowDynamicProperties]
class CopyService
{
    public function __construct(ReferenceNumberService $referenceNumberService) {
        $this->referenceNumberService = $referenceNumberService;
    }

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

    public function getChecklistForUser(int $userId, int $perPage = 15, ?int $isAnswered = null)
    {
        $paginator = Copy::withTrashed()
            ->whereRaw('JSON_CONTAINS(checklist_user_ids, ?)', [json_encode($userId)])
            ->paginate($perPage);

        $copyIds = $paginator->getCollection()->pluck('id');

        // Single query for this user's responses across the whole page of
        // copies (drafts included), grouped by copy_id, to avoid N+1 when
        // merging answers below.
        $answersByCopy = Response::query()
            ->whereIn('copy_id', $copyIds)
            ->where('user_id', $userId)
            ->with('images')
            ->orderByDesc('created_at') // latest submission wins on duplicates
            ->get()
            ->groupBy('copy_id')
            ->map(function ($responses) {
                return $responses->reduce(function (array $carry, Response $response) {
                    $content = $response->content;

                    if (!isset($content['name'])) {
                        return $carry;
                    }

                    $key = $this->subItemKey($content['name'], $content['category'] ?? null);

                    // Keep the first (i.e. latest, due to orderByDesc above) match per key.
                    if (!isset($carry[$key])) {
                        $carry[$key] = [
                            'batch_no' => $response->batch_no,
                            'rating'       => $content['rating'] ?? null,
                            'remarks'      => $content['remarks'] ?? null,
                            'images'       => $response->images->pluck('url')->values(),
                            'is_completed' => (bool) $response->is_completed, // draft vs. final
                            'answered_at'  => $response->created_at,
                        ];
                    }

                    return $carry;
                }, []);
            });

        $filtered = $paginator->getCollection()
            ->map(function (Copy $copy) use ($userId, $isAnswered, $answersByCopy) {
                $answersByKey = $answersByCopy->get($copy->id, collect());

                $sections = collect($copy->checklist)
                    ->filter(function (array $section) use ($userId, $isAnswered) {
                        if (($section['user_id'] ?? null) != $userId) {
                            return false;
                        }

                        // null = no filter, return all of this user's sections regardless of status
                        if ($isAnswered === null) {
                            return true;
                        }

                        $sectionIsAnswered = (int) ($section['is_answered'] ?? 0);

                        return $sectionIsAnswered === $isAnswered;
                    })
                    ->map(function (array $section) use ($answersByKey) {
                        if (!empty($section['sub-sections'])) {
                            $section['sub-sections'] = collect($section['sub-sections'])
                                ->map(function (array $subSection) use ($answersByKey) {
                                    $subSection['sub-items'] = collect($subSection['sub-items'] ?? [])
                                        ->map(function (array $subItem) use ($answersByKey) {
                                            $key = $this->subItemKey($subItem['name'], $subItem['category'] ?? null);
                                            $subItem['answer'] = $answersByKey[$key] ?? null;

                                            return $subItem;
                                        })
                                        ->all();

                                    return $subSection;
                                })
                                ->all();

                            return $section;
                        }

                        // Flat shape: section.item[]
                        $section['item'] = collect($section['item'] ?? [])
                            ->map(function (array $item) use ($answersByKey) {
                                $key = $this->subItemKey($item['name'], $item['category'] ?? null);
                                $item['answer'] = $answersByKey[$key] ?? null;

                                return $item;
                            })
                            ->all();

                        return $section;
                    })
                    ->values()
                    ->all();

                $copy->setAttribute('checklist', $sections);

                return $copy;
            })
            ->reject(fn (Copy $copy) => empty($copy->checklist)); // drop copies with nothing matching

        $paginator->setCollection($filtered->values());

        return $paginator;
    }

    protected function subItemKey(string $name, ?string $category): string
    {
        return Str::lower(trim($name)) . '|' . Str::lower(trim((string) $category));
    }

    private function isDuplicateReferenceNo(QueryException $e): bool
    {
        return (string) $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'reference_no');
    }
}
