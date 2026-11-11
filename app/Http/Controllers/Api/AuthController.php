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
    public const TOKEN_CACHE_PREFIX = 'api_token_';
    public const TOKEN_EPOCH_CACHE_PREFIX = 'api_token_epoch_';
    public const USER_ACTIVE_TOKENS_PREFIX = 'user_active_tokens_';
    public const USER_TOKEN_EPOCH_PREFIX = 'user_token_epoch_';
    public const LEGACY_ACTIVE_TOKEN_PREFIX = 'user_active_token_';
    private const TOKEN_TTL_DAYS = 30;
    private const EPOCH_TTL_DAYS = 31;

    /**
     * Terbitkan Bearer token API baru untuk pengguna dan catat hash aktifnya.
     */
    public static function issueTokenForUser(int $userId): string
    {
        $token = 'rapi_token_' . bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $token);
        $ttl = now()->addDays(self::TOKEN_TTL_DAYS);

        $currentEpoch = Cache::get(self::USER_TOKEN_EPOCH_PREFIX . $userId);
        $epoch = is_numeric($currentEpoch) ? max(1, (int) $currentEpoch) : 1;
        if (is_numeric($currentEpoch)) {
            Cache::put(self::USER_TOKEN_EPOCH_PREFIX . $userId, $epoch, now()->addDays(self::EPOCH_TTL_DAYS));
        }

        Cache::put(self::TOKEN_CACHE_PREFIX . $tokenHash, $userId, $ttl);
        Cache::put(self::TOKEN_EPOCH_CACHE_PREFIX . $tokenHash, $epoch, $ttl);

        $activeKey = self::USER_ACTIVE_TOKENS_PREFIX . $userId;
        $activeHashes = Cache::get($activeKey, []);
        if (!is_array($activeHashes)) {
            $activeHashes = [];
        }

        $activeHashes = array_values(array_filter(
            $activeHashes,
            static fn ($hash): bool => is_string($hash) && Cache::has(self::TOKEN_CACHE_PREFIX . $hash)
        ));
        $activeHashes[] = $tokenHash;

        Cache::put($activeKey, array_values(array_unique($activeHashes)), $ttl);

        return $token;
    }

    /**
     * Cabut seluruh Bearer token API aktif milik pengguna (termasuk multi-perangkat).
     */
    public static function revokeAllTokensForUser(int $userId): void
    {
        $activeKey = self::USER_ACTIVE_TOKENS_PREFIX . $userId;
        $activeHashes = Cache::get($activeKey, []);
        if (is_array($activeHashes)) {
            foreach ($activeHashes as $tokenHash) {
                if (is_string($tokenHash) && $tokenHash !== '') {
                    Cache::forget(self::TOKEN_CACHE_PREFIX . $tokenHash);
                    Cache::forget(self::TOKEN_EPOCH_CACHE_PREFIX . $tokenHash);
                }
            }
        }
        Cache::forget($activeKey);

        $legacyActiveHash = Cache::get(self::LEGACY_ACTIVE_TOKEN_PREFIX . $userId);
        if (is_string($legacyActiveHash) && $legacyActiveHash !== '') {
            Cache::forget(self::TOKEN_CACHE_PREFIX . $legacyActiveHash);
            Cache::forget(self::TOKEN_EPOCH_CACHE_PREFIX . $legacyActiveHash);
        }
        Cache::forget(self::LEGACY_ACTIVE_TOKEN_PREFIX . $userId);

        $currentEpoch = Cache::get(self::USER_TOKEN_EPOCH_PREFIX . $userId);
        $nextEpoch = (is_numeric($currentEpoch) ? max(1, (int) $currentEpoch) : 1) + 1;
        Cache::put(self::USER_TOKEN_EPOCH_PREFIX . $userId, $nextEpoch, now()->addDays(self::EPOCH_TTL_DAYS));
    }

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
        $token = self::issueTokenForUser($user->id);

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
            $tokenHash = hash('sha256', $token);
            Cache::forget(self::TOKEN_CACHE_PREFIX . $tokenHash);
            Cache::forget(self::TOKEN_EPOCH_CACHE_PREFIX . $tokenHash);
            Cache::forget(self::TOKEN_CACHE_PREFIX . $token);

            if ($user = $request->user()) {
                $activeKey = self::USER_ACTIVE_TOKENS_PREFIX . $user->id;
                $activeHashes = Cache::get($activeKey, []);
                if (is_array($activeHashes)) {
                    $remaining = array_values(array_filter(
                        $activeHashes,
                        static fn ($hash): bool => is_string($hash)
                            && $hash !== $tokenHash
                            && Cache::has(self::TOKEN_CACHE_PREFIX . $hash)
                    ));
                    if ($remaining === []) {
                        Cache::forget($activeKey);
                    } else {
                        Cache::put($activeKey, $remaining, now()->addDays(self::TOKEN_TTL_DAYS));
                    }
                }
            }
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
