<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoomRequest;
use App\Http\Requests\Admin\UpdateRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Room::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = Room::query()->create($request->validated());

        return response()->json(['data' => $room], 201);
    }

    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $room->update($request->validated());

        return response()->json(['data' => $room->fresh()]);
    }

    public function destroy(Room $room): JsonResponse
    {
        $room->delete();

        return response()->json(status: 204);
    }
}
