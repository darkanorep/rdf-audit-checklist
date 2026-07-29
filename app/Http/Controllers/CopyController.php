<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Http\Requests\ChecklistRequest;
use App\Http\Requests\CopyRequest;
use App\Http\Resources\ChecklistResource;
use App\Services\CopyService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

#[AllowDynamicProperties]
class CopyController extends Controller
{
    use ApiResponse;
    public function __construct(CopyService $copyService)
    {
        $this->copyService = $copyService;
    }

    public function publish(CopyRequest $request)
    {
        $validated = $request->validated();
        $this->copyService->publish($validated);

        return $this->responseSuccess('Checklist published successfully.');
    }

    public function showPublishedPerUser(Request $request)
    {
        $userId = auth()->id();
        $perPage = $request->integer('per_page', 15);

        $checklists = $this->copyService->getChecklistForUser($userId, $perPage);

        if ($checklists->isEmpty()) {
            return $this->responseNotFound('No published checklist found for the user.');
        }

        return $checklists instanceof LengthAwarePaginator
            ? $checklists->through(fn ($item) => new ChecklistResource($item))
            : $this->responseSuccess('Published checklist retrieved successfully.', ChecklistResource::collection($checklists));
    }
}
