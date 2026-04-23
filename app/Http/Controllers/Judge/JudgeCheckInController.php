<?php

namespace App\Http\Controllers\Judge;

use App\Domain\Debate\Services\MatchLifecycleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Judge\CheckInMatchRequest;
use App\Models\DebateMatch;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class JudgeCheckInController extends Controller
{
    public function __construct(private MatchLifecycleService $lifecycleService)
    {
    }

    public function store(CheckInMatchRequest $request, DebateMatch $match): JsonResponse
    {
        try {
            $match = $this->lifecycleService->checkIn($match, $request->user());
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => $match]);
    }
}
