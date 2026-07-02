<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Services\AuthService;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class AuthController extends Controller
{
    public function __construct(AuthService $authService) {
        $this->authService = $authService;
    }

    public function login(Request $request) {
        return $this->authService->login($request);
    }
}
