<?php

namespace App\Services;

use AllowDynamicProperties;
use App\Models\Finding;
use App\Models\Response;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    /**
     * Paginated checklist listing.
     *
     * - Pass $userId to scope results to a single user (copies assigned to
     *   them, their own responses, sections owned by them).
     * - Pass $userId = null for the admin view: all copies, all users'
     *   responses, every section regardless of owner.
     */
    public function getChecklist(?int $userId = null, int $perPage = 15, ?int $isAnswered = null)
    {
        $paginator = Copy::withTrashed()
            ->when($userId !== null, function ($query) use ($userId) {
                $query->whereRaw('JSON_CONTAINS(checklist_user_ids, ?)', [json_encode($userId)]);
            })
            ->paginate($perPage);

        $copyIds = $paginator->getCollection()->pluck('id');

        // Grouped by copy_id then user_id regardless of mode — cheap even in
        // user mode (single inner group), and required in admin mode to avoid
        // cross-user answer collisions on (name, category) keys.
        $answersByCopy = Response::query()
            ->whereIn('copy_id', $copyIds)
            ->when($userId !== null, function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with('images')
            ->orderByDesc('created_at') // latest submission wins on duplicates
            ->get()
            ->groupBy(['copy_id', 'user_id'])
            ->map(function ($byUser) {
                return $byUser->map(fn ($responses) => $this->buildAnswerIndex($responses));
            });

        $filtered = $paginator->getCollection()
            ->map(function (Copy $copy) use ($userId, $isAnswered, $answersByCopy) {
                $answersByUser = $answersByCopy->get($copy->id, collect());

                $sections = $this->applySectionsAndAnswers(
                    $copy->checklist,
                    $userId,
                    $isAnswered,
                    $answersByUser
                );

                $copy->setAttribute('checklist', $sections);

                return $copy;
            })
            ->reject(fn (Copy $copy) => empty($copy->checklist)); // drop copies with nothing matching

        $paginator->setCollection($filtered->values());

        return $paginator;
    }

    /**
     * Fetch a single copy's checklist by copy_id.
     *
     * - Pass $userId to scope to one user's sections/answers within this copy.
     * - Pass $userId = null to return every user's sections/answers (admin view).
     *
     * Returns null if the copy doesn't exist, or (in user mode) doesn't
     * belong to $userId — callers should treat both cases as a 404.
     */
    public function getChecklistById(int $copyId, ?int $userId = null, ?int $isAnswered = null): ?Copy
    {

      $copy = Copy::withTrashed()
            ->when($userId !== null, function ($query) use ($userId) {
                $query->whereRaw('JSON_CONTAINS(checklist_user_ids, ?)', [json_encode($userId)]);
            })
          ->with([
              'findings' => function ($query) {
                  $query->with(['observers' => function ($query) {
                      $query->select('users.id', DB::raw("CONCAT(first_name, ' ', last_name) as full_name"));
                  }]);
              }
          ])
            ->find($copyId);

        if (!$copy) {
            return null;
        }

        $answersByUser = Response::query()
            ->where('copy_id', $copyId)
            ->when($userId !== null, function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with('images')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($responses) => $this->buildAnswerIndex($responses));

        $sections = $this->applySectionsAndAnswers($copy->checklist, $userId, $isAnswered, $answersByUser);
        $sections = $this->attachSectionAverages($sections);
        $sections = $this->attachAssignedUsers($sections);

        $copy->setAttribute('checklist', $sections);

        return $copy;
    }

    /**
     * Attach the assigned user's { id, name } to every section, keyed off
     * each section's own user_id. Fetches all needed users in a single
     * query (not per-section) to avoid N+1.
     */
    protected function attachAssignedUsers(array $sections): array
    {
        $userIds = collect($sections)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return collect($sections)
                ->map(function (array $section) {
                    $section['assigned_user'] = null;

                    return $section;
                })
                ->all();
        }

        $usersById = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name'])
            ->keyBy('id');

        return collect($sections)
            ->map(function (array $section) use ($usersById) {
                $sectionUserId = isset($section['user_id']) ? (int) $section['user_id'] : null;
                $user = $sectionUserId !== null ? $usersById->get($sectionUserId) : null;

                $section['assigned_user'] = $user
                    ? ['id' => $user->id, 'name' => $user->first_name . ' ' . $user->last_name]
                    : null;

                return $section;
            })
            ->all();
    }

    protected function attachSectionAverages(array $sections): array
    {
        return collect($sections)
            ->map(function (array $section) {
                $breakdown = $this->calculateSectionBreakdown($section);

                $section['average_rating'] = $breakdown['average'];
                $section['rating_breakdown'] = $breakdown;

                return $section;
            })
            ->all();
    }

    /**
     * Build the rating breakdown for a section: which sub-items contributed,
     * how many were rated vs. total, the sum, and the resulting average.
     * Unrated sub-items are listed (rating: null) but excluded from sum/average.
     */
    protected function calculateSectionBreakdown(array $section): array
    {
        $items = [];

        if (!empty($section['sub-sections'])) {
            foreach ($section['sub-sections'] as $subSection) {
                foreach ($subSection['sub-items'] ?? [] as $subItem) {
                    $items[] = $subItem;
                }
            }
        } else {
            $items = $section['item'] ?? [];
        }

        $breakdownItems = collect($items)->map(function (array $item) {
            $rating = $item['answer']['rating'] ?? null;
            $isNumeric = $rating !== null && is_numeric($rating);

            return [
                'name'   => $item['name'] ?? null,
                'rating' => $isNumeric ? (float) $rating : null,
                'remarks' => $item['remarks'] ?? null,
            ];
        });

        $ratedItems = $breakdownItems->filter(fn ($i) => $i['rating'] !== null);
        $sum = $ratedItems->sum('rating');
        $ratedCount = $ratedItems->count();

        return [
            'items'       => $breakdownItems->values()->all(),
            'total_count' => $breakdownItems->count(),
            'rated_count' => $ratedCount,
            'sum'         => $ratedCount > 0 ? round($sum, 2) : 0.0,
            'average'     => $ratedCount > 0 ? round($sum / $ratedCount, 2) : null,
        ];
    }

    protected function calculateSectionAverage(array $section): ?float
    {
        $ratings = collect();

        if (!empty($section['sub-sections'])) {
            foreach ($section['sub-sections'] as $subSection) {
                foreach ($subSection['sub-items'] ?? [] as $subItem) {
                    $rating = $subItem['answer']['rating'] ?? null;

                    if ($rating !== null && is_numeric($rating)) {
                        $ratings->push((float) $rating);
                    }
                }
            }
        } else {
            foreach ($section['item'] ?? [] as $item) {
                $rating = $item['answer']['rating'] ?? null;

                if ($rating !== null && is_numeric($rating)) {
                    $ratings->push((float) $rating);
                }
            }
        }

        if ($ratings->isEmpty()) {
            return null;
        }

        return round($ratings->avg(), 2);
    }

    /**
     * Reduce a set of responses (already scoped to one copy + one user) into
     * a lookup keyed by subItemKey(), keeping the latest submission per key.
     */
    protected function buildAnswerIndex($responses): array
    {
        return $responses->reduce(function (array $carry, Response $response) {
            $content = $response->content;

            if (!isset($content['name'])) {
                return $carry;
            }

            $key = $this->subItemKey($content['name'], $content['category'] ?? null);

            // Keep the first (i.e. latest, due to orderByDesc upstream) match per key.
            if (!isset($carry[$key])) {
                $carry[$key] = [
                    'batch_no'     => $response->batch_no,
                    'rating'       => $content['rating'] ?? null,
                    'remarks'      => $content['remarks'] ?? null,
                    'images'       => $response->images->pluck('url')->values(),
                    'is_completed' => (bool) $response->is_completed, // draft vs. final
                    'answered_at'  => $response->created_at,
                ];
            }

            return $carry;
        }, []);
    }

    /**
     * Filter a copy's checklist sections by owner/is_answered, then attach
     * each section's resolved answer from the per-user answer index.
     *
     * @param array $checklist    Raw $copy->checklist array.
     * @param int|null $userId    Fixed user in user mode; null in admin mode.
     * @param int|null $isAnswered Optional is_answered filter.
     * @param \Illuminate\Support\Collection $answersByUser Keyed by user_id => [subItemKey => answer].
     */
    protected function applySectionsAndAnswers(
        array $checklist,
        ?int $userId,
        ?int $isAnswered,
              $answersByUser
    ): array {
        return collect($checklist)
            ->filter(function (array $section) use ($userId, $isAnswered) {
                // In user mode, only keep sections owned by this user.
                if ($userId !== null && (int) ($section['user_id'] ?? -1) !== $userId) {
                    return false;
                }

                // null = no filter, return all matching sections regardless of status
                if ($isAnswered === null) {
                    return true;
                }

                $sectionIsAnswered = (int) ($section['is_answered'] ?? 0);

                return $sectionIsAnswered === $isAnswered;
            })
            ->map(function (array $section) use ($userId, $answersByUser) {
                // User mode: always resolve against the fixed $userId.
                // Admin mode: resolve against each section's own owner.
                $lookupUserId = $userId ?? (isset($section['user_id']) ? (int) $section['user_id'] : null);
                $answersByKey = $answersByUser->get($lookupUserId, collect());

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
