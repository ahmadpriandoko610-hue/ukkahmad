<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use App\Models\Complaint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Dashboard Admin
     */
    public function admin()
    {
        $jumlahUser = User::count();

        $jumlahRuangan = Room::count();

        $today = now()->toDateString();

        $jumlahAgenda = Event::notExpired()->count();

        $jumlahPengaduan = Complaint::visibleInActiveList()->count();

        $agendaDisetujui = Event::notExpired()->where('status', 'Disetujui')->count();
        $agendaMenunggu = Event::notExpired()->where('status', 'Menunggu')->count();
        $pengaduanBelum = Complaint::visibleInActiveList()->where('status_repair', 'Belum Diperbaiki')->count();

        $rawAgendaTerbaru = Event::with(['room', 'requester'])
            ->notExpired()
            ->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->take(15)
            ->get();
        $agendaTerbaru = Event::groupEventsForDisplay($rawAgendaTerbaru)->take(5);

        $pengaduanTerbaru = Complaint::with(['room', 'reporter'])
            ->visibleInActiveList()
            ->latest()
            ->take(5)
            ->get();

        $ruanganList = Room::with('pic')
            ->take(4)
            ->get();

        return view('admin.dashboard', compact(
            'jumlahUser',
            'jumlahRuangan',
            'jumlahAgenda',
            'jumlahPengaduan',
            'agendaDisetujui',
            'agendaMenunggu',
            'pengaduanBelum',
            'agendaTerbaru',
            'pengaduanTerbaru',
            'ruanganList'
        ));
    }

    /**
     * Dashboard PIC
     *
     * Statistik hanya menghitung agenda dan pengaduan
     * dari ruangan yang menjadi tanggung jawab PIC
     * yang sedang login.
     */
   public function pic()
{
    $picId = Auth::id();

    /*
    |--------------------------------------------------------------------------
    | RUANGAN TANGGUNG JAWAB PIC
    |--------------------------------------------------------------------------
    */

    $ruanganSaya = Room::where('pic_id', $picId)
        ->where('is_active', true)
        ->orderBy('room_name')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | STATISTIK AGENDA
    |--------------------------------------------------------------------------
    */

    $agendaMasuk = Event::notExpired()->where('status', 'Menunggu')
        ->whereHas('room', function ($query) use ($picId) {
            $query->where('pic_id', $picId);
        })
        ->count();

    $disetujui = Event::notExpired()->where('status', 'Disetujui')
        ->whereHas('room', function ($query) use ($picId) {
            $query->where('pic_id', $picId);
        })
        ->count();

    $ditolak = Event::notExpired()->where('status', 'Ditolak')
        ->whereHas('room', function ($query) use ($picId) {
            $query->where('pic_id', $picId);
        })
        ->count();

    /*
    |--------------------------------------------------------------------------
    | STATISTIK PENGADUAN
    |--------------------------------------------------------------------------
    */

    $pengaduanBelum = Complaint::where(
        'status_repair',
        'Belum Diperbaiki'
    )
        ->whereHas('room', function ($query) use ($picId) {
            $query->where('pic_id', $picId);
        })
        ->count();

    $pengaduanProses = Complaint::where(
        'status_repair',
        'Menunggu Spare Part'
    )
        ->whereHas('room', function ($query) use ($picId) {
            $query->where('pic_id', $picId);
        })
        ->count();

    $pengaduanSelesai = Complaint::visibleInActiveList()->where(
        'status_repair',
        'Sudah Diperbaiki'
    )
        ->whereHas('room', function ($query) use ($picId) {
            $query->where('pic_id', $picId);
        })
        ->count();

    /*
    |--------------------------------------------------------------------------
    | DAFTAR ITEM TERBARU PIC
    |--------------------------------------------------------------------------
    */

    $today = Carbon::today()->toDateString();
    $agendaMenungguList = Event::with(['room', 'requester'])
        ->notExpired()
        ->where('status', 'Menunggu')
        ->whereHas('room', function ($query) use ($picId) {
            $query->where('pic_id', $picId);
        })
        ->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
        ->orderBy('date', 'asc')
        ->orderBy('start_time', 'asc')
        ->take(5)
        ->get();

    $pengaduanTerbaruList = Complaint::with(['room', 'reporter'])
        ->whereHas('room', function ($query) use ($picId) {
            $query->where('pic_id', $picId);
        })
        ->latest()
        ->take(5)
        ->get();

    /*
    |--------------------------------------------------------------------------
    | KIRIM DATA KE DASHBOARD PIC
    |--------------------------------------------------------------------------
    */

    return view('pic.dashboard', compact(
        'agendaMasuk',
        'disetujui',
        'ditolak',
        'pengaduanBelum',
        'pengaduanProses',
        'pengaduanSelesai',
        'ruanganSaya',
        'agendaMenungguList',
        'pengaduanTerbaruList'
    ));
}

    /**
     * Dashboard Guru
     */
    public function guru()
    {
        $userId = Auth::id();

        $agendaSaya = Event::notExpired()->where(
            'requester_id',
            $userId
        )->count();

        $disetujui = Event::notExpired()->where(
            'requester_id',
            $userId
        )
            ->where('status', 'Disetujui')
            ->count();

        $menunggu = Event::notExpired()->where(
            'requester_id',
            $userId
        )
            ->where('status', 'Menunggu')
            ->count();

        $ditolak = Event::notExpired()->where(
            'requester_id',
            $userId
        )
            ->where('status', 'Ditolak')
            ->count();

        $totalPengaduanSaya = Complaint::where('reporter_id', $userId)->count();

        $today = Carbon::today()->toDateString();
        $rawAgendaTerbaru = Event::with('room')
            ->notExpired()
            ->where('requester_id', $userId)
            ->orderByRaw("CASE WHEN COALESCE(end_date, date) >= ? THEN 0 ELSE 1 END", [$today])
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->take(15)
            ->get();
        $agendaTerbaru = Event::groupEventsForDisplay($rawAgendaTerbaru)->take(5);

        $pengaduanSaya = Complaint::with('room')
            ->where('reporter_id', $userId)
            ->latest()
            ->take(5)
            ->get();

        return view('teacher.dashboard', compact(
            'agendaSaya',
            'disetujui',
            'menunggu',
            'ditolak',
            'totalPengaduanSaya',
            'agendaTerbaru',
            'pengaduanSaya'
        ));
    }
}

