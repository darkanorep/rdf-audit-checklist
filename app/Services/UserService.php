<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getUsers() {
        return User::all();
    }
    public function createUser(array $data) {
        $data['password'] = Hash::make($data['username']);
        return User::create($data);
    }

    public function getUserById($id) {
        return User::findOrFail($id);
    }

    public function updateUser(array $data, User $user) {
        return $user->update($data);
    }

    public function deleteUser($user) {
        $user = User::withTrashed()->find($user);

        if ($user->trashed()) {
            $user->restore();
        } else {
            $user->delete();
        }

        return $user;
    }
}
