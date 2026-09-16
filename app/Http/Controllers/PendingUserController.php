<?php

namespace App\Http\Controllers;

use App\Http\Requests\PendingUserRequest;
use App\Services\PendingUserService;
use Essa\APIToolKit\Api\ApiResponse;
use Illuminate\Http\Request;

class PendingUserController extends Controller
{
    use ApiResponse;
    public function __construct(readonly PendingUserService $pendingUserService) {}

    public function index() {
        $pendingUsers = $this->pendingUserService->getPendingUsers();

        return $this->responseSuccess(
            message: 'Pending users retrieved successfully',
            data: $pendingUsers
        );
    }
    public function store(PendingUserRequest $request) {

        $data = $request->validated();
        $pendingUser = $this->pendingUserService->createPendingUser($data);

        return $this->responseCreated(
            message: 'Pending user created successfully',
            data: $pendingUser
        );
    }

    /**
     * @throws \Exception
     */
    public function changePassword(Request $request, string $employeeId)
    {
        $data = $request->validate([
            'password' => 'required|string|min:8',
        ]);

        $user = $this->pendingUserService->changePassword($data, $employeeId);

        return $this->responseSuccess(
            message: 'Password changed successfully',
            data: $user,
        );
    }
    public function resetPassword(string $employeeId) {

        $user = $this->pendingUserService->resetPassword($employeeId);

        return $this->responseSuccess(
            message: 'Password reset successfully',
            data: $user
        );
    }
}
