<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Notification;
use App\Models\User;
use App\Support\TablePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ComplaintController extends Controller
{
    protected function getAuthUser(Request $request): ?User
    {
        return $request->user();
    }

    // =========================
    // GET /api/pengaduan
    // =========================
    public function index(Request $request)
    {
        $authUser = $this->getAuthUser($request);
        $filters = $request->validate([
            'status_repair' => 'sometimes|string|in:Belum Diperbaiki,Menunggu Spare Part,Sudah Diperbaiki',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        // Siswa tidak boleh melihat pengaduan
        if ($authUser && $authUser->role === 'siswa') {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak memiliki izin untuk melihat pengaduan kerusakan.'
            ], 403);
        }

        $query = Complaint::with([
            'room',
            'reporter',
            'handler'
        ])
        ->visibleInActiveList()
        ->orderByDesc('reported_at');

        // Filter hak akses:
        // PIC hanya melihat pengaduan di ruangan tanggung jawabnya
        if ($authUser && $authUser->role === 'pic') {
            $query->whereHas('room', function ($q) use ($authUser) {
                $q->where('pic_id', $authUser->id);
            });
        } elseif ($authUser && $authUser->role === 'guru') {
            // Guru hanya melihat pengaduan yang dibuatnya sendiri
            $query->where('reporter_id', $authUser->id);
        }

        if (isset($filters['status_repair'])) {
            $query->where('status_repair', $filters['status_repair']);
        }

        $complaints = $query->paginate($filters['per_page'] ?? 100);

        return response()->json([
            'success' => true,
            'message' => 'Data pengaduan berhasil diambil',
            'data' => $complaints->items(),
            'meta' => [
                'current_page' => $complaints->currentPage(),
                'per_page' => $complaints->perPage(),
                'total' => $complaints->total(),
                'last_page' => $complaints->lastPage(),
            ],
        ]);
    }

    // =========================
    // GET /api/pengaduan/{id}
    // =========================
    public function show(Request $request, int $id)
    {
        $authUser = $this->getAuthUser($request);

        if ($authUser && $authUser->role === 'siswa') {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak memiliki izin untuk melihat pengaduan kerusakan.'
            ], 403);
        }

        $complaint = Complaint::with([
            'room',
            'reporter',
            'handler'
        ])->find($id);

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaduan tidak ditemukan'
            ], 404);
        }

        // Cek hak akses PIC: Hanya ruangan yang ditugaskan
        if ($authUser && $authUser->role === 'pic') {
            if (!$complaint->room || $complaint->room->pic_id !== $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengaduan ini bukan tanggung jawab Anda.'
                ], 403);
            }
        } elseif ($authUser && $authUser->role === 'guru') {
            if ($complaint->reporter_id !== $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki hak untuk melihat pengaduan milik pengguna lain.'
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail pengaduan berhasil diambil',
            'data' => $complaint
        ]);
    }

    // =========================
    // POST /api/pengaduan
    // =========================
    public function store(Request $request)
    {
        $authUser = $this->getAuthUser($request);

        // Siswa tidak boleh membuat pengaduan
        if ($authUser && $authUser->role === 'siswa') {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak memiliki izin untuk mengirimkan pengaduan kerusakan.'
            ], 403);
        }

        // PIC tidak membuat pengaduan kerusakan
        if ($authUser && $authUser->role === 'pic') {
            return response()->json([
                'success' => false,
                'message' => 'PIC Ruang tidak memiliki izin untuk membuat pengaduan kerusakan.'
            ], 403);
        }

        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'description' => 'required|string|max:5000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:2048',
        ], [
            'room_id.required' => 'Ruangan wajib dipilih.',
            'description.required' => 'Deskripsi kerusakan wajib diisi.',
            'photo.image' => 'File yang diunggah harus berupa gambar (jpg, jpeg, png, webp).',
            'photo.max' => 'Ukuran foto maksimal adalah 2MB (2048 KB).',
        ]);

        $dataToSave = [
            'room_id' => $validated['room_id'],
            'reporter_id' => $authUser->id,
            'description' => $validated['description'],
            'status_repair' => 'Belum Diperbaiki',
            'handled_by' => null,
            'reported_at' => now(config('app.timezone', 'Asia/Jakarta')),
        ];

        if ($request->hasFile('photo')) {
            $dataToSave['photo_path'] = $request->file('photo')
                ->store('complaints', 'public');
        }

        $complaint = DB::transaction(fn () => Complaint::create($dataToSave));
        $complaint->load(['room', 'reporter', 'handler']);

        return response()->json([
            'success' => true,
            'message' => 'Laporan pengaduan kerusakan berhasil dikirim',
            'data' => $complaint
        ], 201);
    }

    // =========================
    // PUT /api/pengaduan/{id}/status
    // =========================
    public function updateStatus(Request $request, int $id)
    {
        $authUser = $this->getAuthUser($request);

        if (!$authUser || $authUser->role !== 'pic') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya PIC Ruang yang berwenang memperbarui status pengaduan.'
            ], 403);
        }

        $complaint = Complaint::with('room')->find($id);

        if (!$complaint) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaduan tidak ditemukan'
            ], 404);
        }

        // Pastikan PIC bertanggung jawab atas ruangan tersebut
        if (!$complaint->room || $complaint->room->pic_id !== $authUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaduan ini bukan tanggung jawab Anda.'
            ], 403);
        }

        $request->validate([
            'status_repair' => 'required|in:Belum Diperbaiki,Menunggu Spare Part,Sudah Diperbaiki',
            'repair_note' => 'nullable|string|max:2000',
        ], [
            'status_repair.required' => 'Status perbaikan wajib dipilih.',
            'status_repair.in' => 'Status perbaikan tidak valid.',
            'repair_note.max' => 'Keterangan perbaikan maksimal 2000 karakter.',
        ]);

        $oldStatus = $complaint->status_repair;
        $newStatus = $request->status_repair;
        $repairNote = $request->input('repair_note') ?? ($newStatus === 'Sudah Diperbaiki' ? $complaint->repair_note : null);

        // Update status dan set PIC yang menangani dari user yang terotentikasi (bukan hardcoded!)
        DB::transaction(function () use ($complaint, $authUser, $oldStatus, $newStatus, $repairNote): void {
            $complaint->update([
                'status_repair' => $newStatus,
                'repair_note' => $repairNote,
                'repaired_at' => $newStatus === 'Sudah Diperbaiki'
                    ? ($complaint->repaired_at ?? now(config('app.timezone', 'Asia/Jakarta')))
                    : null,
                'handled_by' => $authUser->id,
            ]);

            $notificationMessage = 'Pengaduan kerusakan di ruangan ' .
                ($complaint->room?->room_name ?? '-') .
                ' berubah dari "' . $oldStatus . '" menjadi "' . $newStatus . '".' .
                ($repairNote ? ' Keterangan: ' . $repairNote : '');

            Notification::create([
                'user_id' => $complaint->reporter_id,
                'complaint_id' => $complaint->id,
                'title' => 'Status Pengaduan Diperbarui',
                'message' => $notificationMessage,
                'is_read' => false,
            ]);
        });

        $complaint->load([
            'room',
            'reporter',
            'handler'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status pengaduan berhasil diperbarui',
            'data' => $complaint
        ]);
    }

    // =========================
    // GET /api/pic/pengaduan/report
    // =========================
    public function picReport(Request $request)
    {
        $authUser = $this->getAuthUser($request);

        if (!$authUser || $authUser->role !== 'pic') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya PIC Ruang yang berwenang mengakses laporan pengaduan.'
            ], 403);
        }

        // Sinkronisasi parameter tanggal mulai dan selesai
        if ($request->filled('start_date') && !$request->filled('tanggal_mulai')) {
            $request->merge(['tanggal_mulai' => $request->input('start_date')]);
        }
        if ($request->filled('end_date') && !$request->filled('tanggal_selesai')) {
            $request->merge(['tanggal_selesai' => $request->input('end_date')]);
        }

        // Dukung periode harian, mingguan, bulanan seperti website jika tanggal belum diisi spesifik
        if ($request->filled('periode') && !$request->filled('tanggal_mulai')) {
            $periode = $request->input('periode');
            $tanggal = Carbon::parse($request->input('tanggal', today()));
            if ($periode === 'mingguan') {
                $mulaiStr = $tanggal->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
                $selesaiStr = $tanggal->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
            } elseif ($periode === 'bulanan') {
                $mulaiStr = $tanggal->copy()->startOfMonth()->toDateString();
                $selesaiStr = $tanggal->copy()->endOfMonth()->toDateString();
            } else {
                $mulaiStr = $tanggal->toDateString();
                $selesaiStr = $tanggal->toDateString();
            }
            $request->merge([
                'tanggal_mulai' => $mulaiStr,
                'tanggal_selesai' => $selesaiStr,
            ]);
        }

        $validated = $request->validate([
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'unduh' => 'sometimes|string|in:pdf',
            'format' => 'sometimes|string|in:json,pdf',
        ], [
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_mulai.date' => 'Format tanggal mulai tidak valid.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.date' => 'Format tanggal selesai tidak valid.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ]);

        $mulai = Carbon::parse($validated['tanggal_mulai'])->startOfDay();
        $selesai = Carbon::parse($validated['tanggal_selesai'])->endOfDay();

        // Query persis aturan website: hanya pengaduan pada ruangan tanggung jawab PIC terautentikasi
        $complaints = Complaint::with(['room', 'reporter', 'handler'])
            ->whereHas('room', fn ($query) => $query->where('pic_id', $authUser->id))
            ->whereBetween('reported_at', [$mulai, $selesai])
            ->latest('reported_at')
            ->get();

        $jumlahStatus = [
            'Belum Diperbaiki' => $complaints->where('status_repair', 'Belum Diperbaiki')->count(),
            'Menunggu Spare Part' => $complaints->where('status_repair', 'Menunggu Spare Part')->count(),
            'Sudah Diperbaiki' => $complaints->where('status_repair', 'Sudah Diperbaiki')->count(),
        ];

        $rows = $complaints->values()->map(fn ($complaint, $index) => [
            $index + 1,
            $complaint->reported_at?->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d-m-Y H:i') ?? '-',
            $complaint->room?->room_name ?? '-',
            $complaint->reporter?->name ?? '-',
            $complaint->description,
            $complaint->status_repair,
            $complaint->handler?->name ?? '-',
        ])->all();

        $periodLabel = $mulai->format('d-m-Y') === $selesai->format('d-m-Y')
            ? 'Tanggal ' . $mulai->format('d-m-Y')
            : 'Periode ' . $mulai->format('d-m-Y') . ' sampai ' . $selesai->format('d-m-Y');

        $filename = 'laporan-kerusakan-pic-' . $mulai->format('Y-m-d') . '-sd-' . $selesai->format('Y-m-d') . '.pdf';

        $pdfBinary = TablePdf::make(
            'Laporan Kerusakan',
            $periodLabel,
            [
                'Total: ' . $complaints->count(),
                'Belum diperbaiki: ' . $jumlahStatus['Belum Diperbaiki'],
                'Menunggu spare part: ' . $jumlahStatus['Menunggu Spare Part'],
                'Sudah diperbaiki: ' . $jumlahStatus['Sudah Diperbaiki']
            ],
            ['No', 'Waktu Laporan', 'Ruangan', 'Pelapor', 'Deskripsi Kerusakan', 'Status', 'Ditangani Oleh'],
            $rows,
            [28, 70, 75, 90, 230, 105, 110]
        );

        if ($request->query('unduh') === 'pdf' || $request->query('format') === 'pdf') {
            return response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data laporan pengaduan berhasil diambil',
            'data' => [
                'tanggal_mulai' => $mulai->format('Y-m-d'),
                'tanggal_selesai' => $selesai->format('Y-m-d'),
                'label_periode' => $periodLabel,
                'total' => $complaints->count(),
                'ringkasan_status' => $jumlahStatus,
                'complaints' => $complaints,
                'pdf_base64' => base64_encode($pdfBinary),
                'pdf_filename' => $filename,
            ]
        ]);
    }
}
