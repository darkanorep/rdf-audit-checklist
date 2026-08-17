<?php

namespace App\Http\Controllers;

use App\Services\PublishChecklistService;
use Illuminate\Http\Request;

class PublishChecklistController extends Controller
{
    public function __construct(
        protected PublishChecklistService $checklistService
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $status = $request->input('status');

        $copies = $this->checklistService->paginateWithSummary($request->all());

        return response()->json($copies);
    }
}
