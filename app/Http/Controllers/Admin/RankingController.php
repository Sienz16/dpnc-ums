<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Services\RankingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankingService) {}

    public function teams(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ranking_sequence' => ['sometimes', 'array', 'list'],
            'ranking_sequence.*' => ['string', 'distinct', Rule::in(['win', 'margin', 'marks', 'judge'])],
            'round_ids' => ['sometimes', 'array', 'list'],
            'round_ids.*' => ['integer', 'exists:rounds,id'],
        ]);

        return response()->json([
            'data' => $this->rankingService->teamRankings(
                rankingSequence: $validated['ranking_sequence'] ?? [],
                roundIds: $validated['round_ids'] ?? [],
            ),
        ]);
    }

    public function speakers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'round_ids' => ['sometimes', 'array', 'list'],
            'round_ids.*' => ['integer', 'exists:rounds,id'],
        ]);

        return response()->json([
            'data' => $this->rankingService->speakerRankings(
                roundIds: $validated['round_ids'] ?? [],
            ),
        ]);
    }
}
