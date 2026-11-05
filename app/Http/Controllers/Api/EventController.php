<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    protected function getAuthUser(Request $request): ?User
    {
        return $request->user();
    }

    // =========================
    // GET /api/agenda
    // =========================
    public function index(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        $filters = $request->validate([
            'room_id' => 'sometimes|integer|exists:rooms,id',
            'status' => 'sometimes|string|in:Menunggu,Disetujui,Ditolak',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'verification' => 'sometimes|boolean',
            'my_rooms' => 'sometimes|boolean',
        ]);

        $query = Event::notExpired()->with(['room', 'requester'])
            ->orderBy('date')
            ->orderBy('start_time');

        // Jika siswa, hanya tampilkan agenda yang sudah disetujui
        if ($authUser && $authUser->role === 'siswa') {
            $query->where('status', 'Disetujui');
        } elseif ($authUser && $authUser->role === 'pic') {
            $query->whereHas('room', fn ($q) => $q->where('pic_id', $authUser->id));
            if ($request->boolean('verification') || $request->input('status') === 'Menunggu') {
                $query->where('status', 'Menunggu');
            }
        } elseif ($authUser && $authUser->role === 'guru') {
            $query->where('requester_id', $authUser->id);
        }

        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $events = $query->paginate($filters['per_page'] ?? 100);

        return response()->json([
            'success' => true,
            'message' => 'Data agenda berhasil diambil',
            'data' => $events->items(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
            ],
        ]);
    }

    // =========================
    // GET /api/agenda/{id}
    // =========================
    public function show(Request $request, int $id)
    {
        $authUser = $this->getAuthUser($request);

        $event = Event::notExpired()->with(['room', 'requester', 'approver'])
            ->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan'
            ], 404);
        }

        // Siswa tidak boleh melihat agenda yang belum disetujui
        if ($authUser && $authUser->role === 'siswa' && $event->status !== 'Disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan atau belum disetujui.'
            ], 403);
        }

        if ($authUser->role === 'guru' && $event->requester_id !== $authUser->id) {
            return response()->json(['success' => false, 'message' => 'Agenda tidak ditemukan.'], 404);
        }

        if ($authUser->role === 'pic' && $event->room?->pic_id !== $authUser->id) {
            return response()->json(['success' => false, 'message' => 'Agenda tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail agenda berhasil diambil',
            'data' => $event
        ]);
    }

    // =========================
    // POST /api/agenda
    // =========================
    public function store(Request $request)
    {
        $authUser = $this->getAuthUser($request);

        // Siswa tidak boleh mengajukan agenda
        if ($authUser && $authUser->role === 'siswa') {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak memiliki izin untuk mengajukan agenda.'
            ], 403);
        }

        // PIC tidak boleh mengajukan agenda melalui mobile
        if ($authUser && $authUser->role === 'pic') {
            return response()->json([
                'success' => false,
                'message' => 'PIC Ruang tidak memiliki izin untuk mengajukan agenda.'
            ], 403);
        }

        if ($request->filled('sesi_mulai') && !$request->filled('jam_mulai')) {
            $request->merge(['jam_mulai' => $this->sessionTime((int) $request->sesi_mulai)]);
        }

        if ($request->filled('sesi_selesai') && !$request->filled('jam_selesai')) {
            $request->merge(['jam_selesai' => $this->sessionTime((int) $request->sesi_selesai + 1)]);
        }

        $request->validate([
            'judul' => 'required|string|max:255',
            'tanggal' => 'required|date|after:today',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'ruangan_id' => 'required|exists:rooms,id',
            'keterangan' => 'nullable|string',
        ], [
            'tanggal.after' => 'Peminjaman ruangan harus diajukan minimal H-1 (sehari sebelum tanggal agenda).',
            'judul.required' => 'Judul agenda wajib diisi.',
            'ruangan_id.required' => 'Ruangan wajib dipilih.',
        ]);

        // Cek rentang operasional sesi sekolah (06:00 - 18:00)
        if ($request->jam_mulai < '06:00' || $request->jam_mulai > '18:00') {
            return response()->json([
                'success' => false,
                'message' => 'Jam mulai harus antara 06:00 dan 18:00.'
            ], 422);
        }

        if ($request->jam_selesai < '06:00' || $request->jam_selesai > '18:00') {
            return response()->json([
                'success' => false,
                'message' => 'Jam selesai harus antara 06:00 dan 18:00.'
            ], 422);
        }

        if ($request->jam_selesai <= $request->jam_mulai) {
            return response()->json([
                'success' => false,
                'message' => 'Jam selesai harus lebih besar dari jam mulai.'
            ], 422);
        }

        // Cek ruangan aktif
        $room = Room::whereKey($request->ruangan_id)
            ->where('is_active', true)
            ->first();

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan yang dipilih tidak aktif.'
            ], 422);
        }

        // Cek bentrok jadwal
        $conflict = Event::query()
            ->where('room_id', $request->ruangan_id)
            ->whereDate('date', $request->tanggal)
            ->where('status', '!=', 'Ditolak')
            ->where('start_time', '<', $request->jam_selesai)
            ->where('end_time', '>', $request->jam_mulai)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan sudah terpakai pada waktu tersebut.'
            ], 422);
        }

        $requesterId = $authUser->id;

        // Simpan agenda
        $event = Event::create([
            'event_name' => $request->judul,
            'date' => $request->tanggal,
            'start_time' => $request->jam_mulai,
            'end_time' => $request->jam_selesai,
            'requester_id' => $requesterId,
            'room_id' => $request->ruangan_id,
            'status' => 'Menunggu',
            'approved_by' => null,
            'move_reason' => null,
            'description' => $request->keterangan,
        ]);

        $event->load(['room', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil diajukan dan menunggu persetujuan PIC.',
            'data' => $event
        ], 201);
    }

    // =========================
    // POST /api/agenda/{id}/approve
    // =========================
    public function approve(Request $request, int $id)
    {
        $authUser = $this->getAuthUser($request);

        if (!$authUser || $authUser->role !== 'pic') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya PIC Ruang yang berwenang menyetujui agenda peminjaman ruangan.'
            ], 403);
        }

        $event = Event::with('room')->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan.'
            ], 404);
        }

        if (!$event->room || $event->room->pic_id !== $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda ini berada di luar ruangan tanggung jawab Anda.'
            ], 403);
        }

        if ($event->status !== 'Menunggu') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya agenda berstatus Menunggu yang dapat disetujui.'
            ], 422);
        }

        $event->update([
            'status' => 'Disetujui',
            'approved_by' => $authUser->id,
        ]);

        $event->load(['room', 'requester', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil disetujui.',
            'data' => $event
        ]);
    }

    // =========================
    // POST /api/agenda/{id}/reject
    // =========================
    public function reject(Request $request, int $id)
    {
        $authUser = $this->getAuthUser($request);

        if (!$authUser || $authUser->role !== 'pic') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya PIC Ruang yang berwenang menolak agenda peminjaman ruangan.'
            ], 403);
        }

        $event = Event::with('room')->find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan.'
            ], 404);
        }

        if (!$event->room || $event->room->pic_id !== $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda ini berada di luar ruangan tanggung jawab Anda.'
            ], 403);
        }

        if ($event->status !== 'Menunggu') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya agenda berstatus Menunggu yang dapat ditolak.'
            ], 422);
        }

        $request->validate([
            'alasan' => 'required|string|max:500',
        ], [
            'alasan.required' => 'Alasan penolakan agenda wajib diisi.',
        ]);

        $event->update([
            'status' => 'Ditolak',
            'approved_by' => $authUser->id,
            'rejection_reason' => $request->alasan,
            'rejected_at' => now(),
        ]);

        $event->load(['room', 'requester', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil ditolak.',
            'data' => $event
        ]);
    }

    // =========================
    // PUT /api/agenda/{id}
    // =========================
    public function update(Request $request, int $id)
    {
        $authUser = $this->getAuthUser($request);

        if ($authUser->role !== 'guru') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya guru yang dapat mengubah agenda melalui API.'
            ], 403);
        }

        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan'
            ], 404);
        }

        // Validasi kepemilikan jika user adalah guru
        if ($event->requester_id !== $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk mengubah agenda milik pengguna lain.'
            ], 403);
        }

        if ($event->status !== 'Menunggu') {
            return response()->json(['success' => false, 'message' => 'Hanya agenda berstatus Menunggu yang dapat diubah.'], 422);
        }

        if ($request->filled('sesi_mulai') && !$request->filled('jam_mulai')) {
            $request->merge(['jam_mulai' => $this->sessionTime((int) $request->sesi_mulai)]);
        }

        if ($request->filled('sesi_selesai') && !$request->filled('jam_selesai')) {
            $request->merge(['jam_selesai' => $this->sessionTime((int) $request->sesi_selesai + 1)]);
        }

        $request->validate([
            'judul' => 'required|string|max:255',
            'tanggal' => 'required|date|after:today',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'ruangan_id' => 'required|exists:rooms,id',
            'keterangan' => 'nullable|string',
        ], [
            'tanggal.after' => 'Peminjaman ruangan harus diajukan minimal H-1 (sehari sebelum tanggal agenda).',
        ]);

        if ($request->jam_mulai < '06:00' || $request->jam_mulai > '18:00') {
            return response()->json([
                'success' => false,
                'message' => 'Jam mulai harus antara 06:00 dan 18:00.'
            ], 422);
        }

        if ($request->jam_selesai < '06:00' || $request->jam_selesai > '18:00') {
            return response()->json([
                'success' => false,
                'message' => 'Jam selesai harus antara 06:00 dan 18:00.'
            ], 422);
        }

        if ($request->jam_selesai <= $request->jam_mulai) {
            return response()->json([
                'success' => false,
                'message' => 'Jam selesai harus lebih besar dari jam mulai.'
            ], 422);
        }

        $room = Room::whereKey($request->ruangan_id)
            ->where('is_active', true)
            ->first();

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan yang dipilih tidak aktif.'
            ], 422);
        }

        $conflict = Event::query()
            ->where('room_id', $request->ruangan_id)
            ->whereDate('date', $request->tanggal)
            ->where('status', '!=', 'Ditolak')
            ->where('start_time', '<', $request->jam_selesai)
            ->where('end_time', '>', $request->jam_mulai)
            ->where('id', '!=', $event->id)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan sudah terpakai pada waktu tersebut.'
            ], 422);
        }

        $event->update([
            'event_name' => $request->judul,
            'date' => $request->tanggal,
            'start_time' => $request->jam_mulai,
            'end_time' => $request->jam_selesai,
            'room_id' => $request->ruangan_id,
            'description' => $request->keterangan,
        ]);

        $event->load(['room', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil diperbarui.',
            'data' => $event
        ]);
    }

    // =========================
    // PUT /api/agenda/{id}/pindah-ruangan
    // =========================
    public function moveRoom(Request $request, int $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan'
            ], 404);
        }

        if ($event->status !== 'Disetujui') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya agenda yang sudah disetujui yang dapat dipindahkan.'
            ], 422);
        }

        $request->validate([
            'ruangan_id' => 'required|exists:rooms,id',
            'alasan' => 'required|string|max:500',
        ]);

        $room = Room::whereKey($request->ruangan_id)
            ->where('is_active', true)
            ->first();

        if (!$room) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan tujuan tidak aktif.'
            ], 422);
        }

        $conflict = Event::query()
            ->where('room_id', $request->ruangan_id)
            ->whereDate('date', $event->date)
            ->where('status', '!=', 'Ditolak')
            ->where('start_time', '<', $event->end_time)
            ->where('end_time', '>', $event->start_time)
            ->where('id', '!=', $event->id)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan tujuan sudah terpakai pada waktu tersebut.'
            ], 422);
        }

        DB::transaction(function () use ($event, $request, $room): void {
            $event->update([
                'room_id' => $request->ruangan_id,
                'move_reason' => $request->alasan,
            ]);

            ScheduleNotification::create([
                'user_id' => $event->requester_id,
                'event_id' => $event->id,
                'title' => 'Ruangan agenda dipindahkan',
                'message' =>
                    'Agenda "' . $event->event_name .
                    '" dipindahkan ke ruangan "' . $room->room_name .
                    '". Alasan: ' . $request->alasan,
                'read_at' => null,
            ]);
        });

        $event->load(['room', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Ruangan agenda berhasil dipindahkan dan notifikasi telah dibuat.',
            'data' => $event
        ]);
    }

    // =========================
    // DELETE /api/agenda/{id}
    // =========================
    public function destroy(Request $request, int $id)
    {
        $authUser = $this->getAuthUser($request);

        $event = Event::find($id);

        if (!$event) {
            return response()->json([
                'success' => false,
                'message' => 'Agenda tidak ditemukan'
            ], 404);
        }

        // Jika guru, hanya boleh hapus agenda miliknya sendiri
        if ($event->requester_id !== $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak untuk menghapus agenda milik pengguna lain.'
            ], 403);
        }

        if ($event->status !== 'Menunggu') {
            return response()->json(['success' => false, 'message' => 'Hanya agenda berstatus Menunggu yang dapat dihapus.'], 422);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Agenda berhasil dihapus.'
        ]);
    }

    private function sessionTime(int $session): string
    {
        return Carbon::createFromTime(7, 30)
            ->addMinutes(($session - 1) * 45)
            ->format('H:i');
    }
}
