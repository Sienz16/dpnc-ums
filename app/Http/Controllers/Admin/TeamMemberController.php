<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeamMemberRequest;
use App\Http\Requests\Admin\UpdateTeamMemberRequest;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;

class TeamMemberController extends Controller
{
    public function store(StoreTeamMemberRequest $request, Team $team): JsonResponse
    {
        $member = $team->members()->create($request->validated());

        return response()->json(['data' => $member], 201);
    }

    public function update(UpdateTeamMemberRequest $request, Team $team, TeamMember $member): JsonResponse
    {
        abort_if($member->team_id !== $team->id, 404);

        $member->update($request->validated());

        return response()->json(['data' => $member->fresh()]);
    }

    public function destroy(Team $team, TeamMember $member): JsonResponse
    {
        abort_if($member->team_id !== $team->id, 404);

        $member->delete();

        return response()->json(status: 204);
    }
}
