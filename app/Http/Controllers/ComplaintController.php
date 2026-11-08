<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use App\Models\Notification;
use App\Support\TablePdf;

class ComplaintController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GURU
    |--------------------------------------------------------------------------
    */

    /**
     * Menampilkan pengaduan milik user yang sedang login.
     */
    public function index()
    {
        $complaints = Complaint::with([
                'room',
                'handler',
            ])
            ->visibleInActiveList()
            ->where('reporter_id', Auth::id())
            ->latest('reported_at')
            ->paginate(5);

        return view('complaints.index', compact('complaints'));
    }

    /**
     * Menampilkan form pengaduan.
     */
    public function create()
    {
        $agenda = null;
        if (request()->filled('agenda_id')) {
            $agenda = Event::with('room')
                ->whereKey(request('agenda_id'))
                ->where('requester_id', Auth::id())
                ->where('status', 'Disetujui')
                ->firstOrFail();
        }

        $rooms = Room::sortRooms(
            Room::where('is_active', true)->get()
        );

        return view('complaints.create', compact('rooms', 'agenda'));
    }

    /**
     * Menyimpan pengaduan baru.
     */
    public function store(Request $request)
    {
        $agenda = null;
        if ($request->filled('agenda_id')) {
            $agenda = Event::with('room')
                ->whereKey($request->input('agenda_id'))
                ->where('requester_id', Auth::id())
                ->where('status', 'Disetujui')
                ->first();

            if (!$agenda) {
                return back()->withInput()->withErrors([
                    'agenda_id' => 'Agenda tidak ditemukan atau belum disetujui.',
                ]);
            }
        }

        $request->validate([
            'agenda_id' => ['nullable', 'integer'],
            'room_id' => $agenda ? ['nullable'] : [
                'required',
                'exists:rooms,id',
            ],

            'description' => [
                'required',
                'string',
            ],

            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ], [
            'room_id.required' =>
                'Ruangan wajib dipilih.',

            'room_id.exists' =>
                'Ruangan yang dipilih tidak ditemukan.',

            'description.required' =>
                'Deskripsi kerusakan wajib diisi.',

            'photo.required' =>
                'Foto kerusakan wajib dilampirkan.',

            'photo.image' =>
                'File yang diupload harus berupa gambar.',

            'photo.mimes' =>
                'Foto harus berformat JPG, JPEG, PNG, atau WEBP.',

            'photo.max' =>
                'Ukuran foto maksimal 2 MB.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | CEK RUANGAN AKTIF
        |--------------------------------------------------------------------------
        */

        $roomId = $agenda ? $agenda->room_id : $request->input('room_id');
        $room = Room::where('id', $roomId)
            ->where('is_active', true)
            ->first();

        if (!$room) {
            return back()
                ->withInput()
                ->withErrors([
                    'room_id' =>
                        'Ruangan yang dipilih sudah tidak aktif.',
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | UPLOAD FOTO
        |--------------------------------------------------------------------------
        */

        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');

            if (!$photo->isValid()) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'photo' =>
                            'Foto gagal diupload. Silakan pilih foto lain.',
                    ]);
            }

            $photoPath = $photo->store(
                'complaints',
                'public'
            );

            if (!$photoPath) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'photo' =>
                            'Foto gagal disimpan. Silakan coba lagi.',
                    ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SIMPAN PENGADUAN
        |--------------------------------------------------------------------------
        */

        Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => Auth::id(),
            'description' => $request->description,
            'photo_path' => $photoPath,
            'status_repair' => 'Belum Diperbaiki',
            'handled_by' => null,
            'reported_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | REDIRECT
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->route('complaints.index')
            ->with(
                'success',
                'Pengaduan kerusakan berhasil dikirim.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | PIC
    |--------------------------------------------------------------------------
    */

    /**
     * Menampilkan pengaduan untuk PIC.
     */
    public function picIndex()
    {
        $complaints = Complaint::with([
                'room',
                'event',
                'reporter',
                'handler',
            ])
            ->visibleInActiveList()
            ->whereHas('room', function ($query) {
                $query->where('pic_id', Auth::id());
            })
            ->latest('reported_at')
            ->paginate(5);

        return view(
            'pic.complaints.index',
            compact('complaints')
        );
    }

    /** Laporan pengaduan PIC berdasarkan periode harian, mingguan, atau bulanan. */
    public function picReport(Request $request)
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

        $complaints = Complaint::with(['room', 'reporter', 'handler'])
            ->whereHas('room', fn ($query) => $query->where('pic_id', Auth::id()))
            ->whereBetween('reported_at', [$mulai, $selesai])
            ->latest('reported_at')
            ->get();

        $jumlahStatus = [
            'Belum Diperbaiki' => $complaints->where('status_repair', 'Belum Diperbaiki')->count(),
            'Menunggu Spare Part' => $complaints->where('status_repair', 'Menunggu Spare Part')->count(),
            'Sudah Diperbaiki' => $complaints->where('status_repair', 'Sudah Diperbaiki')->count(),
        ];

        if ($request->query('unduh') === 'pdf') {
            $rows = $complaints->values()->map(fn ($complaint, $index) => [
                $index + 1,
                $complaint->reported_at?->format('d-m-Y H:i') ?? '-',
                $complaint->room?->room_name ?? '-',
                $complaint->reporter?->name ?? '-',
                $complaint->description,
                $complaint->status_repair . ($complaint->repair_note ? ' (' . $complaint->repair_note . ')' : ''),
                $complaint->handler?->name ?? '-',
            ])->all();
            $filename = 'laporan-kerusakan-' . $periode . '-' . $tanggal->format('Y-m-d') . '.pdf';
            $pdf = TablePdf::make(
                'Laporan Kerusakan ' . ucfirst($periode),
                'Periode ' . $mulai->format('d-m-Y') . ' sampai ' . $selesai->format('d-m-Y'),
                ['Total: ' . $complaints->count(), 'Belum diperbaiki: ' . $jumlahStatus['Belum Diperbaiki'], 'Menunggu spare part: ' . $jumlahStatus['Menunggu Spare Part'], 'Sudah diperbaiki: ' . $jumlahStatus['Sudah Diperbaiki']],
                ['No', 'Waktu Laporan', 'Ruangan', 'Pelapor', 'Deskripsi Kerusakan', 'Status', 'Ditangani Oleh'],
                $rows,
                [28, 70, 75, 90, 230, 105, 110]
            );

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        return view('pic.complaints.report', compact(
            'complaints', 'periode', 'tanggal', 'mulai', 'selesai', 'jumlahStatus'
        ));
    }

    /**
     * PIC memperbarui status pengaduan.
     */
    public function update(
        Request $request,
        Complaint $complaint
    ) {
        /*
        |--------------------------------------------------------------------------
        | CEK ROLE PIC
        |--------------------------------------------------------------------------
        */

        if (
            !Auth::check() ||
            Auth::user()->role !== 'pic'
        ) {
            abort(
                403,
                'Anda tidak memiliki akses untuk menangani pengaduan ini.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | CEK TANGGUNG JAWAB RUANGAN
        |--------------------------------------------------------------------------
        */

        if (
            !$complaint->room ||
            $complaint->room->pic_id !== Auth::id()
        ) {
            abort(
                403,
                'Pengaduan ini bukan tanggung jawab Anda.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | VALIDASI STATUS
        |--------------------------------------------------------------------------
        */

        $request->validate([
            'status_repair' => [
                'required',
                'in:Belum Diperbaiki,Menunggu Spare Part,Sudah Diperbaiki',
            ],
            'repair_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ], [
            'status_repair.required' =>
                'Status perbaikan wajib dipilih.',

            'status_repair.in' =>
                'Status perbaikan tidak valid.',

            'repair_note.max' =>
                'Keterangan perbaikan maksimal 2000 karakter.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | UPDATE STATUS
        |--------------------------------------------------------------------------
        */

        $oldStatus = $complaint->status_repair;
        $newStatus = $request->status_repair;
        $repairNote = $request->input('repair_note') ?? ($newStatus === 'Sudah Diperbaiki' ? $complaint->repair_note : null);

        $complaint->update([
            'status_repair' => $newStatus,
            'repair_note' => $repairNote,
            'repaired_at' => $newStatus === 'Sudah Diperbaiki'
                ? ($complaint->repaired_at ?? now())
                : null,
            'handled_by' => Auth::id(),
        ]);

        $notificationMessage = 'Pengaduan kerusakan di ruangan ' .
            ($complaint->room?->room_name ?? '-') .
            ' berubah dari "' .
            $oldStatus .
            '" menjadi "' .
            $complaint->status_repair .
            '".' . ($repairNote ? ' Keterangan: ' . $repairNote : '');

        Notification::create([
            'user_id' => $complaint->reporter_id,
            'complaint_id' => $complaint->id,
            'title' => 'Status Pengaduan Diperbarui',
            'message' => $notificationMessage,
            'is_read' => false,
        ]);

        return redirect()
            ->route('pic.complaints')
            ->with(
                'success',
                'Status pengaduan berhasil diperbarui.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN
    |--------------------------------------------------------------------------
    */

    /**
     * Menampilkan seluruh pengaduan kepada Admin.
     */
    public function adminIndex()
    {
        $complaints = Complaint::with([
                'room',
                'event',
                'reporter',
                'handler',
            ])
            ->visibleInActiveList()
            ->latest('reported_at')
            ->paginate(5);

        return view(
            'admin.complaints.index',
            compact('complaints')
        );
    }

    /** Form pengaduan admin yang dikaitkan langsung ke agenda dan ruangannya. */
    public function createForEvent(Event $event)
    {
        abort_unless($event->status === 'Disetujui', 404);

        $event->load('room');

        return view('admin.complaints.create', compact('event'));
    }

    /** Simpan pengaduan admin dengan ruangan yang diambil dari agenda. */
    public function storeForEvent(Request $request, Event $event)
    {
        abort_unless($event->status === 'Disetujui', 404);

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:5000'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'description.required' => 'Deskripsi kerusakan wajib diisi.',
            'photo.required' => 'Foto kerusakan wajib dilampirkan.',
            'photo.image' => 'File yang diunggah harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, JPEG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $photoPath = $request->file('photo')->store('complaints', 'public');

        if (!$photoPath) {
            return back()->withInput()->withErrors(['photo' => 'Foto gagal disimpan. Silakan coba lagi.']);
        }

        Complaint::create([
            'room_id' => $event->room_id,
            'event_id' => $event->id,
            'reporter_id' => Auth::id(),
            'description' => $validated['description'],
            'photo_path' => $photoPath,
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        return redirect()->route('admin.complaints')->with('success', 'Pengaduan kerusakan untuk agenda berhasil dikirim.');
    }
public function whatsappWarning(Complaint $complaint)
{
    $complaint->load([
        'room.pic',
    ]);

    // Hanya pengaduan yang belum diperbaiki
    // yang boleh dikirimkan peringatan WhatsApp.
    if ($complaint->status_repair !== 'Belum Diperbaiki') {
        return back()->withErrors([
            'warning' =>
                'Peringatan WhatsApp hanya dapat dikirim untuk pengaduan yang masih Belum Diperbaiki.',
        ]);
    }

    // Ambil PIC dari ruangan pengaduan
    $pic = $complaint->room?->pic;

    if (!$pic) {
        return back()->withErrors([
            'warning' =>
                'PIC untuk ruangan pengaduan tidak ditemukan.',
        ]);
    }

    // Ambil nomor HP PIC
    $phone = preg_replace(
        '/\D+/',
        '',
        (string) $pic->phone
    );

    if (!$phone) {
        return back()->withErrors([
            'warning' =>
                'Nomor WhatsApp PIC ruangan belum tersedia.',
        ]);
    }

    // Ubah nomor 08xxx menjadi 628xxx
    if (str_starts_with($phone, '0')) {
        $phone = '62' . substr($phone, 1);
    }

    $roomName =
        $complaint->room?->room_name
        ?? 'Tanpa ruangan';

    $description =
        $complaint->description
        ?? '-';

    // Pesan WhatsApp
    $message =
        "🔔 Peringatan RAPI-Sekolah\n\n" .
        "Terdapat pengaduan kerusakan fasilitas " .
        "yang masih berstatus Belum Diperbaiki.\n\n" .
        "Ruangan   : {$roomName}\n" .
        "Pengaduan : {$description}\n" .
        "Status    : {$complaint->status_repair}\n\n" .
        "Mohon segera ditindaklanjuti.\n\n" .
        "Terima kasih.";

    // Buka WhatsApp
    return redirect()->away(
        'https://wa.me/' .
        $phone .
        '?text=' .
        rawurlencode($message)
    );
}
}
