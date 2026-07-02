<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use Essa\APIToolKit\Api\ApiResponse;

class AuthService
{
    use ApiResponse;
    public function login($request) {

        $credentials = $request->only('username', 'password');

        if (auth()->attempt($credentials)) {
            $user = auth()->user();
            $token = $user->createToken('auth_token')->plainTextToken;
            $user['token'] = $token;
            $cookie = cookie('sanctum', $token, 60 * 24);

            return $this->responseSuccess('Login successful.', [
                'user' => new UserResource($user),
                'token' => $token,
            ])->withCookie($cookie);
        }

        return $this->responseUnAuthenticated('Invalid credentials');
    }
}
