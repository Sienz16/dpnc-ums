<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Services\RankingService;
use App\Http\Controllers\Controller;
use App\Models\DebateMatch;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private RankingService $rankingService)
    {
    }

    public function match(DebateMatch $match): JsonResponse
    {
        return response()->json([
            'data' => $match->load([
                'round',
                'room',
                'governmentTeam.members',
                'oppositionTeam.members',
                'judgeAssignments.judge',
                'scoreSheets.bestDebater',
                'result.bestSpeaker',
            ]),
        ]);
    }

    public function tournament(): JsonResponse
    {
        return response()->json([
            'data' => [
                'team_rankings' => $this->rankingService->teamRankings(),
                'speaker_rankings' => $this->rankingService->speakerRankings(),
            ],
        ]);
    }
}
