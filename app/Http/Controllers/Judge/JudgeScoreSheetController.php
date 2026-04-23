<?php

namespace App\Http\Controllers\Judge;

use App\Domain\Debate\Services\ScoreSheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Judge\SaveScoreSheetRequest;
use App\Http\Requests\Judge\SubmitScoreSheetRequest;
use App\Models\DebateMatch;
use App\Models\ScoreSheet;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class JudgeScoreSheetController extends Controller
{
    public function __construct(private ScoreSheetService $scoreSheetService)
    {
    }

    public function show(DebateMatch $match): JsonResponse
    {
        $this->authorize('view', $match);

        $scoreSheet = ScoreSheet::query()
            ->where('match_id', $match->id)
            ->where('judge_id', auth()->id())
            ->first();

        return response()->json(['data' => $scoreSheet]);
    }

    public function saveDraft(SaveScoreSheetRequest $request, DebateMatch $match): JsonResponse
    {
        try {
            $scoreSheet = $this->scoreSheetService->saveDraft(
                $match->loadMissing(['governmentTeam.members', 'oppositionTeam.members']),
                $request->user(),
                $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => $scoreSheet]);
    }

    public function submit(SubmitScoreSheetRequest $request, DebateMatch $match): JsonResponse
    {
        try {
            $scoreSheet = $this->scoreSheetService->submit(
                $match->loadMissing(['governmentTeam.members', 'oppositionTeam.members']),
                $request->user(),
                $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => $scoreSheet]);
    }
}
