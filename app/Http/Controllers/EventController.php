<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Services\RoomMoveWhatsAppService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Support\TablePdf;

class EventController extends Controller
{
    /**
     * =========================================================
     * ADMIN - DAFTAR AGENDA
     * =========================================================
     */
    public function index(Request $request)
    {
        $filters = $this->resolveAgendaListFilters($request, 'agenda.index');
        if (isset($filters['error_response'])) {
            return $filters['error_response'];
        }

        $query = Event::notExpired()->with(['user', 'room', 'approver']);
        $this->applyAgendaListFilters($query, $filters);

        $rawAgenda = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $grouped = Event::groupEventsForDisplay($rawAgenda);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = $grouped->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $agenda = new LengthAwarePaginator($currentItems, $grouped->count(), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        $roomMoveWhatsapp = $this->roomMoveWhatsAppService()->resolveForView($request);

        return view('admin.event.index', compact('agenda', 'filters', 'roomMoveWhatsapp'));
    }

    /** Laporan agenda admin berdasarkan periode harian, mingguan, atau bulanan. */
    public function adminReport(Request $request)
    {
        $validated = $request->validate([
            'periode' => ['nullable', 'in:harian,mingguan,bulanan'],
            'tanggal' => ['nullable', 'date'],
        ]);

        $periode = $validated['periode'] ?? 'harian';
        $tanggal = Carbon::parse($validated['tanggal'] ?? today());
        $mulai = $tanggal->copy()->startOfDay();
        $selesai = $tanggal->copy()->endOfDay();

        if ($periode === 'mingguan') {
            $mulai = $tanggal->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
            $selesai = $tanggal->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();
        } elseif ($periode === 'bulanan') {
            $mulai = $tanggal->copy()->startOfMonth()->startOfDay();
            $selesai = $tanggal->copy()->endOfMonth()->endOfDay();
        }

        $rawAgenda = Event::with(['user', 'room', 'approver'])
            ->where(function ($query) use ($mulai, $selesai) {
                $query->whereDate('date', '<=', $selesai->toDateString())
                    ->whereDate(DB::raw('COALESCE(end_date, date)'), '>=', $mulai->toDateString());
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $agenda = Event::groupEventsForDisplay($rawAgenda);

        $jumlahStatus = [
            'Menunggu' => $agenda->where('status', 'Menunggu')->count(),
            'Disetujui' => $agenda->where('status', 'Disetujui')->count(),
            'Ditolak' => $agenda->where('status', 'Ditolak')->count(),
        ];

        if ($request->query('unduh') === 'pdf') {
            $rows = $agenda->values()->map(fn ($item, $index) => [
                $index + 1,
                $item->formatted_date_range,
                substr((string) $item->start_time, 0, 5) . ' - ' . substr((string) $item->end_time, 0, 5),
                $item->event_name,
                $item->room?->room_name ?? '-',
                $item->user?->name ?? '-',
                $item->status === 'Disetujui' ? $item->status_pelaksanaan : $item->status,
                $item->approver?->name ?? '-',
            ])->all();
            $filename = 'laporan-agenda-' . $periode . '-' . $tanggal->format('Y-m-d') . '.pdf';
            $pdf = TablePdf::make(
                'Laporan Agenda ' . ucfirst($periode),
                'Periode ' . $mulai->format('d-m-Y') . ' sampai ' . $selesai->format('d-m-Y'),
                ['Total: ' . $agenda->count(), 'Menunggu: ' . $jumlahStatus['Menunggu'], 'Disetujui: ' . $jumlahStatus['Disetujui'], 'Ditolak: ' . $jumlahStatus['Ditolak']],
                ['No', 'Tanggal', 'Waktu', 'Judul Agenda', 'Ruangan', 'Pengaju', 'Status', 'Disetujui'],
                $rows,
                [25, 55, 62, 150, 90, 95, 75, 120]
            );

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        return view('admin.event.report', compact(
            'agenda', 'periode', 'tanggal', 'mulai', 'selesai', 'jumlahStatus'
        ));
    }

    /**
     * =========================================================
     * FORM TAMBAH AGENDA
     * =========================================================
     */
    public function create()
    {
        $ruangan = Room::sortRooms(Room::all());

        if (Auth::user()->role === 'guru') {
            return view('teacher.event.create', compact('ruangan'));
        }

        return view('admin.event.create', compact('ruangan'));
    }

    /**
     * =========================================================
     * SIMPAN AGENDA BARU
     * =========================================================
     */
    public function store(Request $request)
    {
        $isAdmin = Auth::user()->role === 'admin';
        $dateRule = $isAdmin ? 'required|date|after_or_equal:today' : 'required|date|after:today';

        $roomInput = $request->input('ruangan_id');
        $roomIds = is_array($roomInput) ? array_values(array_filter($roomInput)) : ($roomInput ? [$roomInput] : []);

        $request->validate([
            'judul' => 'required|string|max:255',
            'tanggal' => $dateRule,
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'sesi_mulai' => 'required|integer|between:1,14',
            'sesi_selesai' => 'required|integer|between:1,14|gte:sesi_mulai',
            'ruangan_id' => 'required',
            'keterangan' => 'nullable|string',
        ], [
            'judul.required' => 'Judul agenda wajib diisi.',
            'tanggal.required' => 'Tanggal agenda wajib diisi.',
            'tanggal.date' => 'Tanggal agenda tidak valid.',
            'tanggal.after' => 'Peminjaman ruangan harus diajukan minimal H-1 (sehari sebelum tanggal agenda).',
            'tanggal.after_or_equal' => 'Tanggal agenda tidak boleh mendahului hari ini.',
            'tanggal_selesai.date' => 'Tanggal selesai agenda tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            'sesi_mulai.required' => 'Sesi mulai wajib dipilih.',
            'sesi_mulai.between' => 'Sesi mulai harus antara sesi 1 sampai sesi 14.',
            'sesi_selesai.required' => 'Sesi selesai wajib dipilih.',
            'sesi_selesai.between' => 'Sesi selesai harus antara sesi 1 sampai sesi 14.',
            'sesi_selesai.gte' => 'Sesi selesai harus sama dengan atau setelah sesi mulai.',
            'ruangan_id.required' => 'Ruangan wajib dipilih minimal 1.',
        ]);

        if (empty($roomIds)) {
            return back()
                ->withInput()
                ->withErrors([
                    'ruangan_id' => 'Ruangan wajib dipilih minimal 1.',
                ]);
        }

        $startTime = $this->sessionTime((int) $request->sesi_mulai);
        $endTime = $this->sessionTime((int) $request->sesi_selesai + 1);
        $endDate = $request->tanggal_selesai ?: $request->tanggal;

        /**
         * =====================================================
         * CEK RUANGAN AKTIF
         * =====================================================
         */
        $rooms = Room::whereIn('id', $roomIds)->get()->keyBy('id');

        foreach ($roomIds as $roomId) {
            $room = $rooms->get($roomId);
            if (!$room) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'ruangan_id' => 'Ruangan yang dipilih tidak ditemukan.',
                    ]);
            }

            if (!$room->is_active) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'ruangan_id' => 'Ruangan "' . ($room->room_name ?: $room->nama_ruangan) . '" sedang tidak aktif.',
                    ]);
            }
        }

        /**
         * =====================================================
         * CEK BENTROK JADWAL
         * =====================================================
         */
        foreach ($roomIds as $roomId) {
            if (
                $this->hasScheduleConflict(
                    (int) $roomId,
                    $request->tanggal,
                    $startTime,
                    $endTime,
                    null,
                    $endDate
                )
            ) {
                $room = $rooms->get($roomId);
                $roomName = $room ? ($room->room_name ?: $room->nama_ruangan) : "ID: $roomId";
                return back()
                    ->withInput()
                    ->withErrors([
                        'ruangan_id' => 'Ruangan "' . $roomName . '" sudah terpakai pada waktu tersebut.',
                    ]);
            }
        }

        /**
         * =====================================================
         * SIMPAN AGENDA
         * =====================================================
         */
        DB::transaction(function () use ($roomIds, $request, $startTime, $endTime, $endDate, $isAdmin) {
            $groupId = (string) Str::uuid();
            $hasGroupId = Schema::hasColumn('events', 'group_id');

            foreach ($roomIds as $roomId) {
                $payload = [
                    'event_name' => $request->judul,
                    'date' => $request->tanggal,
                    'end_date' => $endDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'requester_id' => Auth::id(),
                    'room_id' => $roomId,
                    'status' => $isAdmin ? 'Disetujui' : 'Menunggu',
                    'approved_by' => $isAdmin ? Auth::id() : null,
                    'move_reason' => null,
                    'description' => $request->keterangan,
                ];

                if ($hasGroupId && count($roomIds) > 1) {
                    $payload['group_id'] = $groupId;
                }

                Event::create($payload);
            }
        });

        /**
         * =====================================================
         * REDIRECT
         * =====================================================
         */
        $roomCount = count($roomIds);

        if (Auth::user()->role === 'guru') {
            $message = $roomCount > 1
                ? "Agenda berhasil diajukan untuk {$roomCount} ruangan."
                : 'Agenda berhasil diajukan.';

            return redirect()
                ->route('guru.agenda')
                ->with('success', $message);
        }

        $message = $roomCount > 1
            ? "Agenda berhasil ditambahkan untuk {$roomCount} ruangan."
            : 'Agenda berhasil ditambahkan.';

        return redirect()
            ->route('agenda.index')
            ->with('success', $message);
    }

    /**
     * =========================================================
     * DETAIL AGENDA
     * =========================================================
     */
    public function show(Event $event)
    {
        $event->load([
            'user',
            'room',
            'approver',
        ]);

        $roomMoveWhatsapp = $this->roomMoveWhatsAppService()->resolveForView(request(), $event);

        return view(
            'admin.event.show',
            compact('event', 'roomMoveWhatsapp')
        );
    }

    /**
     * =========================================================
     * FORM EDIT AGENDA
     * =========================================================
     */
    public function edit(Event $event)
    {
        if ($event->status === 'Ditolak') {
            return redirect()->route('agenda.index')->with('error', 'Agenda yang ditolak tidak dapat diedit.');
        }

        $agenda = $event;

        $ruangan = Room::sortRooms(Room::all());
        $roomMoveWhatsapp = $this->roomMoveWhatsAppService()->resolveForView(request(), $event);

        return view(
            'admin.event.edit',
            compact('agenda', 'ruangan', 'roomMoveWhatsapp')
        );
    }

    /**
     * =========================================================
     * UPDATE AGENDA
     * =========================================================
     */
    public function update(
        Request $request,
        Event $event
    ) {
        if ($event->status === 'Ditolak') {
            $this->roomMoveWhatsAppService()->clear($request, (int) $event->id);

            return redirect()->route('agenda.index')->with('error', 'Agenda yang ditolak tidak dapat diedit.');
        }

        $request->validate([
            'judul' => 'required|string|max:255',
            'tanggal' => 'required|date|after_or_equal:today',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'sesi_mulai' => 'required|integer|between:1,14',
            'sesi_selesai' => 'required|integer|between:1,14|gte:sesi_mulai',
            'ruangan_id' => 'required|exists:rooms,id',
            'current_room_id' => 'nullable|integer|exists:rooms,id',
            'move_reason' => 'nullable|string|max:1000',
            'keterangan' => 'nullable|string',
        ], [
            'judul.required' => 'Judul agenda wajib diisi.',
            'tanggal.required' => 'Tanggal agenda wajib diisi.',
            'tanggal.date' => 'Tanggal agenda tidak valid.',
            'tanggal.after_or_equal' => 'Tanggal agenda tidak boleh mendahului hari ini.',
            'tanggal_selesai.date' => 'Tanggal selesai agenda tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama dengan atau setelah tanggal mulai.',
            'sesi_mulai.required' => 'Sesi mulai wajib dipilih.',
            'sesi_mulai.between' => 'Sesi mulai harus antara sesi 1 sampai sesi 14.',
            'sesi_selesai.required' => 'Sesi selesai wajib dipilih.',
            'sesi_selesai.between' => 'Sesi selesai harus antara sesi 1 sampai sesi 14.',
            'sesi_selesai.gte' => 'Sesi selesai harus sama dengan atau setelah sesi mulai.',
            'ruangan_id.required' => 'Ruangan wajib dipilih.',
            'ruangan_id.exists' => 'Ruangan yang dipilih tidak ditemukan.',
            'current_room_id.exists' => 'Ruangan awal agenda tidak valid.',
        ]);

        $startTime = $this->sessionTime((int) $request->sesi_mulai);
        $endTime = $this->sessionTime((int) $request->sesi_selesai + 1);
        $endDate = $request->tanggal_selesai ?: $request->tanggal;
        $targetRoomId = (int) $request->ruangan_id;
        $submittedCurrentRoomId = $request->filled('current_room_id')
            ? (int) $request->input('current_room_id')
            : null;
        $requestedMoveReason = trim((string) $request->input('move_reason', ''));

        $outcome = [
            'updated' => false,
            'room_changed' => false,
            'event_id' => null,
            'old_room_id' => null,
            'old_room_name' => '',
            'new_room_id' => null,
            'error_field' => 'ruangan_id',
            'error_message' => null,
            'redirect_error' => null,
        ];

        DB::transaction(function () use (
            $event,
            $request,
            $endDate,
            $startTime,
            $endTime,
            $targetRoomId,
            $submittedCurrentRoomId,
            $requestedMoveReason,
            &$outcome
        ): void {
            /** @var Event|null $lockedEvent */
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if (!$lockedEvent) {
                $outcome['redirect_error'] = 'Agenda tidak ditemukan.';

                return;
            }

            if ($lockedEvent->status === 'Ditolak') {
                $outcome['redirect_error'] = 'Agenda yang ditolak tidak dapat diedit.';

                return;
            }

            $currentRoomId = (int) $lockedEvent->room_id;
            $roomChanged = $currentRoomId !== $targetRoomId;

            if ($submittedCurrentRoomId !== null && $submittedCurrentRoomId !== $currentRoomId) {
                $outcome['error_message'] = 'Kondisi ruangan agenda sudah berubah karena proses lain. Muat ulang halaman sebelum menyimpan perubahan.';

                return;
            }

            if ($roomChanged && $lockedEvent->status !== 'Disetujui') {
                $outcome['error_message'] = 'Hanya agenda yang sudah disetujui yang dapat dipindahkan ruangannya.';

                return;
            }

            $room = Room::query()->whereKey($targetRoomId)->lockForUpdate()->first();
            if (!$room || !$room->is_active) {
                $outcome['error_message'] = 'Ruangan yang dipilih tidak aktif.';

                return;
            }

            if (
                $this->hasScheduleConflict(
                    (int) $room->id,
                    $request->tanggal,
                    $startTime,
                    $endTime,
                    $lockedEvent->id,
                    $endDate
                )
            ) {
                $outcome['error_message'] = 'Ruangan sudah terpakai pada waktu tersebut.';

                return;
            }

            $updatePayload = [
                'event_name' => $request->judul,
                'date' => $request->tanggal,
                'end_date' => $endDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_id' => $room->id,
                'description' => $request->keterangan,
            ];

            if (!$roomChanged) {
                $lockedEvent->update($updatePayload);
                $outcome['updated'] = true;

                return;
            }

            $oldRoom = Room::find($currentRoomId);
            $oldRoomName = trim((string) ($oldRoom?->room_name ?? ''));
            $resolvedMoveReason = $requestedMoveReason !== ''
                ? $requestedMoveReason
                : 'Penyesuaian jadwal dan penggunaan ruangan oleh Admin.';

            $updatePayload['move_reason'] = $resolvedMoveReason;

            $lockedEvent->update($updatePayload);

            ScheduleNotification::create([
                'user_id' => $lockedEvent->requester_id,
                'event_id' => $lockedEvent->id,
                'title' => 'Ruangan agenda dipindahkan',
                'message' =>
                    'Agenda "' . $lockedEvent->event_name . '" tanggal ' .
                    $lockedEvent->formatted_date_range .
                    ' dipindahkan dari ' .
                    ($oldRoomName !== '' ? $oldRoomName : 'ruangan sebelumnya') .
                    ' ke ' .
                    $room->room_name .
                    '. Alasan: ' .
                    $resolvedMoveReason,
            ]);

            $outcome['updated'] = true;
            $outcome['room_changed'] = true;
            $outcome['event_id'] = (int) $lockedEvent->id;
            $outcome['old_room_id'] = $currentRoomId;
            $outcome['old_room_name'] = $oldRoomName;
            $outcome['new_room_id'] = (int) $room->id;
        });

        if ($outcome['redirect_error'] !== null) {
            $this->roomMoveWhatsAppService()->clear($request, (int) $event->id);

            return redirect()->route('agenda.index')->with('error', $outcome['redirect_error']);
        }

        if (!$outcome['updated']) {
            return back()
                ->withInput()
                ->withErrors([
                    $outcome['error_field'] => $outcome['error_message'] ?? 'Agenda gagal diperbarui.',
                ]);
        }

        if ($outcome['room_changed'] && $outcome['event_id'] !== null && $outcome['new_room_id'] !== null) {
            $this->roomMoveWhatsAppService()->rememberRoomMove(
                $request,
                $outcome['event_id'],
                $outcome['old_room_id'],
                $outcome['old_room_name'],
                $outcome['new_room_id']
            );
        } else {
            // Hanya bersihkan konteks agenda ini agar notifikasi agenda lain tidak terhapus.
            $this->roomMoveWhatsAppService()->clear($request, (int) $event->id);
        }

        $successMessage = $outcome['room_changed']
            ? 'Agenda berhasil diperbarui dan notifikasi internal telah dibuat.'
            : 'Agenda berhasil diperbarui.';

        return redirect()
            ->route('agenda.index')
            ->with('success', $successMessage);
    }

    /**
     * =========================================================
     * PIC - MENYETUJUI AGENDA
     * =========================================================
     */
    public function approve(Event $event)
    {
        $this->ensurePicOwnsRoom($event);

        return $this->approvePendingEvent($event, 'pic.events', (int) Auth::id());
    }

    /** Admin dapat menyetujui agenda dari halaman Kelola Agenda. */
    public function adminApprove(Event $event)
    {
        return $this->approvePendingEvent($event, 'agenda.index');
    }

    private function approvePendingEvent(Event $event, string $route, ?int $picUserId = null)
    {
        $event->loadMissing('room');

        if ($picUserId !== null) {
            if (
                !$event->room ||
                $event->room->pic_id === null ||
                (int) $event->room->pic_id !== $picUserId
            ) {
                abort(
                    403,
                    'Anda tidak memiliki hak untuk menangani agenda pada ruangan ini.'
                );
            }

            if ($event->status !== 'Menunggu') {
                return redirect()
                    ->route($route)
                    ->with('error', 'Hanya agenda berstatus Menunggu yang dapat disetujui.');
            }
        }

        $sisters = $event->getSisterEvents()->loadMissing('room');

        if ($picUserId !== null) {
            $eligibleEvents = $sisters
                ->filter(fn (Event $sister) => $sister->status === 'Menunggu'
                    && $sister->room
                    && $sister->room->pic_id !== null
                    && (int) $sister->room->pic_id === $picUserId)
                ->values();

            if ($event->status === 'Menunggu' && !$eligibleEvents->contains('id', $event->id)) {
                $eligibleEvents->push($event);
            }
        } else {
            $eligibleEvents = $sisters
                ->filter(fn (Event $sister) => $sister->status === 'Menunggu')
                ->values();

            if ($event->status === 'Menunggu' && !$eligibleEvents->contains('id', $event->id)) {
                $eligibleEvents->push($event);
            }

            if ($eligibleEvents->isEmpty()) {
                return redirect()
                    ->route($route)
                    ->with('error', 'Hanya agenda berstatus Menunggu yang dapat disetujui.');
            }
        }

        $approverId = Auth::id();

        DB::transaction(function () use ($eligibleEvents, $approverId) {
            foreach ($eligibleEvents as $sister) {
                $sister->update([
                    'status' => 'Disetujui',
                    'approved_by' => $approverId,
                ]);
            }
        });

        return redirect()
            ->route($route)
            ->with(
                'success',
                'Agenda berhasil disetujui.'
            );
    }

    /**
     * =========================================================
     * PIC - MENOLAK AGENDA
     * =========================================================
     */
    public function rejectForm(Event $event)
    {
        $this->ensurePicOwnsRoom($event);

        if ($event->status !== 'Menunggu') {
            $redirectRoute = request()->routeIs('agenda.tolak.form') ? 'pic.agenda' : 'pic.events';

            return redirect()
                ->route($redirectRoute)
                ->with('error', 'Hanya agenda berstatus Menunggu yang dapat ditolak.');
        }

        return view('pic.reject', compact('event'));
    }

    public function reject(Request $request, Event $event)
    {
        return $this->rejectPendingEventForPic($request, $event, 'pic.events');
    }

    private function rejectPendingEventForPic(Request $request, Event $event, string $route)
    {
        $this->ensurePicOwnsRoom($event);

        if ($event->status !== 'Menunggu') {
            return redirect()
                ->route($route)
                ->with('error', 'Hanya agenda berstatus Menunggu yang dapat ditolak.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ], [
            'rejection_reason.required' => 'Keterangan penolakan wajib diisi.',
            'rejection_reason.max' => 'Keterangan penolakan maksimal 2000 karakter.',
        ]);

        $picUserId = (int) Auth::id();

        $sisters = $event->getSisterEvents()
            ->loadMissing('room')
            ->filter(fn (Event $sister) => $sister->status === 'Menunggu'
                && $sister->room
                && $sister->room->pic_id !== null
                && (int) $sister->room->pic_id === $picUserId)
            ->values();

        if ($event->status === 'Menunggu' && !$sisters->contains('id', $event->id)) {
            $sisters->push($event);
        }

        $rejectedAt = now();

        DB::transaction(function () use ($sisters, $picUserId, $validated, $rejectedAt) {
            foreach ($sisters as $sister) {
                $sister->update([
                    'status' => 'Ditolak',
                    'approved_by' => $picUserId,
                    'rejection_reason' => $validated['rejection_reason'],
                    'rejected_at' => $rejectedAt,
                ]);
            }
        });

        return redirect()
            ->route($route)
            ->with(
                'success',
                'Agenda berhasil ditolak.'
            );
    }

    /**
     * =========================================================
     * HAPUS AGENDA
     * =========================================================
     */
    public function destroy(Event $event)
    {
        $sisters = $event->getSisterEvents();
        if ($sisters->isEmpty()) {
            $sisters = collect([$event]);
        }

        $deletedIds = $sisters->pluck('id')->push($event->id)->map(fn ($id) => (int) $id)->unique()->all();

        DB::transaction(function () use ($sisters) {
            foreach ($sisters as $sister) {
                $sister->delete();
            }
        });

        $whatsAppService = $this->roomMoveWhatsAppService();
        foreach ($deletedIds as $deletedId) {
            $whatsAppService->clear(request(), (int) $deletedId);
        }

        return redirect()
            ->route('agenda.index')
            ->with(
                'success',
                'Agenda berhasil dihapus.'
            );
    }

    /**
     * Admin menolak agenda dengan keterangan penolakan.
     */
    public function adminReject(Request $request, Event $event)
    {
        $sisters = $event->getSisterEvents();
        $eligibleEvents = $sisters
            ->filter(fn (Event $sister) => $sister->status === 'Menunggu')
            ->values();

        if ($event->status === 'Menunggu' && !$eligibleEvents->contains('id', $event->id)) {
            $eligibleEvents->push($event);
        }

        if ($eligibleEvents->isEmpty()) {
            return redirect()
                ->route('agenda.index')
                ->with('error', 'Hanya agenda berstatus Menunggu yang dapat ditolak.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ], [
            'rejection_reason.required' => 'Keterangan penolakan wajib diisi.',
            'rejection_reason.max' => 'Keterangan penolakan maksimal 2000 karakter.',
        ]);

        $approverId = Auth::id();
        $rejectedAt = now();

        DB::transaction(function () use ($eligibleEvents, $approverId, $validated, $rejectedAt) {
            foreach ($eligibleEvents as $sister) {
                $sister->update([
                    'status' => 'Ditolak',
                    'approved_by' => $approverId,
                    'rejection_reason' => $validated['rejection_reason'],
                    'rejected_at' => $rejectedAt,
                ]);
            }
        });

        return redirect()
            ->route('agenda.index')
            ->with('success', 'Agenda berhasil ditolak.');
    }

    /**
     * Batalkan/tolak agenda yang masih menunggu persetujuan.
     */
    public function cancel(Request $request, Event $event)
    {
        $sisters = $event->getSisterEvents();
        $eligibleEvents = $sisters
            ->filter(fn (Event $sister) => $sister->status === 'Menunggu')
            ->values();

        if ($event->status === 'Menunggu' && !$eligibleEvents->contains('id', $event->id)) {
            $eligibleEvents->push($event);
        }

        if ($eligibleEvents->isEmpty()) {
            return redirect()
                ->route('agenda.index')
                ->with('error', 'Hanya agenda berstatus Menunggu yang dapat dibatalkan/ditolak.');
        }

        $reason = $request->input('rejection_reason') ?: 'Ditolak oleh Administrator.';
        $approverId = Auth::id();
        $rejectedAt = now();

        DB::transaction(function () use ($eligibleEvents, $approverId, $reason, $rejectedAt) {
            foreach ($eligibleEvents as $sister) {
                $sister->update([
                    'status' => 'Ditolak',
                    'approved_by' => $approverId,
                    'rejection_reason' => $reason,
                    'rejected_at' => $rejectedAt,
                ]);
            }
        });

        return redirect()
            ->route('agenda.index')
            ->with('success', 'Agenda berhasil ditolak.');
    }

    /**
     * =========================================================
     * PIC - VERIFIKASI AGENDA
     * =========================================================
     */
    public function verification(Request $request)
    {
        $redirectRoute = $request->routeIs('pic.agenda') ? 'pic.agenda' : 'pic.events';
        $filters = $this->resolveAgendaListFilters($request, $redirectRoute);
        if (isset($filters['error_response'])) {
            return $filters['error_response'];
        }

        $query = Event::notExpired()->with([
                'user',
                'room',
                'approver',
            ])
            ->whereHas('room', function (Builder $query) {
                $query->where(
                    'pic_id',
                    Auth::id()
                );
            });

        $this->applyAgendaListFilters($query, $filters);

        $rawAgenda = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $grouped = Event::groupEventsForDisplay($rawAgenda);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = $grouped->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $agenda = new LengthAwarePaginator($currentItems, $grouped->count(), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        return view(
            'pic.index',
            compact('agenda', 'filters')
        );
    }

    /**
     * =========================================================
     * ROUTE LAMA - SETUJUI
     * =========================================================
     */
    public function setujui(Event $event)
    {
        $this->ensurePicOwnsRoom($event);

        return $this->approvePendingEvent($event, 'pic.agenda', (int) Auth::id());
    }

    /**
     * =========================================================
     * ROUTE LAMA - TOLAK
     * =========================================================
     */
    public function tolak(Request $request, Event $event)
    {
        return $this->rejectPendingEventForPic($request, $event, 'pic.agenda');
    }

    /**
     * =========================================================
     * AGENDA MILIK GURU
     * =========================================================
     */
    public function agendaGuru(Request $request)
    {
        $redirectRoute = $request->routeIs('guru.events') ? 'guru.events' : 'guru.agenda';
        $filters = $this->resolveAgendaListFilters($request, $redirectRoute);
        if (isset($filters['error_response'])) {
            return $filters['error_response'];
        }

        $query = Event::visibleInTeacherAgenda()
            ->with([
                'user',
                'room',
                'approver',
            ])
            ->where(
                'requester_id',
                Auth::id()
            );

        $this->applyAgendaListFilters($query, $filters);

        $rawAgenda = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $grouped = Event::groupEventsForDisplay($rawAgenda);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $currentItems = $grouped->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $agenda = new LengthAwarePaginator($currentItems, $grouped->count(), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        $notifications = ScheduleNotification::where(
                'user_id',
                Auth::id()
            )
            ->whereNull('read_at')
            ->latest()
            ->get();

        /**
         * Tandai notifikasi sebagai telah dibaca.
         */
        if ($notifications->isNotEmpty()) {
            ScheduleNotification::whereIn(
                'id',
                $notifications->pluck('id')
            )->update([
                'read_at' => now(),
            ]);
        }

        return view(
            'teacher.event.index',
            compact(
                'agenda',
                'notifications',
                'filters'
            )
        );
    }

    /**
     * =========================================================
     * ALIAS AGENDA GURU
     * =========================================================
     */
    public function myEvents(Request $request)
    {
        return $this->agendaGuru($request);
    }
    
    public function teacherCalendar()
{
    $rawEvents = Event::notExpired()->with(['room', 'requester', 'user'])
        ->where('status', 'Disetujui')
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    $events = Event::groupEventsForDisplay($rawEvents)
        ->map(function (Event $event) {

            return [
                'id' => $event->id,
                'title' => $event->event_name,

                'date' => $event->date
                    ? Carbon::parse($event->date)->format('Y-m-d')
                    : null,
                'end_date' => $event->end_date
                    ? Carbon::parse($event->end_date)->format('Y-m-d')
                    : ($event->date ? Carbon::parse($event->date)->format('Y-m-d') : null),
                'date_range' => $event->formatted_date_range,

                'time' => $event->start_time
                    ? substr($event->start_time, 0, 5)
                        . ' - ' .
                        substr($event->end_time, 0, 5)
                    : '-',

                'room' => $event->rooms_label ?: ($event->room?->room_name ?? 'Tanpa ruangan'),

                'description' => $event->description ?? '-',
                'requester_name' => $event->requester?->name ?? $event->user?->name ?? 'Guru / Organisasi',
                'creator_role' => $event->creator_role,
                'creator_label' => $event->creator_label,
                'status' => $event->status_pelaksanaan,
            ];
        })
        ->values();

    $nationalHolidays = \App\Services\NationalCalendarService::getCalendarData();

    return view(
        'teacher.calendar',
        compact('events', 'nationalHolidays')
    );
}

    /**
     * Kalender agenda yang telah disetujui untuk admin.
     */
    public function adminCalendar()
    {
        return $this->teacherCalendar();
    }

    /**
     * =========================================================
     * JADWAL UMUM
     * =========================================================
     */
    public function jadwal()
    {
        $today = Carbon::today()->toDateString();
        $rawAgenda = Event::notExpired()->with([
                'room',
                'user',
            ])
            ->where(
                'status',
                'Disetujui'
            )
            ->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $agenda = Event::groupEventsForDisplay($rawAgenda);

        return view(
            'jadwal.index',
            compact('agenda')
        );
    }

    /**
 * =========================================================
 * KALENDER PIC
 * =========================================================
 */
public function picCalendar()
{
    $rawEvents = Event::notExpired()->with(['room', 'requester', 'user'])
        ->where('status', 'Disetujui')
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    $events = Event::groupEventsForDisplay($rawEvents)
        ->map(function (Event $event) {
            $rawDate = $event->getRawOriginal('date') ?? $event->date;
            $dateStr = $rawDate
                ? Carbon::parse($rawDate)->format('Y-m-d')
                : null;
            $endDateStr = $event->end_date
                ? Carbon::parse($event->end_date)->format('Y-m-d')
                : ($rawDate ? Carbon::parse($rawDate)->format('Y-m-d') : null);

            return [
                'id' => $event->id,
                'title' => $event->event_name,
                'date' => $dateStr,
                'end_date' => $endDateStr,
                'date_range' => $event->formatted_date_range,
                'time' => $event->start_time
                    ? substr($event->start_time, 0, 5)
                        . ' - ' .
                        substr($event->end_time, 0, 5)
                    : '-',
                'room' => $event->rooms_label ?: ($event->room?->room_name ?? 'Tanpa ruangan'),
                'description' => $event->description
                    ?? '-',
                'requester_name' => $event->requester?->name ?? $event->user?->name ?? 'Guru / Organisasi',
                'creator_role' => $event->creator_role,
                'creator_label' => $event->creator_label,
                'status' => $event->status_pelaksanaan,
            ];
        })
        ->values();

    $nationalHolidays = \App\Services\NationalCalendarService::getCalendarData();

    return view(
        'pic.calendar',
        compact('events', 'nationalHolidays')
    );
}

    /**
     * =========================================================
     * SCHEDULE
     * =========================================================
     */
    public function schedule()
    {
        $query = Event::notExpired()->with([
                'room',
                'user',
            ])
            ->where(
                'status',
                'Disetujui'
            );

        if (Auth::user()?->role === 'pic') {
            $query->whereHas('room', function (Builder $builder): void {
                $builder->where('pic_id', Auth::id());
            });
        }

        $today = Carbon::today()->toDateString();
        $rawAgenda = $query->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        $agenda = Event::groupEventsForDisplay($rawAgenda);

        return view(
            'schedule.index',
            compact('agenda')
        );
    }

    /**
     * =========================================================
     * FORM PEMINDAHAN RUANGAN
     * =========================================================
     */
    public function moveForm(Event $event)
    {
        if ($event->status !== 'Disetujui') {
            return redirect()->route('agenda.index')->with('error', 'Hanya agenda yang sudah disetujui yang dapat dipindahkan.');
        }

        $rooms = Room::sortRooms(
            Room::where('is_active', true)
                ->whereKeyNot($event->room_id)
                ->get()
        );

        $roomMoveWhatsapp = $this->roomMoveWhatsAppService()->resolveForView(request(), $event);

        return view(
            'admin.event.move',
            compact(
                'event',
                'rooms',
                'roomMoveWhatsapp'
            )
        );
    }

    /**
     * =========================================================
     * PINDAH RUANGAN + NOTIFIKASI
     * =========================================================
     */
    public function move(
        Request $request,
        Event $event
    ) {
        if ($event->status !== 'Disetujui') {
            $this->roomMoveWhatsAppService()->clear($request, (int) $event->id);

            return redirect()->route('agenda.index')->with('error', 'Hanya agenda yang sudah disetujui yang dapat dipindahkan.');
        }

        $request->validate([
            'room_id' => [
                'required',
                'exists:rooms,id',
            ],
            'current_room_id' => [
                'nullable',
                'integer',
                'exists:rooms,id',
            ],
            'move_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ], [
            'room_id.required' =>
                'Ruangan tujuan wajib dipilih.',
            'room_id.exists' =>
                'Ruangan tujuan tidak ditemukan.',
            'current_room_id.exists' =>
                'Ruangan awal agenda tidak valid.',
            'move_reason.required' =>
                'Alasan pemindahan wajib diisi.',
        ]);

        $targetRoomId = (int) $request->room_id;
        $submittedCurrentRoomId = $request->filled('current_room_id')
            ? (int) $request->input('current_room_id')
            : null;
        $moveReason = (string) $request->move_reason;

        $outcome = [
            'moved' => false,
            'event_id' => null,
            'old_room_id' => null,
            'old_room_name' => '',
            'new_room_id' => null,
            'error_field' => 'room_id',
            'error_message' => null,
            'redirect_error' => null,
        ];

        /**
         * =====================================================
         * KUNCI BARIS AGENDA & PINDAHKAN RUANGAN SECARA ATOMIK
         * =====================================================
         */
        DB::transaction(function () use (
            $event,
            $targetRoomId,
            $submittedCurrentRoomId,
            $moveReason,
            &$outcome
        ): void {
            /** @var Event|null $lockedEvent */
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->first();

            if (!$lockedEvent) {
                $outcome['redirect_error'] = 'Agenda tidak ditemukan.';

                return;
            }

            if ($lockedEvent->status !== 'Disetujui') {
                $outcome['redirect_error'] = 'Hanya agenda yang sudah disetujui yang dapat dipindahkan.';

                return;
            }

            $currentRoomId = (int) $lockedEvent->room_id;

            // Validasi current_room_id jika dikirim form sebagai pemeriksaan stale state.
            if ($submittedCurrentRoomId !== null && $submittedCurrentRoomId !== $currentRoomId) {
                $outcome['error_message'] = 'Kondisi ruangan agenda telah berubah karena permintaan lain. Muat ulang halaman sebelum memindahkan ruangan.';

                return;
            }

            if ($targetRoomId === $currentRoomId) {
                $outcome['error_message'] = 'Ruangan tujuan harus berbeda dari ruangan saat ini.';

                return;
            }

            $room = Room::query()->whereKey($targetRoomId)->lockForUpdate()->first();
            if (!$room || !$room->is_active) {
                $outcome['error_message'] = 'Ruangan tujuan tidak tersedia.';

                return;
            }

            if (
                $this->hasScheduleConflict(
                    (int) $room->id,
                    $lockedEvent->date,
                    $lockedEvent->start_time,
                    $lockedEvent->end_time,
                    $lockedEvent->id,
                    $lockedEvent->end_date ?? $lockedEvent->date
                )
            ) {
                $outcome['error_message'] = 'Ruangan tujuan sudah terpakai pada waktu agenda ini.';

                return;
            }

            $oldRoom = Room::find($currentRoomId);
            $rawOldRoomName = trim((string) ($oldRoom?->room_name ?? ''));
            $oldRoomName = $rawOldRoomName !== '' ? $rawOldRoomName : 'ruangan sebelumnya';

            $lockedEvent->update([
                'room_id' => $room->id,
                'move_reason' => $moveReason,
            ]);

            ScheduleNotification::create([
                'user_id' => $lockedEvent->requester_id,
                'event_id' => $lockedEvent->id,
                'title' => 'Ruangan agenda dipindahkan',
                'message' =>
                    'Agenda "' . $lockedEvent->event_name . '" tanggal ' .
                    $lockedEvent->formatted_date_range .
                    ' dipindahkan dari ' .
                    $oldRoomName .
                    ' ke ' .
                    $room->room_name .
                    '. Alasan: ' .
                    $moveReason,
            ]);

            $outcome['moved'] = true;
            $outcome['event_id'] = (int) $lockedEvent->id;
            $outcome['old_room_id'] = $currentRoomId;
            $outcome['old_room_name'] = $rawOldRoomName;
            $outcome['new_room_id'] = (int) $room->id;
        });

        if ($outcome['redirect_error'] !== null) {
            $this->roomMoveWhatsAppService()->clear($request, (int) $event->id);

            return redirect()
                ->route('agenda.index')
                ->with('error', $outcome['redirect_error']);
        }

        if (!$outcome['moved']) {
            return back()
                ->withInput()
                ->withErrors([
                    $outcome['error_field'] => $outcome['error_message'] ?? 'Pemindahan ruangan gagal diproses.',
                ]);
        }

        if ($outcome['event_id'] !== null && $outcome['new_room_id'] !== null) {
            $this->roomMoveWhatsAppService()->rememberRoomMove(
                $request,
                $outcome['event_id'],
                $outcome['old_room_id'],
                $outcome['old_room_name'],
                $outcome['new_room_id']
            );
        }

        return redirect()
            ->route('agenda.index')
            ->with('success', 'Ruangan agenda berhasil dipindahkan.');
    }

    /**
     * Membuka tautan WhatsApp (wa.me) untuk pemberitahuan pemindahan ruangan kepada Guru pemilik agenda.
     */
    public function sendRoomMoveWhatsApp(Request $request, Event $event)
    {
        $result = $this->roomMoveWhatsAppService()->resolveRedirectUrl($request, $event);

        if (!$result['ok'] || empty($result['url'])) {
            return redirect()
                ->route('agenda.index')
                ->with('error', $result['error'] ?? 'Tautan WhatsApp belum tersedia.');
        }

        return redirect()->away($result['url']);
    }

    /**
     * Menutup panel pemberitahuan WhatsApp pemindahan ruangan di sesi Admin saat ini.
     */
    public function dismissRoomMoveWhatsApp(Request $request)
    {
        $rawEventId = $request->input('event_id');
        $eventId = is_numeric($rawEventId) && (int) $rawEventId > 0
            ? (int) $rawEventId
            : null;

        $this->roomMoveWhatsAppService()->clear($request, $eventId);

        return redirect()->route('agenda.index');
    }

    private function roomMoveWhatsAppService(): RoomMoveWhatsAppService
    {
        return new RoomMoveWhatsAppService();
    }

    /**
 * =========================================================
 * KALENDER GURU
 * =========================================================
 */



    /**
     * =========================================================
     * KALENDER PUBLIK
     * =========================================================
     */
 /**
 * Kalender publik read-only.
 * Bisa diakses tanpa login oleh semua pengguna,
 * termasuk siswa/tamu.
 */
public function publicCalendar()
{
    $rawEvents = Event::notExpired()->with(['room', 'requester', 'user'])
        ->where('status', 'Disetujui')
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    $events = Event::groupEventsForDisplay($rawEvents)
        ->map(function (Event $event) {
            $rawDate = $event->getRawOriginal('date') ?? $event->date;
            $dateStr = $rawDate
                ? Carbon::parse($rawDate)->format('Y-m-d')
                : null;
            $endDateStr = $event->end_date
                ? Carbon::parse($event->end_date)->format('Y-m-d')
                : ($rawDate ? Carbon::parse($rawDate)->format('Y-m-d') : null);

            return [
                'id' => $event->id,

                'title' => $event->event_name,

                'date' => $dateStr,
                'end_date' => $endDateStr,
                'date_range' => $event->formatted_date_range,

                'time' => $event->start_time
                    ? substr($event->start_time, 0, 5)
                        . ' - ' .
                        substr($event->end_time, 0, 5)
                    : '-',

                'start_time' => $event->start_time
                    ? substr($event->start_time, 0, 5)
                    : '-',

                'end_time' => $event->end_time
                    ? substr($event->end_time, 0, 5)
                    : '-',

                'room' => $event->rooms_label ?: ($event->room?->room_name ?? 'Tanpa ruangan'),

                'description' => $event->description
                    ?? '-',

                'requester_name' => $event->requester?->name ?? $event->user?->name ?? 'Guru / Organisasi',
                'creator_role' => $event->creator_role,
                'creator_label' => $event->creator_label,
                'status' => $event->status_pelaksanaan,
            ];
        })
        ->values();

    $nationalHolidays = \App\Services\NationalCalendarService::getCalendarData();

    return view(
        'calendar.public',
        compact('events', 'nationalHolidays')
    );
}
    /**
     * =========================================================
     * KEAMANAN PIC
     * =========================================================
     */
    private function ensurePicOwnsRoom(
        Event $event
    ): void {
        $event->loadMissing('room');
        $authId = Auth::id();

        if (
            $authId === null ||
            !$event->room ||
            $event->room->pic_id === null ||
            (int) $event->room->pic_id !== (int) $authId
        ) {
            abort(
                403,
                'Anda tidak memiliki hak untuk menangani agenda pada ruangan ini.'
            );
        }
    }

    /**
     * =========================================================
     * CEK BENTROK JADWAL
     * =========================================================
     */
    private function hasScheduleConflict(
        int $roomId,
        mixed $date,
        string $startTime,
        string $endTime,
        ?int $ignoreEventId = null,
        mixed $endDate = null
    ): bool {
        $startDate = Carbon::parse($date)->toDateString();
        $finalEndDate = $endDate ? Carbon::parse($endDate)->toDateString() : $startDate;

        $query = Event::query()
            ->where(
                'room_id',
                $roomId
            )
            ->where(function (Builder $query) use ($startDate, $finalEndDate) {
                $query->whereDate('date', '<=', $finalEndDate)
                    ->whereDate(DB::raw('COALESCE(end_date, date)'), '>=', $startDate);
            })
            ->where(
                'status',
                '!=',
                'Ditolak'
            )
            ->when(
                $ignoreEventId !== null,
                function (Builder $query) use (
                    $ignoreEventId
                ) {
                    $query->whereKeyNot(
                        $ignoreEventId
                    );
                }
            )
            ->where(
                'start_time',
                '<',
                $endTime
            )
            ->where(
                'end_time',
                '>',
                $startTime
            );

        if (DB::transactionLevel() > 0 && DB::getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    /**
     * Validasi dan ambil parameter filter tanggal & pencarian daftar agenda.
     *
     * @return array{tanggal_mulai: ?string, tanggal_selesai: ?string, search: string, error_response?: \Illuminate\Http\RedirectResponse}
     */
    private function resolveAgendaListFilters(Request $request, string $redirectRoute): array
    {
        $validator = Validator::make($request->query(), [
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => array_values(array_filter([
                'nullable',
                'date',
                $request->filled('tanggal_mulai') ? 'after_or_equal:tanggal_mulai' : null,
            ])),
            'search' => ['nullable', 'string', 'max:255'],
        ], [
            'tanggal_mulai.date' => 'Format tanggal mulai tidak valid.',
            'tanggal_selesai.date' => 'Format tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal mulai tidak boleh lebih besar daripada tanggal selesai.',
        ]);

        if ($validator->fails()) {
            return [
                'tanggal_mulai' => null,
                'tanggal_selesai' => null,
                'search' => '',
                'error_response' => redirect()
                    ->route($redirectRoute)
                    ->withErrors($validator)
                    ->withInput(),
            ];
        }

        $validated = $validator->validated();

        return [
            'tanggal_mulai' => !empty($validated['tanggal_mulai']) ? (string) $validated['tanggal_mulai'] : null,
            'tanggal_selesai' => !empty($validated['tanggal_selesai']) ? (string) $validated['tanggal_selesai'] : null,
            'search' => trim((string) ($validated['search'] ?? '')),
        ];
    }

    /**
     * Terapkan filter rentang tanggal & pencarian ke query agenda.
     *
     * @param array{tanggal_mulai: ?string, tanggal_selesai: ?string, search: string} $filters
     */
    private function applyAgendaListFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['tanggal_mulai'])) {
            $query->whereDate(DB::raw('COALESCE(end_date, date)'), '>=', $filters['tanggal_mulai']);
        }

        if (!empty($filters['tanggal_selesai'])) {
            $query->whereDate('date', '<=', $filters['tanggal_selesai']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function (Builder $sub) use ($search): void {
                $sub->where('event_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('room', fn (Builder $roomQ) => $roomQ->where('room_name', 'like', "%{$search}%"))
                    ->orWhereHas('user', fn (Builder $userQ) => $userQ->where('name', 'like', "%{$search}%"));
            });
        }
    }

    /**
     * Konversi nomor sesi menjadi jam. Sesi 1 dimulai pukul 07.30,
     * dan setiap sesi berlangsung selama 45 menit.
     */
    private function sessionTime(int $session): string
    {
        return Carbon::createFromTime(7, 30)
            ->addMinutes(($session - 1) * 45)
            ->format('H:i:s');
    }
}
