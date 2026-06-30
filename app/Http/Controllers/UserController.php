<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class UserController extends Controller
{
    public function __construct(UserService $user) {
        $this->user = $user;
    }

    public function index() {
        return UserResource::collection($this->user->getUsers());
    }

    public function store(UserRequest $request) {
        $validated = $request->validated();
        return new UserResource($this->user->createUser($validated));
    }

    public function show(User $user) {
        return new UserResource($user);
    }

    public function update(UserRequest $request, User $user) {
        $validated = $request->validated();
        return new UserResource($this->user->updateUser($validated, $user));
    }

    public function destroy($id) {
        return $this->user->deleteUser($id);
    }
}
