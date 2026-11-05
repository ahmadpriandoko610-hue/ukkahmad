<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\ScheduleNotification;
use App\Models\User;

class NotificationController extends Controller
{
    protected function getAuthUser(Request $request): ?User
    {
        return $request->user();
    }

    public function index(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        $userId = $authUser->id;

        $scheduleNotifs = ScheduleNotification::with('event')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 100), 1), 100));

        $complaintNotifs = Notification::with('complaint')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(min(max((int) $request->input('per_page', 100), 1), 100));

        return response()->json([
            'success' => true,
            'message' => 'Data notifikasi berhasil diambil',
            'data' => [
                'schedule_notifications' => $scheduleNotifs->items(),
                'complaint_notifications' => $complaintNotifs->items(),
            ],
            'meta' => [
                'schedule_notifications' => [
                    'current_page' => $scheduleNotifs->currentPage(),
                    'per_page' => $scheduleNotifs->perPage(),
                    'total' => $scheduleNotifs->total(),
                    'last_page' => $scheduleNotifs->lastPage(),
                ],
                'complaint_notifications' => [
                    'current_page' => $complaintNotifs->currentPage(),
                    'per_page' => $complaintNotifs->perPage(),
                    'total' => $complaintNotifs->total(),
                    'last_page' => $complaintNotifs->lastPage(),
                ],
            ],
        ]);
    }

    public function markAsRead(Request $request, int $id)
    {
        $authUser = $this->getAuthUser($request);
        $userId = $authUser->id;

        $notification = ScheduleNotification::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            $compNotif = Notification::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if ($compNotif) {
                $compNotif->update(['is_read' => true]);
                return response()->json([
                    'success' => true,
                    'message' => 'Notifikasi berhasil ditandai sudah dibaca',
                    'data' => $compNotif
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan'
            ], 404);
        }

        $notification->update([
            'read_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil ditandai sudah dibaca',
            'data' => $notification
        ]);
    } 
}
