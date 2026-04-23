<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Services\MatchLifecycleService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ForceCompleteMatchRequest;
use App\Http\Requests\Admin\ReopenMatchRequest;
use App\Models\DebateMatch;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class MatchLifecycleController extends Controller
{
    public function __construct(private MatchLifecycleService $lifecycleService)
    {
    }

    public function forceComplete(ForceCompleteMatchRequest $request, DebateMatch $match): JsonResponse
    {
        try {
            $match = $this->lifecycleService->forceComplete(
                $match,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => $match]);
    }

    public function reopen(ReopenMatchRequest $request, DebateMatch $match): JsonResponse
    {
        try {
            $match = $this->lifecycleService->reopen(
                $match,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => $match]);
    }
}
