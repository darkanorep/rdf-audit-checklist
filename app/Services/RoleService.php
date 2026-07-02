<?php

namespace App\Services;

use App\Models\Role;

class RoleService
{
    public function getRoles() {
        return Role::orderBy('updated_at', 'desc')->useFilters()->dynamicPaginate();
    }

    public function createRole(array $data) {
        return Role::create($data);
    }

    public function getRolebyId($id) {
        return Role::withTrashed()->findOrFail($id);
    }

    public function updateRole(array $data, Role $role) {
        $role->update($data);
        return $role->fresh();
    }

    public function deleteRole($role) : void {
        $role = Role::withTrashed()->find($role);

        if ($role->trashed()) {
            $role->restore();
        } else {
            $role->delete();
        }
    }
}
