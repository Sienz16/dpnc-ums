<?php

namespace App\Http\Controllers\Judge;

use App\Http\Controllers\Controller;
use App\Models\DebateMatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JudgeMatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $matches = DebateMatch::query()
            ->whereHas('judgeAssignments', fn ($query) => $query->where('judge_id', $request->user()->id))
            ->with(['round', 'room', 'governmentTeam', 'oppositionTeam', 'judgeAssignments', 'result'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $matches]);
    }

    public function show(DebateMatch $match): JsonResponse
    {
        $this->authorize('view', $match);

        return response()->json([
            'data' => $match->load([
                'round',
                'room',
                'governmentTeam.members',
                'oppositionTeam.members',
                'judgeAssignments.judge',
                'scoreSheets',
                'result.bestSpeaker',
            ]),
        ]);
    }
}
