<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoundRequest;
use App\Http\Requests\Admin\UpdateRoundRequest;
use App\Models\Round;
use Illuminate\Http\JsonResponse;

class RoundController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Round::query()->orderBy('sequence')->orderBy('id')->get(),
        ]);
    }

    public function store(StoreRoundRequest $request): JsonResponse
    {
        $round = Round::query()->create($request->validated());

        return response()->json(['data' => $round], 201);
    }

    public function update(UpdateRoundRequest $request, Round $round): JsonResponse
    {
        $round->update($request->validated());

        return response()->json(['data' => $round->fresh()]);
    }

    public function destroy(Round $round): JsonResponse
    {
        $round->delete();

        return response()->json(status: 204);
    }
}
