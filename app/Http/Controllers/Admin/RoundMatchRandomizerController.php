<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Debate\Services\RoundMatchRandomizer;
use App\Http\Controllers\Controller;
use App\Models\Round;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class RoundMatchRandomizerController extends Controller
{
    public function __construct(private RoundMatchRandomizer $randomizer) {}

    public function __invoke(Round $round): JsonResponse
    {
        try {
            $result = $this->randomizer->randomize($round);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json([
            'data' => [
                'matches' => $result['matches']->values(),
                'created_matches_count' => $result['matches']->count(),
                'unpaired_team' => $result['unpaired_team'],
            ],
        ], 201);
    }
}
