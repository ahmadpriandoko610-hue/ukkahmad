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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

        $query = Event::notExpired()->with(['room', 'requester', 'user'])
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
            // Gunakan scope visibleInTeacherAgenda untuk mengikuti aturan masa tampil website Laravel
            $query = Event::visibleInTeacherAgenda()->with(['room', 'requester', 'user'])
                ->orderBy('date')
                ->orderBy('start_time')
                ->where('requester_id', $authUser->id);
        }

        if (isset($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $rawEvents = $query->get();
        $groupedEvents = Event::groupEventsForDisplay($rawEvents);

        return response()->json([
            'success' => true,
            'message' => 'Data agenda berhasil diambil',
            'data' => $groupedEvents->values(),
            'meta' => [
                'current_page' => 1,
                'per_page' => $groupedEvents->count(),
                'total' => $groupedEvents->count(),
                'last_page' => 1,
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

        $rawRoomIds = $request->input('ruangan_id') ?? $request->input('ruangan_ids');
        $roomIds = is_array($rawRoomIds) ? array_filter($rawRoomIds) : (empty($rawRoomIds) ? [] : [$rawRoomIds]);

        $request->validate([
            'judul' => 'required|string|max:255',
            'tanggal' => 'required|date|after:today',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'keterangan' => 'nullable|string',
        ], [
            'tanggal.after' => 'Peminjaman ruangan harus diajukan minimal H-1 (sehari sebelum tanggal agenda).',
            'judul.required' => 'Judul agenda wajib diisi.',
        ]);

        if (empty($roomIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Ruangan wajib dipilih minimal 1.'
            ], 422);
        }

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
        $rooms = Room::whereIn('id', $roomIds)->get()->keyBy('id');
        foreach ($roomIds as $roomId) {
            $room = $rooms->get($roomId);
            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan yang dipilih tidak ditemukan.'
                ], 422);
            }
            if (!$room->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan "' . ($room->room_name ?: $room->nama_ruangan) . '" sedang tidak aktif.'
                ], 422);
            }
        }

        // Cek bentrok jadwal
        $endDate = $request->input('tanggal_selesai') ?: $request->input('tanggal');
        foreach ($roomIds as $roomId) {
            $conflict = Event::query()
                ->where('room_id', $roomId)
                ->where(function ($q) use ($request, $endDate) {
                    $q->whereDate('date', '<=', $endDate)
                        ->whereDate(DB::raw('COALESCE(end_date, date)'), '>=', $request->tanggal);
                })
                ->where('status', '!=', 'Ditolak')
                ->where('start_time', '<', $request->jam_selesai)
                ->where('end_time', '>', $request->jam_mulai)
                ->exists();

            if ($conflict) {
                $room = $rooms->get($roomId);
                $roomName = $room ? ($room->room_name ?: $room->nama_ruangan) : "ID: $roomId";
                return response()->json([
                    'success' => false,
                    'message' => 'Ruangan "' . $roomName . '" sudah terpakai pada waktu tersebut.'
                ], 422);
            }
        }

        $requesterId = $authUser->id;

        // Simpan agenda (mendukung multi-ruangan dengan group_id)
        $createdEvents = DB::transaction(function () use ($roomIds, $request, $requesterId, $endDate) {
            $groupId = (string) Str::uuid();
            $hasGroupId = Schema::hasColumn('events', 'group_id');
            $events = [];

            foreach ($roomIds as $roomId) {
                $payload = [
                    'event_name' => $request->judul,
                    'date' => $request->tanggal,
                    'end_date' => $request->filled('tanggal_selesai') ? $request->tanggal_selesai : null,
                    'start_time' => $request->jam_mulai,
                    'end_time' => $request->jam_selesai,
                    'requester_id' => $requesterId,
                    'room_id' => $roomId,
                    'status' => 'Menunggu',
                    'approved_by' => null,
                    'move_reason' => null,
                    'description' => $request->keterangan,
                ];

                if ($hasGroupId) {
                    $payload['group_id'] = $groupId;
                }

                $events[] = Event::create($payload);
            }

            return $events;
        });

        $primary = $createdEvents[0];
        $primary->load(['room', 'requester']);

        $roomCount = count($roomIds);
        $message = $roomCount > 1
            ? "Agenda berhasil diajukan untuk {$roomCount} ruangan dan menunggu persetujuan PIC."
            : 'Agenda berhasil diajukan dan menunggu persetujuan PIC.';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $primary
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

        $picId = (int) $authUser->id;

        if (!$event->room || $event->room->pic_id === null || (int) $event->room->pic_id !== $picId) {
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

        $sisters = $event->getSisterEvents()
            ->loadMissing('room')
            ->filter(fn (Event $sister) => $sister->status === 'Menunggu'
                && $sister->room
                && $sister->room->pic_id !== null
                && (int) $sister->room->pic_id === $picId)
            ->values();

        if (!$sisters->contains('id', $event->id)) {
            $sisters->push($event);
        }

        DB::transaction(function () use ($sisters, $picId) {
            foreach ($sisters as $sister) {
                $sister->update([
                    'status' => 'Disetujui',
                    'approved_by' => $picId,
                ]);
            }
        });

        $event->refresh()->load(['room', 'requester', 'approver']);

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

        $picId = (int) $authUser->id;

        if (!$event->room || $event->room->pic_id === null || (int) $event->room->pic_id !== $picId) {
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

        $validated = $request->validate([
            'alasan' => 'required|string|max:500',
        ], [
            'alasan.required' => 'Alasan penolakan agenda wajib diisi.',
        ]);

        $sisters = $event->getSisterEvents()
            ->loadMissing('room')
            ->filter(fn (Event $sister) => $sister->status === 'Menunggu'
                && $sister->room
                && $sister->room->pic_id !== null
                && (int) $sister->room->pic_id === $picId)
            ->values();

        if (!$sisters->contains('id', $event->id)) {
            $sisters->push($event);
        }

        $rejectedAt = now();

        DB::transaction(function () use ($sisters, $picId, $validated, $rejectedAt) {
            foreach ($sisters as $sister) {
                $sister->update([
                    'status' => 'Ditolak',
                    'approved_by' => $picId,
                    'rejection_reason' => $validated['alasan'],
                    'rejected_at' => $rejectedAt,
                ]);
            }
        });

        $event->refresh()->load(['room', 'requester', 'approver']);

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
            'data' => $event,
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
            'current_room_id' => 'nullable|integer|exists:rooms,id',
            'alasan' => 'required|string|max:500',
        ]);

        $targetRoomId = (int) $request->ruangan_id;
        $submittedCurrentRoomId = $request->filled('current_room_id')
            ? (int) $request->input('current_room_id')
            : null;
        $reason = (string) $request->alasan;

        $errorResponse = null;
        $movedEvent = null;

        DB::transaction(function () use (
            $id,
            $targetRoomId,
            $submittedCurrentRoomId,
            $reason,
            &$errorResponse,
            &$movedEvent
        ): void {
            /** @var Event|null $lockedEvent */
            $lockedEvent = Event::query()->whereKey($id)->lockForUpdate()->first();

            if (!$lockedEvent) {
                $errorResponse = response()->json([
                    'success' => false,
                    'message' => 'Agenda tidak ditemukan'
                ], 404);

                return;
            }

            if ($lockedEvent->status !== 'Disetujui') {
                $errorResponse = response()->json([
                    'success' => false,
                    'message' => 'Hanya agenda yang sudah disetujui yang dapat dipindahkan.'
                ], 422);

                return;
            }

            $oldRoomId = (int) $lockedEvent->room_id;

            if ($submittedCurrentRoomId !== null && $submittedCurrentRoomId !== $oldRoomId) {
                $errorResponse = response()->json([
                    'success' => false,
                    'message' => 'Kondisi ruangan agenda telah berubah karena permintaan lain. Muat ulang data sebelum memindahkan ruangan.'
                ], 409);

                return;
            }

            if ($targetRoomId === $oldRoomId) {
                $errorResponse = response()->json([
                    'success' => false,
                    'message' => 'Ruangan tujuan harus berbeda dari ruangan saat ini.'
                ], 422);

                return;
            }

            $room = Room::query()->whereKey($targetRoomId)->lockForUpdate()->first();
            if (!$room || !$room->is_active) {
                $errorResponse = response()->json([
                    'success' => false,
                    'message' => 'Ruangan tujuan tidak aktif.'
                ], 422);

                return;
            }

            $eventStartDate = $lockedEvent->getRawOriginal('date')
                ?: Carbon::parse($lockedEvent->date)->toDateString();
            $eventEndDate = $lockedEvent->getRawOriginal('end_date')
                ?: ($lockedEvent->end_date ? Carbon::parse($lockedEvent->end_date)->toDateString() : $eventStartDate);

            $conflictQuery = Event::query()
                ->where('room_id', $room->id)
                ->where(function ($query) use ($eventStartDate, $eventEndDate): void {
                    $query->whereDate('date', '<=', $eventEndDate)
                        ->whereDate(DB::raw('COALESCE(end_date, date)'), '>=', $eventStartDate);
                })
                ->where('status', '!=', 'Ditolak')
                ->where('start_time', '<', $lockedEvent->end_time)
                ->where('end_time', '>', $lockedEvent->start_time)
                ->where('id', '!=', $lockedEvent->id);

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $conflictQuery->lockForUpdate();
            }

            if ($conflictQuery->exists()) {
                $errorResponse = response()->json([
                    'success' => false,
                    'message' => 'Ruangan tujuan sudah terpakai pada waktu tersebut.'
                ], 422);

                return;
            }

            $oldRoom = Room::find($oldRoomId);
            $oldRoomName = $oldRoom?->room_name ?? 'ruangan sebelumnya';

            $lockedEvent->update([
                'room_id' => $room->id,
                'move_reason' => $reason,
            ]);

            ScheduleNotification::create([
                'user_id' => $lockedEvent->requester_id,
                'event_id' => $lockedEvent->id,
                'title' => 'Ruangan agenda dipindahkan',
                'message' =>
                    'Agenda "' . $lockedEvent->event_name .
                    '" dipindahkan ke ruangan "' . $room->room_name .
                    '" (sebelumnya ' . $oldRoomName . '). Alasan: ' . $reason,
                'read_at' => null,
            ]);

            $movedEvent = $lockedEvent;
        });

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $event = ($movedEvent ?? Event::find($id))?->load(['room', 'requester']);

        return response()->json([
            'success' => true,
            'message' => 'Ruangan agenda berhasil dipindahkan.',
            'data' => $event,
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

        $sisters = $event->getSisterEvents();
        if ($sisters->isEmpty()) {
            $sisters = collect([$event]);
        }

        foreach ($sisters as $sister) {
            $sister->delete();
        }

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
