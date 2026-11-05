@extends('layouts.app')

@section('title', 'Laporan Kerusakan')
@section('judul', 'Laporan Kerusakan')

@section('sidebar')
<a href="{{ route('dashboard.pic') }}" class="menu-item">Dashboard</a>
<a href="{{ route('pic.agenda') }}" class="menu-item">Verifikasi Agenda</a>
<a href="{{ route('pic.calendar') }}" class="menu-item">Kalender Agenda</a>
<a href="{{ route('pic.complaints') }}" class="menu-item">Pengaduan Kerusakan</a>
<a href="{{ route('pic.complaints.report') }}" class="menu-item active">Laporan Kerusakan</a>
<a href="{{ route('pic.schedule') }}" class="menu-item">Jadwal Ruangan</a>
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">Logout</button>
</form>
@endsection

@section('content')
<div class="report-screen-only" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:20px;">
    <form method="GET" action="{{ route('pic.complaints.report') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
        <label>Periode<br>
            <select name="periode" style="padding:10px;border:1px solid #ccc;border-radius:6px;">
                <option value="harian" @selected($periode === 'harian')>Harian</option>
                <option value="mingguan" @selected($periode === 'mingguan')>Mingguan</option>
                <option value="bulanan" @selected($periode === 'bulanan')>Bulanan</option>
            </select>
        </label>
        <label>Tanggal acuan<br>
            <input type="date" name="tanggal" value="{{ $tanggal->format('Y-m-d') }}" style="padding:9px;border:1px solid #ccc;border-radius:6px;">
        </label>
        <button type="submit" class="btn btn-edit">Tampilkan</button>
    </form>
    <details class="report-actions">
        <summary class="btn btn-edit">Cetak Laporan</summary>
        <div class="report-actions-menu">
            <button type="button" class="btn btn-edit" onclick="window.print()">Cetak</button>
            <a href="{{ route('pic.complaints.report', ['periode' => $periode, 'tanggal' => $tanggal->format('Y-m-d'), 'unduh' => 'pdf']) }}" class="btn btn-edit">Unduh PDF</a>
        </div>
    </details>
</div>

<section id="complaint-report">
    <h2>Laporan Kerusakan {{ ucfirst($periode) }}</h2>
    <p>Periode {{ $mulai->format('d-m-Y') }} sampai {{ $selesai->format('d-m-Y') }}</p>
    <p>Jumlah pengaduan: <strong>{{ $complaints->count() }}</strong></p>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0 24px;">
        @foreach($jumlahStatus as $status => $jumlah)
            <div style="padding:12px 16px;background:#f3f4f6;border-radius:8px;">
                {{ $status }}: <strong>{{ $jumlah }}</strong>
            </div>
        @endforeach
    </div>

    <div class="agenda-table-wrapper">
        <table class="agenda-table">
            <thead>
                <tr>
                    <th>No</th><th>Waktu Laporan</th><th>Ruangan</th><th>Pelapor</th>
                    <th>Deskripsi Kerusakan</th><th>Status</th><th>Ditangani Oleh</th>
                </tr>
            </thead>
            <tbody>
                @forelse($complaints as $complaint)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $complaint->reported_at?->format('d-m-Y H:i') ?? '-' }}</td>
                        <td>{{ $complaint->room?->room_name ?? '-' }}</td>
                        <td>{{ $complaint->reporter?->name ?? '-' }}</td>
                        <td>{{ $complaint->description }}</td>
                        <td>
                            <strong>{{ $complaint->status_repair }}</strong>
                            @if ($complaint->repair_note)
                                <div style="font-size: 11.5px; color: #166534; margin-top: 4px; line-height: 1.3;">
                                    <em>Ket: {{ $complaint->repair_note }}</em>
                                </div>
                            @endif
                        </td>
                        <td>{{ $complaint->handler?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;">Tidak ada pengaduan pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<style>
@media print {
    .sidebar, nav, header, .report-screen-only, button { display:none !important; }
    body, main, #complaint-report { width:100% !important; margin:0 !important; padding:0 !important; }
    .agenda-table-wrapper { overflow:visible !important; }
    .agenda-table { width:100%; border-collapse:collapse; }
    .agenda-table th, .agenda-table td { border:1px solid #777; padding:6px; color:#000; }
}
.report-actions { position:relative; }
.report-actions summary { list-style:none; cursor:pointer; }
.report-actions summary::-webkit-details-marker { display:none; }
.report-actions-menu { position:absolute; z-index:5; top:calc(100% + 6px); right:0; display:flex; gap:8px; padding:10px; background:#fff; border:1px solid #ddd; border-radius:8px; box-shadow:0 4px 12px #0002; white-space:nowrap; }
</style>
@endsection
