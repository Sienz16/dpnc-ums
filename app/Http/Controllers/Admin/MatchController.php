<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMatchRequest;
use App\Http\Requests\Admin\UpdateMatchRequest;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use Illuminate\Http\JsonResponse;

class MatchController extends Controller
{
    public function index(): JsonResponse
    {
        $matches = DebateMatch::query()
            ->with(['round', 'room', 'governmentTeam', 'oppositionTeam', 'judgeAssignments', 'result'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $matches]);
    }

    public function store(StoreMatchRequest $request): JsonResponse
    {
        $match = DebateMatch::query()->create(array_merge(
            $request->validated(),
            ['status' => MatchStatus::Pending],
        ));

        return response()->json(['data' => $match->fresh()], 201);
    }

    public function show(DebateMatch $match): JsonResponse
    {
        $unavailableJudgeIds = JudgeAssignment::query()
            ->whereHas('match', function ($query) use ($match): void {
                $query->where('round_id', $match->round_id)
                    ->whereKeyNot($match->id);
            })
            ->pluck('judge_id')
            ->unique()
            ->values()
            ->all();

        $matchData = $match->load([
            'round',
            'room',
            'governmentTeam.members',
            'oppositionTeam.members',
            'judgeAssignments.judge',
            'scoreSheets.bestDebater',
            'result.bestSpeaker',
        ]);
        $matchData->setAttribute('unavailable_judge_ids', $unavailableJudgeIds);

        return response()->json([
            'data' => $matchData,
        ]);
    }

    public function update(UpdateMatchRequest $request, DebateMatch $match): JsonResponse
    {
        if ($match->status !== MatchStatus::Pending) {
            abort(422, 'Only pending matches can be updated.');
        }

        $match->update($request->validated());

        return response()->json(['data' => $match->fresh()]);
    }

    public function destroy(DebateMatch $match): JsonResponse
    {
        if ($match->status !== MatchStatus::Pending) {
            abort(422, 'Only pending matches can be deleted.');
        }

        $match->delete();

        return response()->json(status: 204);
    }
}
