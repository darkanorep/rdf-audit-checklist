<?php

namespace App\Services;

use AllowDynamicProperties;
use App\Models\Copy;
use App\Models\Image;
use App\Models\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $userId        = $data['user_id'] ?? auth()->id();
            $now           = now();
            $completedAt   = $isCompleted ? $now : null;

            $batchNo = $this->generateBatchNo($copyId);

            return collect($contentItems)->map(function (array $content, int $index) use (
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
                    'copy_id' => $response->copy_id,
                    'content' => $response->content,
                    'images'  => $response->images->pluck('url'),
                ];
            });
        });
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

                $url = data_get($uploadFile, 'result.url');

                if ($url) {
                    Image::create([
                        'response_id' => $responseId,
                        'url' => $url,
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
