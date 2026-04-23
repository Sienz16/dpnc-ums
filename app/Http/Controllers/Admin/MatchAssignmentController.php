<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Services\JudgeAssignmentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ManualJudgeAssignmentRequest;
use App\Http\Requests\Admin\RandomJudgeAssignmentRequest;
use App\Models\DebateMatch;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class MatchAssignmentController extends Controller
{
    public function __construct(private JudgeAssignmentService $assignmentService)
    {
    }

    public function manual(ManualJudgeAssignmentRequest $request, DebateMatch $match): JsonResponse
    {
        try {
            $assignments = $this->assignmentService->assignManual($match, $request->validated('judge_ids'));
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => $assignments->values()]);
    }

    public function randomize(RandomJudgeAssignmentRequest $request, DebateMatch $match): JsonResponse
    {
        try {
            $assignments = $this->assignmentService->assignRandom(
                $match,
                $request->validated('eligible_judge_ids'),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => $assignments->values()]);
    }

    public function clear(DebateMatch $match): JsonResponse
    {
        try {
            $this->assignmentService->clear($match);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => []]);
    }
}
