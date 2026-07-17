<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Http\Requests\ChecklistRequest;
use App\Http\Requests\CopyRequest;
use App\Http\Resources\ChecklistResource;
use App\Services\CopyService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

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

    public function showPublishedPerUser() {
        $userId = auth()->id();
        $checklist = $this->copyService->getChecklistForUser($userId);

        if (!$checklist) {
            return $this->responseNotFound('No published checklist found for the user.');
        }

        return $this->responseSuccess('Published checklist retrieved successfully.', new ChecklistResource($checklist));
    }
}
