<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResponseRequest;
use App\Services\ResponseService;
use Essa\APIToolKit\Api\ApiResponse;

class ResponseController extends Controller
{
    use ApiResponse;
    public function __construct(
        private readonly ResponseService $responseService
    ) {}

    public function store(ResponseRequest $request)
    {
        $responses = $this->responseService->storeResponse($request->validated());

        return $this->responseSuccess('Submit Response successfully.', $responses);
    }
}
