<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Services\ScoreSheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminScoreSheetUpdateRequest;
use App\Models\DebateMatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class AdminScoreSheetController extends Controller
{
    public function __construct(private ScoreSheetService $scoreSheetService) {}

    public function update(AdminScoreSheetUpdateRequest $request, DebateMatch $match, User $judge): JsonResponse
    {
        try {
            $scoreSheet = $this->scoreSheetService->submitAsAdmin(
                $match->loadMissing(['governmentTeam.members', 'oppositionTeam.members']),
                $judge,
                $request->user(),
                $request->safe()->except('reason'),
                $request->validated('reason'),
                'admin_corrected',
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(['data' => $scoreSheet]);
    }
}
