<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\TablePdf;

class EventController extends Controller
{
    /**
     * =========================================================
     * ADMIN - DAFTAR AGENDA
     * =========================================================
     */
    public function index()
    {
        $today = Carbon::today()->toDateString();
        $agenda = Event::notExpired()->with(['user', 'room', 'approver'])
            ->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(10);

        return view('admin.event.index', compact('agenda'));
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

        $agenda = Event::with(['user', 'room', 'approver'])
            ->where(function ($query) use ($mulai, $selesai) {
                $query->whereDate('date', '<=', $selesai->toDateString())
                    ->whereDate(DB::raw('COALESCE(end_date, date)'), '>=', $mulai->toDateString());
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

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
        $ruangan = Room::orderBy('room_name')->get();

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
            foreach ($roomIds as $roomId) {
                Event::create([
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
                ]);
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

        return view(
            'admin.event.show',
            compact('event')
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

        $ruangan = Room::orderBy('room_name')->get();

        return view(
            'admin.event.edit',
            compact('agenda', 'ruangan')
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
            return redirect()->route('agenda.index')->with('error', 'Agenda yang ditolak tidak dapat diedit.');
        }

        $request->validate([
            'judul' => 'required|string|max:255',
            'tanggal' => 'required|date|after_or_equal:today',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal',
            'sesi_mulai' => 'required|integer|between:1,14',
            'sesi_selesai' => 'required|integer|between:1,14|gte:sesi_mulai',
            'ruangan_id' => 'required|exists:rooms,id',
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
        ]);

        $startTime = $this->sessionTime((int) $request->sesi_mulai);
        $endTime = $this->sessionTime((int) $request->sesi_selesai + 1);
        $endDate = $request->tanggal_selesai ?: $request->tanggal;

        /**
         * =====================================================
         * CEK RUANGAN AKTIF
         * =====================================================
         */
        $room = Room::whereKey($request->ruangan_id)
            ->where('is_active', true)
            ->first();

        if (!$room) {
            return back()
                ->withInput()
                ->withErrors([
                    'ruangan_id' =>
                        'Ruangan yang dipilih tidak aktif.',
                ]);
        }

        /**
         * =====================================================
         * CEK BENTROK
         * Event yang sedang diedit dikecualikan.
         * =====================================================
         */
        if (
            $this->hasScheduleConflict(
                (int) $request->ruangan_id,
                $request->tanggal,
                $startTime,
                $endTime,
                $event->id,
                $endDate
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'ruangan_id' =>
                        'Ruangan sudah terpakai pada waktu tersebut.',
                ]);
        }

        /**
         * =====================================================
         * UPDATE
         * =====================================================
         */
        $event->update([
            'event_name' => $request->judul,
            'date' => $request->tanggal,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room_id' => $request->ruangan_id,
            'description' => $request->keterangan,
        ]);

        return redirect()
            ->route('agenda.index')
            ->with(
                'success',
                'Agenda berhasil diperbarui.'
            );
    }

    /**
     * =========================================================
     * PIC - MENYETUJUI AGENDA
     * =========================================================
     */
    public function approve(Event $event)
    {
        $this->ensurePicOwnsRoom($event);

        return $this->approvePendingEvent($event, 'pic.events');
    }

    /** Admin dapat menyetujui agenda dari halaman Kelola Agenda. */
    public function adminApprove(Event $event)
    {
        if ($event->status !== 'Menunggu') {
            return redirect()->route('agenda.index')->with('error', 'Hanya agenda berstatus Menunggu yang dapat disetujui.');
        }

        return $this->approvePendingEvent($event, 'agenda.index');
    }

    private function approvePendingEvent(Event $event, string $route)
    {
        if ($event->status !== 'Menunggu') {
            return redirect()->route($route)->with('error', 'Hanya agenda berstatus Menunggu yang dapat disetujui.');
        }

        $event->update([
            'status' => 'Disetujui',
            'approved_by' => Auth::id(),
        ]);

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

        return view('pic.reject', compact('event'));
    }

    public function reject(Request $request, Event $event)
    {
        $this->ensurePicOwnsRoom($event);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ], [
            'rejection_reason.required' => 'Keterangan penolakan wajib diisi.',
            'rejection_reason.max' => 'Keterangan penolakan maksimal 2000 karakter.',
        ]);

        $event->update([
            'status' => 'Ditolak',
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
        ]);

        return redirect()
            ->route('pic.events')
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
        $event->delete();

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
        if ($event->status !== 'Menunggu') {
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

        $event->update([
            'status' => 'Ditolak',
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
        ]);

        return redirect()
            ->route('agenda.index')
            ->with('success', 'Agenda berhasil ditolak.');
    }

    /**
     * Batalkan/tolak agenda yang masih menunggu persetujuan.
     */
    public function cancel(Request $request, Event $event)
    {
        if ($event->status !== 'Menunggu') {
            return redirect()
                ->route('agenda.index')
                ->with('error', 'Hanya agenda berstatus Menunggu yang dapat dibatalkan/ditolak.');
        }

        $reason = $request->input('rejection_reason') ?: 'Ditolak oleh Administrator.';

        $event->update([
            'status' => 'Ditolak',
            'approved_by' => Auth::id(),
            'rejection_reason' => $reason,
            'rejected_at' => now(),
        ]);

        return redirect()
            ->route('agenda.index')
            ->with('success', 'Agenda berhasil ditolak.');
    }

    /**
     * =========================================================
     * PIC - VERIFIKASI AGENDA
     * =========================================================
     */
    public function verification()
    {
        $today = Carbon::today()->toDateString();
        $agenda = Event::notExpired()->with([
                'user',
                'room',
                'approver',
            ])
            ->whereHas('room', function (Builder $query) {
                $query->where(
                    'pic_id',
                    Auth::id()
                );
            })
            ->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(10);

        return view(
            'pic.index',
            compact('agenda')
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

        $event->update([
            'status' => 'Disetujui',
            'approved_by' => Auth::id(),
        ]);

        return redirect()
            ->route('pic.agenda')
            ->with(
                'success',
                'Agenda berhasil disetujui.'
            );
    }

    /**
     * =========================================================
     * ROUTE LAMA - TOLAK
     * =========================================================
     */
    public function tolak(Request $request, Event $event)
    {
        $this->ensurePicOwnsRoom($event);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ], [
            'rejection_reason.required' => 'Keterangan penolakan wajib diisi.',
            'rejection_reason.max' => 'Keterangan penolakan maksimal 2000 karakter.',
        ]);

        $event->update([
            'status' => 'Ditolak',
            'approved_by' => Auth::id(),
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
        ]);

        return redirect()
            ->route('pic.agenda')
            ->with(
                'success',
                'Agenda berhasil ditolak.'
            );
    }

    /**
     * =========================================================
     * AGENDA MILIK GURU
     * =========================================================
     */
    public function agendaGuru()
    {
        $today = Carbon::now('Asia/Jakarta')->toDateString();
        $agenda = Event::visibleInTeacherAgenda()
            ->with([
                'user',
                'room',
                'approver',
            ])
            ->where(
                'requester_id',
                Auth::id()
            )
            ->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(10);

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
                'notifications'
            )
        );
    }

    /**
     * =========================================================
     * ALIAS AGENDA GURU
     * =========================================================
     */
    public function myEvents()
    {
        return $this->agendaGuru();
    }
    
    public function teacherCalendar()
{
    $events = Event::notExpired()->with(['room', 'requester', 'user'])
        ->where('status', 'Disetujui')
        ->orderBy('date')
        ->orderBy('start_time')
        ->get()
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

                'room' => $event->room?->room_name
                    ?? 'Tanpa ruangan',

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
        $agenda = Event::notExpired()->with([
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
    $events = Event::notExpired()->with(['room', 'requester', 'user'])
        ->where('status', 'Disetujui')
        ->orderBy('date')
        ->orderBy('start_time')
        ->get()
        ->map(function (Event $event) {
            return [
                'id' => $event->id,
                'title' => $event->event_name,
                'date' => $event->getRawOriginal('date')
                    ? Carbon::parse(
                        $event->getRawOriginal('date')
                    )->format('Y-m-d')
                    : null,
                'end_date' => $event->end_date
                    ? Carbon::parse($event->end_date)->format('Y-m-d')
                    : ($event->getRawOriginal('date') ? Carbon::parse($event->getRawOriginal('date'))->format('Y-m-d') : null),
                'date_range' => $event->formatted_date_range,
                'time' => $event->start_time
                    ? substr($event->start_time, 0, 5)
                        . ' - ' .
                        substr($event->end_time, 0, 5)
                    : '-',
                'room' => $event->room?->room_name
                    ?? 'Tanpa ruangan',
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
        $agenda = $query->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

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
        if ($event->status === 'Ditolak') {
            return redirect()->route('agenda.index')->with('error', 'Ruangan agenda yang ditolak tidak dapat dipindahkan.');
        }

        $rooms = Room::where(
                'is_active',
                true
            )
            ->whereKeyNot($event->room_id)
            ->orderBy('room_name')
            ->get();

        return view(
            'admin.event.move',
            compact(
                'event',
                'rooms'
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
        if ($event->status === 'Ditolak') {
            return redirect()->route('agenda.index')->with('error', 'Ruangan agenda yang ditolak tidak dapat dipindahkan.');
        }

        $request->validate([
            'room_id' => [
                'required',
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
            'move_reason.required' =>
                'Alasan pemindahan wajib diisi.',
        ]);

        /**
         * =====================================================
         * CEK RUANGAN SAMA
         * =====================================================
         */
        if ((int) $request->room_id === (int) $event->room_id) {
            return back()
                ->withInput()
                ->withErrors([
                    'room_id' =>
                        'Ruangan tujuan harus berbeda dari ruangan saat ini.',
                ]);
        }

        /**
         * =====================================================
         * CEK RUANGAN AKTIF
         * =====================================================
         */
        $room = Room::whereKey(
                $request->room_id
            )
            ->where(
                'is_active',
                true
            )
            ->first();

        if (!$room) {
            return back()
                ->withInput()
                ->withErrors([
                    'room_id' =>
                        'Ruangan tujuan tidak tersedia.',
                ]);
        }

        /**
         * =====================================================
         * CEK BENTROK RUANGAN TUJUAN
         * =====================================================
         */
        if (
            $this->hasScheduleConflict(
                (int) $room->id,
                $event->date,
                $event->start_time,
                $event->end_time,
                $event->id,
                $event->end_date ?? $event->date
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'room_id' =>
                        'Ruangan tujuan sudah terpakai pada waktu agenda ini.',
                ]);
        }

        /**
         * Simpan nama ruangan lama
         * sebelum update.
         */
        $oldRoomName = $event->room?->room_name
            ?? 'ruangan sebelumnya';

        /**
         * =====================================================
         * UPDATE RUANGAN
         * =====================================================
         */
        $event->update([
            'room_id' => $room->id,
            'move_reason' => $request->move_reason,
        ]);

        /**
         * =====================================================
         * BUAT NOTIFIKASI
         * =====================================================
         */
       ScheduleNotification::create([
    'user_id' => $event->requester_id,
    'event_id' => $event->id,
    'title' => 'Ruangan agenda dipindahkan',
    'message' =>
    'Agenda "' . $event->event_name . '" tanggal ' .
    $event->formatted_date_range .
    ' dipindahkan dari ' .
    $oldRoomName .
    ' ke ' .
    $room->room_name .
    '. Alasan: ' .
    $request->move_reason,
]);

        return redirect()
            ->route('agenda.index')
            ->with(
                'success',
                'Ruangan agenda berhasil dipindahkan dan notifikasi telah dibuat.'
            );
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
    $events = Event::notExpired()->with(['room', 'requester', 'user'])
        ->where('status', 'Disetujui')
        ->orderBy('date')
        ->orderBy('start_time')
        ->get()
        ->map(function (Event $event) {
            return [
                'id' => $event->id,

                'title' => $event->event_name,

                'date' => $event->getRawOriginal('date')
    ? Carbon::parse($event->getRawOriginal('date'))->format('Y-m-d')
    : null,
                'end_date' => $event->end_date
                    ? Carbon::parse($event->end_date)->format('Y-m-d')
                    : ($event->getRawOriginal('date') ? Carbon::parse($event->getRawOriginal('date'))->format('Y-m-d') : null),
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

                'room' => $event->room?->room_name
                    ?? 'Tanpa ruangan',

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

        if (
            !$event->room ||
            $event->room->pic_id !== Auth::id()
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

        return Event::query()
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
            )
            ->exists();
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
