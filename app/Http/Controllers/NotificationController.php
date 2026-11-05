<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\User;

/**
 * NotificationController - Mengelola notifikasi pengaduan
 */
class NotificationController extends Controller
{
    /**
     * Menandai semua notifikasi pengaduan
     * milik user yang sedang login sebagai sudah dibaca.
     */
    public function markAsRead()
    {
        /** @var User $user */
        $user = Auth::user();
        
        $user->complaintNotifications()
            ->where('is_read', false)
            ->update([
                'is_read' => true,
            ]);

        return response()->json([
            'success' => true,
        ]);
    }
}