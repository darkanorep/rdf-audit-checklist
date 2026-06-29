<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Http\Requests\UserRequest;
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
        return $this->user->getUsers();
    }

    public function store(UserRequest $request) {
        $validated = $request->validated();
        return $this->user->createUser($validated);
    }

    public function show($id) {
        return $this->user->getUserById($id);
    }

    public function update(UserRequest $request, User $user) {
        $validated = $request->validated();
        return $this->user->updateUser($validated, $user);
    }

    public function destroy($id) {
        return $this->user->deleteUser($id);
    }
}
