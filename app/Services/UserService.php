<?php

namespace App\Services;

use App\Models\PendingUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(private readonly PendingUser $pendingUser) {}
    public function getUsers() {
        return User::with(['role'])->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function createUser(array $data): User
    {
        $data['password'] = Hash::make($data['username']);

        return DB::transaction(function () use ($data) {
            $user = User::create($data);

            $this->pendingUser
                ->where('employee_id', $data['employee_id'])
                ->update(['is_created' => true]);

            $this->pendingUser
                ->where('employee_id', $data['employee_id'])
                ->delete();

            return $user;
        });
    }

    public function getUserById($id) {
        return User::withTrashed()->findOrFail($id);
    }

    public function updateUser(array $data, User $user) {
        $user->update($data);
        return $user->fresh();
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
