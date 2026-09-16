<?php

namespace App\Services;

use App\Models\PendingUser;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PendingUserService
{
    public function __construct(
        private readonly PendingUser $pendingUser,
        private readonly User $user,
    ) {}

    public function getPendingUsers() {
        return $this->pendingUser->orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function createPendingUser(array $data): PendingUser|User
    {
        $employeeId = $this->formatUserIdentifier($data['id_prefix'], $data['id_no']);

        $existingUser = $this->user->withTrashed()
            ->where('employee_id', $employeeId)
            ->first();

        if ($existingUser) {
            return $this->syncExistingUser($existingUser, $data);
        }

        return $this->upsertPendingUser([
            ...$data,
            'employee_id' => $employeeId,
        ]);
    }

    private function formatUserIdentifier(string $idPrefix, int|string $idNo): string
    {
        return sprintf('%s - %s', $idPrefix, $idNo);
    }

    private function syncExistingUser(User $user, array $data): User {
        $attributes = ['username' => $data['username']];

        // Only rehash/update password when one was actually provided.
        if (!empty($data['password'])) {
            $attributes['password'] = Hash::make($data['password']);
        }

        $user->update($attributes);

        return $user;
    }

    private function upsertPendingUser(array $data) {
        $attributes = [
            'id_prefix'           => $data['id_prefix'],
            'id_no'               => $data['id_no'],
            'employee_id'         => $data['employee_id'],
            'first_name'          => $data['first_name'],
            'middle_name'         => $data['middle_name'] ?? null,
            'last_name'           => $data['last_name'],
            'suffix'              => $data['suffix'] ?? null,
            'position'            => $data['position'] ?? null,
            'charging_code'       => $data['charging_code'] ?? null,
            'charging_name'       => $data['charging_name'] ?? null,
            'company_code'        => $data['company_code'] ?? null,
            'company_name'        => $data['company_name'] ?? null,
            'business_unit_code'  => $data['business_unit_code'] ?? null,
            'business_unit_name'  => $data['business_unit_name'] ?? null,
            'department_code'     => $data['department_code'] ?? null,
            'department_name'     => $data['department_name'] ?? null,
            'unit_code'           => $data['unit_code'] ?? null,
            'unit_name'           => $data['unit_name'] ?? null,
            'sub_unit_code'       => $data['sub_unit_code'] ?? null,
            'sub_unit_name'       => $data['sub_unit_name'] ?? null,
            'location_code'       => $data['location_code'] ?? null,
            'location_name'       => $data['location_name'] ?? null,
            'username'            => $data['username'],
        ];

        $this->pendingUser->withTrashed()->upsert(
            $attributes,
            uniqueBy: ['id_prefix', 'id_no'],
            update: array_keys(
            // Never let the unique key itself land in the UPDATE clause.
                collect($attributes)->except('employee_id')->toArray()
            ),
        );

        return $this->pendingUser->withTrashed()
            ->where([
                'id_prefix' => $data['id_prefix'],
                'id_no' => $data['id_no'],
            ])
            ->firstOrFail();
    }

    public function changePassword(array $data, string $employeeId)
    {
        $user = $this->user->withTrashed()
            ->where('employee_id', $employeeId)
            ->first();

        if (!$user) {
            throw new ModelNotFoundException("User with employee_id [{$employeeId}] not found.");
        }

        if ($user->password && Hash::check($data['password'], $user->password)) {
            throw new Exception('The new password cannot be the same as the current password.');
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return $user;
    }
    public function resetPassword(string $employeeId) {

        $user = $this->user->withTrashed()
            ->where('employee_id', $employeeId)
            ->first();

        if (!$user) {
            throw new ModelNotFoundException("User not found.");
        }

        $user->forceFill([
            'password' => Hash::make($user->username)
        ])->save();

        return $user;
    }
}
