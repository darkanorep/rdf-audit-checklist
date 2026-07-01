<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class UserController extends Controller
{
    use ApiResponse;
    public function __construct(UserService $user) {
        $this->user = $user;
    }

    public function index() {
        return $this->responseSuccess('Users retrieved successfully.', UserResource::collection($this->user->getUsers()));
    }

    public function store(UserRequest $request) {
        $validated = $request->validated();
        return $this->responseCreated('User created successfully.', new UserResource($this->user->createUser($validated)));
    }

    public function show($id) {
        $user = $this->findUserOrFail($id);
        if ($user instanceof JsonResponse) return $user;

        return $this->responseSuccess('User retrieved successfully.', new UserResource($user));
    }

    public function update(UserRequest $request, $id) {
        $user = $this->findUserOrFail($id);
        if ($user instanceof JsonResponse) return $user;

        $validated = $request->validated();
        return $this->responseSuccess('User updated successfully.', new UserResource($this->user->updateUser($validated, $user)));
    }

    public function destroy($id) {
        $user = $this->findUserOrFail($id);
        if ($user instanceof JsonResponse) return $user;

        return $this->responseSuccess('User status successfully changed.', $this->user->deleteUser($user));
    }

    private function findUserOrFail($id) {
        $user = $this->user->getUserById($id);
        return $user ?: $this->responseNotFound('User not found.');
    }
}
