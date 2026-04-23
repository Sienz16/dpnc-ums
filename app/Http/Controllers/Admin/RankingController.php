<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Services\RankingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class RankingController extends Controller
{
    public function __construct(private RankingService $rankingService)
    {
    }

    public function teams(): JsonResponse
    {
        return response()->json([
            'data' => $this->rankingService->teamRankings(),
        ]);
    }

    public function speakers(): JsonResponse
    {
        return response()->json([
            'data' => $this->rankingService->speakerRankings(),
        ]);
    }
}
