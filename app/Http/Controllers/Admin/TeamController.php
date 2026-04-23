<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeamRequest;
use App\Http\Requests\Admin\UpdateTeamRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Team::query()->with('members')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $team = Team::query()->create($request->validated());

        return response()->json(['data' => $team], 201);
    }

    public function show(Team $team): JsonResponse
    {
        return response()->json([
            'data' => $team->load('members'),
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $team->update($request->validated());

        return response()->json(['data' => $team->fresh()->load('members')]);
    }

    public function destroy(Team $team): JsonResponse
    {
        $team->delete();

        return response()->json(status: 204);
    }
}
