<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getUsers() {
        return User::with(['role'])->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function createUser(array $data) {
        $data['password'] = Hash::make($data['username']);
        return User::create($data);
    }

    public function getUserById($id) {
        return User::find($id);
    }

    public function updateUser(array $data, User $user) {
        return $user->update($data);
    }

    public function deleteUser($user): void {
        $user = User::withTrashed()->find($user);

        if ($user->trashed()) {
            $user->restore();
        } else {
            $user->delete();
        }
    }
}
