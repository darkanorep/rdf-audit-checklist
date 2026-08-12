<?php

namespace App\Http\Controllers;

use App\Services\FindingService;
use Illuminate\Http\Request;

class FindingController extends Controller
{
    public function __construct(private readonly FindingService $findingService) {}

//    public function index() {
//        return $this->findingService->getFindings();
//    }
    public function store(Request $request) {

        return $this->findingService->storeFindings($request->all());
    }
}
