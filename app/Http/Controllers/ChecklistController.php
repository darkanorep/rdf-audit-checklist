<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Http\Requests\ChecklistRequest;
use App\Http\Resources\ChecklistResource;
use App\Http\Resources\PublishedChecklistResource;
use App\Services\ChecklistService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

#[AllowDynamicProperties]
class ChecklistController extends Controller
{
    use ApiResponse;
    protected ChecklistService $checklistService;
    public function __construct(ChecklistService $checklistService) {
        $this->checklistService = $checklistService;
    }

    public function index() {
        $checklists = $this->checklistService->getChecklist();

        if ($checklists->isEmpty()) {
            return $this->responseNotFound('No Checklists found.');
        }

        return $checklists instanceof LengthAwarePaginator
            ? $checklists->through(fn($item) => new ChecklistResource($item))
            : $this->responseSuccess('Checklists retrieved successfully.', ChecklistResource::collection($checklists));
    }

    public function store(ChecklistRequest $request) {
        $validated = $request->validated();
        return $this->responseCreated('Checklist created successfully.', new ChecklistResource($this->checklistService->createChecklist($validated)));
    }

    public function update(ChecklistRequest $request, $id) {
        $checklists = $this->findUserOrFail($id);
        if ($checklists instanceof JsonResponse) return $checklists;

        $validated = $request->validated();
        return $this->responseSuccess('Checklist updated successfully.', new ChecklistResource($this->checklistService->updateChecklist($validated, $checklists)));
    }

    public function show($id) {
        $checklists = $this->findUserOrFail($id);
        if ($checklists instanceof JsonResponse) return $checklists;

        return $this->responseSuccess('Checklist retrieved successfully.', new ChecklistResource($checklists));
    }

    public function destroy($id) {
        $checklists = $this->findUserOrFail($id);
        if ($checklists instanceof JsonResponse) return $checklists;

        return $this->responseSuccess('Checklist status successfully changed.', $this->checklistService->deleteChecklist($id));
    }

    public function publish(ChecklistRequest $request, $id)
    {
        $checklist = $this->findUserOrFail($id);
        if ($checklist instanceof JsonResponse) return $checklist;

        $validated = $request->validated();

        return $this->responseSuccess(
            'Checklist published successfully.',
            new ChecklistResource($this->checklistService->publishChecklist($validated, $checklist))
        );
    }

    public function showPublishedPerUser() {
        $userId = auth()->id();
        $checklist = $this->checklistService->getChecklistForUser($userId);

        if (!$checklist) {
            return $this->responseNotFound('No published checklist found for the user.');
        }

        return $this->responseSuccess('Published checklist retrieved successfully.', new PublishedChecklistResource($checklist));
    }

    private function findUserOrFail($id) {
        $checklist = $this->checklistService->getChecklistById($id);
        return $checklist ?: $this->responseNotFound('Checklist not found.');
    }
}
