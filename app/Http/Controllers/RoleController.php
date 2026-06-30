<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\Request;

#[AllowDynamicProperties]
class RoleController extends Controller
{
    public function __construct(RoleService $roleService) {
        $this->roleService = $roleService;
    }

    public function index() {
       return RoleResource::collection($this->roleService->getRoles());
    }

    public function store(RoleRequest $request) {
        $validated = $request->validated();
        return new RoleResource($this->roleService->createRole($validated));
    }

    public function show($id) {
        return new RoleResource($this->roleService->getRoleById($id));
    }

    public function update(RoleRequest $request, Role $role) {
        $validated = $request->validated();
        return new RoleResource($this->roleService->updateRole($validated, $role));
    }

    public function destroy($id) {
        return $this->roleService->deleteRole($id);
    }
}
