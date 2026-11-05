<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login API untuk Flutter Mobile.
     * Mendukung login dengan Email atau Username (nama akun).
     * Admin DITOLAK dari mobile sesuai instruksi proyek.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'nullable|string|max:255',
            'username' => 'nullable|string|max:255',
            'password' => 'required|string|max:255',
        ]);

        $loginInput = trim((string)($request->input('email') ?? $request->input('username') ?? ''));
        $password = (string)$request->input('password', '');

        if ($loginInput === '') {
            return response()->json([
                'success' => false,
                'message' => 'Email atau username wajib diisi.'
            ], 422);
        }

        if ($password === '') {
            return response()->json([
                'success' => false,
                'message' => 'Password wajib diisi.'
            ], 422);
        }

        // Cari user berdasarkan email atau nama (username), case-insensitive
        $user = User::where('email', $loginInput)
            ->orWhereRaw('LOWER(name) = ?', [strtolower($loginInput)])
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah. Silakan periksa kembali akun Anda.'
            ], 401);
        }

        // Tolak akses mobile untuk Admin (Admin menggunakan website Laravel)
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator hanya dapat diakses melalui website RAPI-Sekolah.'
            ], 403);
        }

        // Token bearer berumur 30 hari, divalidasi middleware api.token.
        $token = 'rapi_token_' . bin2hex(random_bytes(24));
        Cache::put('api_token_' . hash('sha256', $token), $user->id, now()->addDays(30));

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil. Selamat datang, ' . $user->name,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => $user->role_label,
                'phone' => $user->phone ?? '',
                'token' => $token,
                'dashboard' => $this->dashboardData($user),
            ]
        ]);
    }

    /**
     * Menghasilkan data metrik dan informasi awal dashboard sesuai role.
     */
    public function dashboardData(User $user): array
    {
        if ($user->role === 'admin') {
            return [
                'metrics' => [
                    ['label' => 'User', 'value' => User::count()],
                    ['label' => 'Ruangan', 'value' => Room::count()],
                    ['label' => 'Agenda', 'value' => Event::notExpired()->count()],
                    ['label' => 'Pengaduan', 'value' => Complaint::visibleInActiveList()->count()],
                ],
            ];
        }

        if ($user->role === 'pic') {
            $rooms = Room::where('pic_id', $user->id)
                ->where('is_active', true)
                ->orderBy('room_name')
                ->get(['id', 'room_name', 'location', 'capacity']);

            $eventsForPic = fn () => Event::notExpired()
                ->whereHas('room', fn ($query) => $query->where('pic_id', $user->id));
            $complaintsForPic = fn ($status) => Complaint::visibleInActiveList()
                ->where('status_repair', $status)
                ->whereHas('room', fn ($query) => $query->where('pic_id', $user->id));

            return [
                'metrics' => [
                    ['label' => 'Agenda Masuk', 'value' => $eventsForPic()->where('status', 'Menunggu')->count()],
                    ['label' => 'Disetujui', 'value' => $eventsForPic()->where('status', 'Disetujui')->count()],
                    ['label' => 'Ditolak', 'value' => $eventsForPic()->where('status', 'Ditolak')->count()],
                    ['label' => 'Pengaduan Baru', 'value' => $complaintsForPic('Belum Diperbaiki')->count()],
                    ['label' => 'Sedang Diperbaiki', 'value' => $complaintsForPic('Menunggu Spare Part')->count()],
                    ['label' => 'Sudah Diperbaiki', 'value' => $complaintsForPic('Sudah Diperbaiki')->count()],
                ],
                'rooms' => $rooms,
            ];
        }

        if ($user->role === 'guru') {
            $myEvents = Event::notExpired()->where('requester_id', $user->id);

            $recentSubmissions = (clone $myEvents)
                ->with('room')
                ->orderByDesc('id')
                ->take(5)
                ->get();

            $upcomingEvents = Event::notExpired()
                ->where('status', 'Disetujui')
                ->whereDate('date', '>=', now()->toDateString())
                ->with('room')
                ->orderBy('date')
                ->orderBy('start_time')
                ->take(5)
                ->get();

            $complaintsCount = Complaint::visibleInActiveList()
                ->where('reporter_id', $user->id)
                ->count();

            return [
                'metrics' => [
                    ['label' => 'Agenda Saya', 'value' => (clone $myEvents)->count()],
                    ['label' => 'Disetujui', 'value' => (clone $myEvents)->where('status', 'Disetujui')->count()],
                    ['label' => 'Menunggu', 'value' => (clone $myEvents)->where('status', 'Menunggu')->count()],
                    ['label' => 'Pengaduan Saya', 'value' => $complaintsCount],
                ],
                'recent_submissions' => $recentSubmissions,
                'upcoming_events' => $upcomingEvents,
            ];
        }

        if ($user->role === 'siswa') {
            $upcomingEvents = Event::notExpired()
                ->where('status', 'Disetujui')
                ->whereDate('date', '>=', now()->toDateString())
                ->with('room')
                ->orderBy('date')
                ->orderBy('start_time')
                ->take(5)
                ->get();

            return [
                'metrics' => [
                    ['label' => 'Agenda Disetujui', 'value' => Event::notExpired()->where('status', 'Disetujui')->count()],
                ],
                'upcoming_events' => $upcomingEvents,
            ];
        }

        return ['metrics' => []];
    }

    /**
     * Logout API. Menghapus sesi token dari cache.
     */
    public function logout(Request $request)
    {
        if ($token = $request->bearerToken()) {
            Cache::forget('api_token_' . hash('sha256', $token));
            Cache::forget('api_token_' . $token);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    /**
     * Ambil data profil & dashboard terbaru untuk user aktif.
     */
    public function profile(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akun Administrator hanya dapat diakses melalui website RAPI-Sekolah.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data profile berhasil diambil',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => $user->role_label,
                'phone' => $user->phone ?? '',
                'dashboard' => $this->dashboardData($user),
            ]
        ]);
    }
}
