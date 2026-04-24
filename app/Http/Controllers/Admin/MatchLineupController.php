<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Services\MatchLineupService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMatchLineupRequest;
use App\Models\DebateMatch;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class MatchLineupController extends Controller
{
    public function __construct(private MatchLineupService $matchLineupService) {}

    public function update(UpdateMatchLineupRequest $request, DebateMatch $match): JsonResponse
    {
        try {
            $match = $this->matchLineupService->sync(
                $match->loadMissing([
                    'governmentTeam.members',
                    'oppositionTeam.members',
                    'matchSpeakers.teamMember',
                ]),
                $request->validated(),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json([
            'data' => $this->matchLineupService->decorateMatch(
                $match->load([
                    'round',
                    'room',
                    'governmentTeam.members',
                    'oppositionTeam.members',
                    'judgeAssignments.judge',
                    'scoreSheets.judge',
                    'scoreSheets.bestDebater',
                    'result.bestSpeaker',
                    'matchSpeakers.teamMember',
                ]),
            ),
        ]);
    }
}
