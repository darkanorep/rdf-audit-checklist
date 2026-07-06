<?php

namespace App\Http\Controllers;

use AllowDynamicProperties;
use App\Http\Requests\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

#[AllowDynamicProperties]
class RoleController extends Controller
{
    use ApiResponse;
    protected RoleService $roleService;
    public function __construct(RoleService $roleService) {
        $this->roleService = $roleService;
    }

    public function index() {
        $users = $this->roleService->getRoles();

        if ($users->isEmpty()) {
            return $this->responseNotFound('No roles found.');
        }

        return RoleResource::collection($users);
    }

    public function store(RoleRequest $request) {
        $validated = $request->validated();
        return $this->responseCreated('Role created successfully.', new RoleResource($this->roleService->createRole($validated)));
    }

    public function show($id) {
        $role = $this->findUserOrFail($id);
        if ($role instanceof JsonResponse) return $role;

        return $this->responseSuccess('Role retrieved successfully.', new RoleResource($role));
    }

    public function update(RoleRequest $request, $id) {
        $role = $this->findUserOrFail($id);
        if ($role instanceof JsonResponse) return $role;

        $validated = $request->validated();
        return $this->responseSuccess('Role updated successfully.', new RoleResource($this->roleService->updateRole($validated, $role)));
    }

    public function destroy($id) {
        $role = $this->findUserOrFail($id);
        if ($role instanceof JsonResponse) return $role;

        return $this->responseSuccess('Role status successfully changed.', $this->roleService->deleteRole($id));
    }

    private function findUserOrFail($id) {
        $role = $this->roleService->getRolebyId($id);
        return $role ?: $this->responseNotFound('Role not found.');
    }
}
