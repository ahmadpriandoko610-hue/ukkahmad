<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    protected function getAuthUser(Request $request): ?User
    {
        return $request->user();
    }

    public function index(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        $filters = $request->validate([
            'pic_id' => 'sometimes|integer|exists:users,id',
            'my_rooms' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = Room::with('pic')
            ->where('is_active', true)
            ->orderBy('room_name');

        if (isset($filters['pic_id'])) {
            $query->where('pic_id', $filters['pic_id']);
        } elseif (($filters['my_rooms'] ?? false) && $authUser && $authUser->role === 'pic') {
            $query->where('pic_id', $authUser->id);
        }

        $rooms = $query->paginate($filters['per_page'] ?? 100);

        return response()->json([
            'success' => true,
            'message' => 'Data ruangan berhasil diambil',
            'data' => $rooms->items(),
            'meta' => [
                'current_page' => $rooms->currentPage(),
                'per_page' => $rooms->perPage(),
                'total' => $rooms->total(),
                'last_page' => $rooms->lastPage(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $room = Room::with(['pic', 'events' => function ($q) {
            $q->notExpired()->where('status', 'Disetujui')->orderBy('date')->orderBy('start_time');
        }])
        ->where('is_active', true)
        ->find($id);

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail ruangan berhasil diambil',
            'data' => $room
        ]);
    }
}
