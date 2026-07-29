<?php

namespace App\Services;

use AllowDynamicProperties;
use App\Models\Copy;
use App\Models\Image;
use App\Models\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ImageKit\ImageKit;
use InvalidArgumentException;

#[AllowDynamicProperties]
class ResponseService
{
    public function __construct() {
        $this->imageKit = new ImageKit(
            config('app.imagekit_public_key'),
            config('app.imagekit_private_key'),
            config('app.imagekit_url_endpoint')
        );
    }

    /**
     * Store one or more checklist responses and, for every section touched
     * in this batch, re-evaluate (against ALL of this user's responses for
     * the copy — not just this batch) whether each sub-section — and, once
     * all sub-sections are complete, the section itself — is now fully
     * answered, flipping is_answered on the Copy's checklist JSON as needed.
     */
    public function storeResponse(array $data)
    {
        return DB::transaction(function () use ($data) {
            $contentItems = $data['content'] ?? [];

            if (empty($contentItems)) {
                throw new InvalidArgumentException('At least one content item is required.');
            }

            $imagesByIndex = $data['image'] ?? [];
            $copyId        = $data['copy_id'];
            $isCompleted   = filter_var($data['is_completed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $userId        = (int) ($data['user_id'] ?? auth()->id());
            $now           = now();
            $completedAt   = $isCompleted ? $now : null;

            $copy = Copy::whereKey($copyId)->lockForUpdate()->firstOrFail();
            $checklist = $copy->checklist ?? [];

            $incomingBatchNo = $data['batch_no'] ?? null;

            if ($incomingBatchNo) {
                // Overwriting an existing batch (draft edit). Verify it belongs
                // to this copy+user and isn't already finalized before touching it.
                $existing = Response::query()
                    ->where('copy_id', $copyId)
                    ->where('user_id', $userId)
                    ->where('batch_no', $incomingBatchNo)
                    ->with('images')
                    ->get();

                if ($existing->isEmpty()) {
                    throw new InvalidArgumentException(
                        "No existing batch found for batch_no \"{$incomingBatchNo}\"."
                    );
                }

                if ($existing->contains(fn (Response $r) => $r->is_completed)) {
                    throw new InvalidArgumentException(
                        "Batch \"{$incomingBatchNo}\" has already been submitted as final and cannot be overwritten."
                    );
                }

                foreach ($existing as $oldResponse) {
                    $this->deleteImages($oldResponse->images);
                }

                $existing->each->delete();

                $batchNo = $incomingBatchNo;
            } else {
                // First-time save — generate a fresh batch_no.
                $batchNo = $this->generateBatchNo($copyId);
            }

            $results = collect($contentItems)->map(function (array $content, int $index) use (
                $copyId, $batchNo, $isCompleted, $completedAt, $now, $userId, $imagesByIndex
            ) {
                $response = Response::create([
                    'copy_id'      => $copyId,
                    'content'      => $content,
                    'batch_no'     => $batchNo,
                    'is_completed' => $isCompleted,
                    'start_at'     => $now,
                    'completed_at' => $completedAt,
                    'user_id'      => $userId,
                ]);

                $this->storeImages($imagesByIndex[$index] ?? [], $response->id);

                return [
                    'copy_id'  => $response->copy_id,
                    'content'  => $response->content,
                    'images'   => $response->images->pluck('url'),
                    'batch_no' => $response->batch_no,
                ];
            });

            // Only evaluate/roll up is_answered on a real "Submit"
            // (is_completed: 1), not a "Save as draft" (is_completed: 0).
            if ($isCompleted) {
                $touchedSections = collect($contentItems)->pluck('section')->filter()->unique();
                $checklistDirty  = false;

                foreach ($touchedSections as $section) {
                    if ($this->markSectionAnsweredIfComplete($copy->id, $checklist, $section, $userId)) {
                        $checklistDirty = true;
                    }
                }

                if ($checklistDirty) {
                    $copy->checklist = $checklist;
                    $copy->save();
                }
            }

            return $results;
        });
    }

    /**
     * Mutates $checklist in place for the given section (owned by $userId):
     *
     *   - Shape A (sub-sections[] present): each sub-section's is_answered
     *     flips true once all its sub-items have a matching Response; once
     *     ALL sub-sections are answered, the section's is_answered flips too.
     *   - Shape B (flat item[], no sub-sections): section's is_answered
     *     flips true once all its items have a matching Response.
     */
    protected function markSectionAnsweredIfComplete(int $copyId, array &$checklist, string $section, int $userId): bool
    {
        $sectionIndex = null;

        foreach ($checklist as $i => $entry) {
            if (
                isset($entry['section'], $entry['user_id'])
                && $this->sameSection($entry['section'], $section)
                && (int) $entry['user_id'] === $userId
            ) {
                $sectionIndex = $i;
                break;
            }
        }

        if ($sectionIndex === null) {
            Log::warning('Checklist section not found for user during response submission.', [
                'copy_id' => $copyId,
                'user_id' => $userId,
                'section' => $section,
            ]);

            return false;
        }

        if (!empty($checklist[$sectionIndex]['is_answered'])) {
            return false;
        }

        $answeredKeys = $this->fetchAnsweredKeys($copyId, $userId, $section);
        $dirty = false;

        if (!empty($checklist[$sectionIndex]['sub-sections'])) {
            $allSubSectionsAnswered = true;

            foreach ($checklist[$sectionIndex]['sub-sections'] as $j => $subSection) {
                if (!empty($subSection['is_answered'])) {
                    continue;
                }

                $requiredKeys = [];

                foreach ($subSection['sub-items'] ?? [] as $subItem) {
                    if (isset($subItem['name'])) {
                        $requiredKeys[] = $this->subItemKey($subItem['name'], $subItem['category'] ?? null);
                    }
                }

                if (empty($requiredKeys)) {
                    Log::warning('Checklist sub-section has no sub-items defined; cannot evaluate completeness.', [
                        'copy_id'     => $copyId,
                        'user_id'     => $userId,
                        'section'     => $section,
                        'sub_section' => $subSection['item'] ?? null,
                    ]);

                    $allSubSectionsAnswered = false;
                    continue;
                }

                $subSectionComplete = collect($requiredKeys)->every(fn (string $key) => $answeredKeys->has($key));

                if ($subSectionComplete) {
                    $checklist[$sectionIndex]['sub-sections'][$j]['is_answered'] = 1;
                    $dirty = true;
                } else {
                    $allSubSectionsAnswered = false;
                }
            }

            if ($allSubSectionsAnswered) {
                $checklist[$sectionIndex]['is_answered'] = 1;
                $dirty = true;
            }

            return $dirty;
        }

        // Shape B: flat item[] with no sub-sections at all.
        $requiredKeys = [];

        foreach ($checklist[$sectionIndex]['item'] ?? [] as $item) {
            if (isset($item['name'])) {
                $requiredKeys[] = $this->subItemKey($item['name'], $item['category'] ?? null);
            }
        }

        if (empty($requiredKeys)) {
            Log::warning('Checklist section has no items defined; cannot evaluate completeness.', [
                'copy_id' => $copyId,
                'user_id' => $userId,
                'section' => $section,
            ]);

            return false;
        }

        if (collect($requiredKeys)->every(fn (string $key) => $answeredKeys->has($key))) {
            $checklist[$sectionIndex]['is_answered'] = 1;
            $dirty = true;
        }

        return $dirty;
    }

    /**
     * Fetches the set of "name|category" keys this user has already
     * submitted a Response for, within the given section, on this copy.
     */
    protected function fetchAnsweredKeys(int $copyId, int $userId, string $section)
    {
        return Response::query()
            ->where('copy_id', $copyId)
            ->where('user_id', $userId)
            ->get(['content'])
            ->map(function ($response) use ($section) {
                $content = $response->content; // array-cast JSON column

                if (!isset($content['section'], $content['name']) || !$this->sameSection($content['section'], $section)) {
                    return null;
                }

                return $this->subItemKey($content['name'], $content['category'] ?? null);
            })
            ->filter()
            ->unique()
            ->flip(); // O(1) has() lookups
    }

    /**
     * Builds a normalized (case/whitespace-insensitive) key combining
     * name + category. Sub-section is deliberately NOT part of this key —
     * the frontend does not currently submit a sub-section identifier, so
     * matching on it would always fail. If two sub-sections within the same
     * section ever end up with an identically-named question in the same
     * category, this key would conflate them; revisit if that becomes
     * a real scenario (add a stable sub-item id to the checklist schema
     * instead of relying on name+category matching at all).
     */
    protected function subItemKey(string $name, ?string $category): string
    {
        return Str::lower(trim($name)) . '|' . Str::lower(trim((string) $category));
    }

    protected function sameSection(string $a, string $b): bool
    {
        return Str::lower(trim($a)) === Str::lower(trim($b));
    }

    private function storeImages($images, int $responseId): void
    {
        $imagesToProcess = ! is_array($images) ? [$images] : $images;

        foreach ($imagesToProcess as $image) {
            if (! $image || ! method_exists($image, 'getRealPath')) {
                continue;
            }

            $handle = null;

            try {
                $fileName = time().'_'.uniqid().'_'.$image->getClientOriginalName();
                $handle = fopen($image->getRealPath(), 'r');

                $uploadFile = $this->imageKit->uploadFile([
                    'file' => $handle,
                    'fileName' => $fileName,
                ]);

                $url    = data_get($uploadFile, 'result.url');
                $fileId = data_get($uploadFile, 'result.fileId'); // needed for future deletion

                if ($url) {
                    Image::create([
                        'response_id' => $responseId,
                        'url'         => $url,
                        'file_id'     => $fileId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('ImageKit upload failed: '.$e->getMessage(), [
                    'response_id' => $responseId,
                    'file_name' => $image->getClientOriginalName(),
                ]);
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }
    }

    /**
     * Deletes the given images both from ImageKit and from the database.
     * Images uploaded before the file_id column existed (file_id === null)
     * can only be removed from the DB — the remote file is orphaned and left
     * in place; see the migration/rollout notes for why this is acceptable.
     */
    private function deleteImages(iterable $images): void
    {
        foreach ($images as $image) {
            if ($image->file_id) {
                try {
                    $this->imageKit->deleteFile($image->file_id);
                } catch (\Exception $e) {
                    Log::error('ImageKit delete failed: '.$e->getMessage(), [
                        'image_id' => $image->id,
                        'file_id'  => $image->file_id,
                    ]);
                    // Don't rethrow — a failed remote delete shouldn't block
                    // the draft overwrite; the DB row still gets removed below.
                    // Orphaned remote file becomes a cleanup concern, not a
                    // blocker for the user's save action.
                }
            }

            $image->delete();
        }
    }

    private function generateBatchNo(int $copyId): int
    {
        $copy = Copy::where('id', $copyId)->lockForUpdate()->first();

        if (! $copy) {
            throw new ModelNotFoundException("Copy [{$copyId}] not found.");
        }

        $lastBatchNo = Response::where('copy_id', $copyId)->max('batch_no');

        return ($lastBatchNo ?? 0) + 1;
    }
}
