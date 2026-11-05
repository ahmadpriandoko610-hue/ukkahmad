<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $query = Event::notExpired()->with([
            'room',
            'requester'
        ])
        ->where('status', 'Disetujui');

        if ($request->filled('room_id')) {
            $query->where('room_id', $request->input('room_id'));
        }

        $events = $query->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Data kalender berhasil diambil',
            'data' => $events
        ]);
    }
}
