<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Enums\MatchStatus;
use App\Domain\Debate\Services\MatchLineupService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMatchRequest;
use App\Http\Requests\Admin\UpdateMatchRequest;
use App\Models\DebateMatch;
use App\Models\JudgeAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MatchController extends Controller
{
    public function __construct(private MatchLineupService $matchLineupService) {}

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
        $match = DB::transaction(function () use ($request): DebateMatch {
            $payload = collect($request->validated());
            $lineupPayload = $payload->only(['government', 'opposition'])->all();

            $match = DebateMatch::query()->create(array_merge(
                $payload->except(['government', 'opposition'])->all(),
                ['status' => MatchStatus::Pending],
            ));

            $lineupWasProvided = collect($lineupPayload)
                ->flatten()
                ->filter(fn (mixed $value): bool => $value !== null)
                ->isNotEmpty();

            if ($lineupWasProvided) {
                $match = $this->matchLineupService->sync(
                    $match->loadMissing([
                        'governmentTeam.members',
                        'oppositionTeam.members',
                        'matchSpeakers.teamMember',
                    ]),
                    $lineupPayload,
                    $request->user(),
                );
            }

            return $match;
        });

        return response()->json([
            'data' => $this->matchLineupService->decorateMatch($match->fresh()->load([
                'round',
                'room',
                'governmentTeam.members',
                'oppositionTeam.members',
                'matchSpeakers.teamMember',
            ])),
        ], 201);
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
            'matchSpeakers.teamMember',
            'judgeAssignments.judge',
            'scoreSheets.judge',
            'scoreSheets.bestDebater',
            'result.bestSpeaker',
        ]);
        $matchData->setAttribute('unavailable_judge_ids', $unavailableJudgeIds);

        return response()->json([
            'data' => $this->matchLineupService->decorateMatch($matchData),
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
