<?php

namespace App\Services;

use App\Models\Copy;
use App\Models\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class PublishChecklistService
{
    // ... publish(), getChecklistForUser(), storeResponse(),
    //     markSectionAnsweredIfComplete(), fetchAnsweredKeys(), subItemKey(), etc.

    /**
     * Paginated list of copies, each annotated with COUNTS only
     * (answered/remaining/percent) — no item-level detail, no images.
     * Computed fresh from Response rows via a single grouped query,
     * so it stays accurate even if checklist.is_answered lags behind.
     */

    public function paginateWithSummary(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['perPage'] ?? 15;
        $status  = $filters['status'] ?? null;
        $page    = $filters['page'] ?? (int) request()->get('page', 1);

        $paginator = Copy::query()
            ->with([
                'findings' => function ($query) {
                    $query->with(['observers' => function ($query) {
                        $query->select('users.id', DB::raw("CONCAT(first_name, ' ', last_name) as full_name"));
                    }]);
                }
            ])
            ->paginate($perPage);

        $copyIds = $paginator->getCollection()->pluck('id');

        $answeredKeysByCopy = Response::query()
            ->whereIn('copy_id', $copyIds)
            ->where('is_completed', true)
            ->get(['copy_id', 'user_id', 'content'])
            ->groupBy('copy_id')
            ->map(function ($responses) {
                return $responses->groupBy('user_id')->map(function ($userResponses) {
                    return $userResponses->reduce(function (Collection $carry, Response $response) {
                        $content = $response->content;

                        if (!isset($content['name'])) {
                            return $carry;
                        }

                        $key = $this->subItemKey($content['name'], $content['category'] ?? null);

                        return $carry->put($key, true); // presence is all we need for counts
                    }, collect());
                });
            });

        $paginator->getCollection()->transform(function (Copy $copy) use ($answeredKeysByCopy) {
            $answeredKeysByUser = $answeredKeysByCopy->get($copy->id, collect());

            $copy->setAttribute(
                'checklist_summary',
                $this->countsFromResponses($copy->checklist ?? [], $answeredKeysByUser)
            );

            return $copy;
        });

        if (!$status) {
            return $paginator;
        }

        // Filter the current page's items in memory (cheap — already loaded).
        $filtered = $paginator->getCollection()->filter(
            fn (Copy $copy) => $this->matchesStatus($copy, $status)
        )->values();

        // Recompute the TRUE total across the whole table using the same rule,
        // so total()/lastPage() stop lying once filtering drops rows.
        $realTotal = $this->countByStatus($status);

        return new LengthAwarePaginator(
            $filtered,
            $realTotal,
            $perPage,
            $page,
            [
                'path'     => $paginator->path(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Precedence: findings present -> consolidated (overrides ongoing)
     *             else answered > 0 -> ongoing
     *             else -> pending
     */
    protected function matchesStatus(Copy $copy, string $status): bool
    {
        $hasFindings = $copy->relationLoaded('findings') && $copy->findings->isNotEmpty();
        $hasAnswered = ($copy->checklist_summary['answered'] ?? 0) > 0;

        return match ($status) {
            'generated'    => $hasFindings,
//      'ongoing'      => !$hasFindings && $hasAnswered,
            'consolidated' => !$hasFindings && !$hasAnswered,
            default        => true,
        };
    }

    /**
     * Mirrors matchesStatus() at the query level, for an accurate total.
     * Keep this in sync with matchesStatus() — same naming caveat applies.
     */
    protected function countByStatus(string $status): int
    {
        $completedResponseCopyIds = Response::query()
            ->where('is_completed', true)
            ->distinct()
            ->pluck('copy_id');

        return Copy::query()
            ->when($status === 'generated', fn ($q) => $q->whereHas('findings'))
            ->when($status === 'consolidated', fn ($q) => $q
                ->whereDoesntHave('findings')
                ->whereNotIn('id', $completedResponseCopyIds)
            )
            ->count();
    }
    /**
     * Counts-only summary: total / answered / remaining / percent.
     * No item detail, no images — keeps the list payload small.
     */
    protected function countsFromResponses(array $sections, Collection $answeredKeysByUser): array
    {
        $answeredCount = 0;

        foreach ($sections as $section) {
            $userId = (int) ($section['user_id'] ?? 0);
            $answeredKeys = $answeredKeysByUser->get($userId, collect());

            if ($this->isSectionComplete($section, $answeredKeys)) {
                $answeredCount++;
            }
        }

        $total = count($sections);

        return [
            'total'     => $total,
            'answered'  => $answeredCount,
            'remaining' => $total - $answeredCount,
            'percent'   => $total > 0 ? round(($answeredCount / $total) * 100) : 0,
        ];
    }

    /**
     * Full detail summary for ONE copy — includes item-level answers
     * with images, ratings, remarks. This is the expensive version;
     * only ever call it for a single Copy (the detail/drill-in view),
     * never inside a paginated loop.
     */
    public function summarize(Copy $copy): array
    {
        $answersByUser = Response::query()
            ->where('copy_id', $copy->id)
            ->where('is_completed', true)
            ->with('images')
            ->get()
            ->groupBy('user_id')
            ->map(function ($responses) {
                return $responses->reduce(function (Collection $carry, Response $response) {
                    $content = $response->content;

                    if (!isset($content['name'])) {
                        return $carry;
                    }

                    $key = $this->subItemKey($content['name'], $content['category'] ?? null);

                    return $carry->put($key, [
                        'rating'      => $content['rating'] ?? null,
                        'remarks'     => $content['remarks'] ?? null,
                        'images'      => $response->images->pluck('url')->values(),
                        'answered_at' => $response->created_at,
                    ]);
                }, collect());
            });

        $sections = collect($copy->checklist ?? []);
        $answeredCount = 0;
        $sectionSummaries = [];

        foreach ($sections as $section) {
            $userId = (int) ($section['user_id'] ?? 0);
            $answersByKey = $answersByUser->get($userId, collect());

            [$isAnswered, $extra] = $this->evaluateSectionDetail($section, $answersByKey);

            if ($isAnswered) {
                $answeredCount++;
            }

            $sectionSummaries[] = array_merge([
                'section'     => $section['section'] ?? null,
                'user_id'     => $section['user_id'] ?? null,
                'is_answered' => $isAnswered,
            ], $extra);
        }

        $total = $sections->count();

        return [
            'total'     => $total,
            'answered'  => $answeredCount,
            'remaining' => $total - $answeredCount,
            'percent'   => $total > 0 ? round(($answeredCount / $total) * 100) : 0,
            'sections'  => $sectionSummaries,
        ];
    }

    /**
     * Lightweight check: is this section complete, given a set of answered keys?
     * Shared by the counts-only path (no detail needed).
     */
    protected function isSectionComplete(array $section, Collection $answeredKeys): bool
    {
        if (!empty($section['sub-sections'])) {
            foreach ($section['sub-sections'] as $subSection) {
                $requiredKeys = collect($subSection['sub-items'] ?? [])
                    ->filter(fn (array $item) => isset($item['name']))
                    ->map(fn (array $item) => $this->subItemKey($item['name'], $item['category'] ?? null));

                if ($requiredKeys->isEmpty() || !$requiredKeys->every(fn (string $key) => $answeredKeys->has($key))) {
                    return false;
                }
            }

            return !empty($section['sub-sections']);
        }

        $requiredKeys = collect($section['item'] ?? [])
            ->filter(fn (array $item) => isset($item['name']))
            ->map(fn (array $item) => $this->subItemKey($item['name'], $item['category'] ?? null));

        return $requiredKeys->isNotEmpty()
            && $requiredKeys->every(fn (string $key) => $answeredKeys->has($key));
    }

    /**
     * Full detail version: same completeness rule as isSectionComplete(),
     * but also builds item/sub-item arrays with answer payloads (images etc.)
     * for the single-copy detail view.
     */
    protected function evaluateSectionDetail(array $section, Collection $answersByKey): array
    {
        if (!empty($section['sub-sections'])) {
            $subTotal = 0;
            $subAnswered = 0;
            $subDetails = [];

            foreach ($section['sub-sections'] as $subSection) {
                $subItems = collect($subSection['sub-items'] ?? [])
                    ->filter(fn (array $item) => isset($item['name']))
                    ->map(function (array $item) use ($answersByKey) {
                        $key = $this->subItemKey($item['name'], $item['category'] ?? null);

                        return array_merge($item, ['answer' => $answersByKey->get($key)]);
                    });

                $requiredKeys = $subItems->map(
                    fn (array $item) => $this->subItemKey($item['name'], $item['category'] ?? null)
                );

                $subIsAnswered = $requiredKeys->isNotEmpty()
                    && $requiredKeys->every(fn (string $key) => $answersByKey->has($key));

                $subTotal++;
                if ($subIsAnswered) {
                    $subAnswered++;
                }

                $subDetails[] = [
                    'item'        => $subSection['item'] ?? null,
                    'is_answered' => $subIsAnswered,
                    'sub_items'   => $subItems->values()->all(),
                ];
            }

            return [$subTotal > 0 && $subAnswered === $subTotal, [
                'sub_sections'          => $subDetails,
                'sub_sections_answered' => $subAnswered,
                'sub_sections_total'    => $subTotal,
            ]];
        }

        $items = collect($section['item'] ?? [])
            ->filter(fn (array $item) => isset($item['name']))
            ->map(function (array $item) use ($answersByKey) {
                $key = $this->subItemKey($item['name'], $item['category'] ?? null);

                return array_merge($item, ['answer' => $answersByKey->get($key)]);
            });

        $requiredKeys = $items->map(fn (array $item) => $this->subItemKey($item['name'], $item['category'] ?? null));

        $isAnswered = $requiredKeys->isNotEmpty()
            && $requiredKeys->every(fn (string $key) => $answersByKey->has($key));

        return [$isAnswered, ['items' => $items->values()->all()]];
    }

    protected function subItemKey(string $name, ?string $category): string
    {
        return Str::lower(trim($name)) . '|' . Str::lower(trim((string) $category));
    }
}
