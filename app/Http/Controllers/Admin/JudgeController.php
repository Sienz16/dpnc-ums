<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJudgeRequest;
use App\Http\Requests\Admin\UpdateJudgeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class JudgeController extends Controller
{
    public function index(): JsonResponse
    {
        $judges = User::query()
            ->where('role', UserRole::Judge)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $judges]);
    }

    public function store(StoreJudgeRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $judge = User::query()->create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => Hash::make($payload['password']),
            'role' => UserRole::Judge,
            'is_active' => $payload['is_active'] ?? true,
        ]);

        return response()->json(['data' => $judge], 201);
    }

    public function update(UpdateJudgeRequest $request, User $judge): JsonResponse
    {
        if (! $judge->isJudge()) {
            abort(422, 'User is not a judge.');
        }

        $payload = $request->validated();

        if (isset($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        }

        $judge->update($payload);

        return response()->json(['data' => $judge->fresh()]);
    }
}
