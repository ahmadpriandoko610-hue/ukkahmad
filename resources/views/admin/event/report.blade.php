@extends('layouts.app')

@section('title', 'Laporan Agenda')
@section('judul', 'Laporan Agenda')

@section('content')
<div class="report-screen-only" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:20px;">
    <form method="GET" action="{{ route('admin.agenda.report') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
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
            <a href="{{ route('admin.agenda.report', ['periode' => $periode, 'tanggal' => $tanggal->format('Y-m-d'), 'unduh' => 'pdf']) }}" class="btn btn-edit">Unduh PDF</a>
        </div>
    </details>
</div>

<section id="agenda-report">
    <h2>Laporan Agenda {{ ucfirst($periode) }}</h2>
    <p>Periode {{ $mulai->format('d-m-Y') }} sampai {{ $selesai->format('d-m-Y') }}</p>
    <p>Jumlah agenda: <strong>{{ $agenda->count() }}</strong></p>

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
                    <th>No</th><th>Tanggal</th><th>Waktu</th><th>Judul Agenda</th>
                    <th>Ruangan</th><th>Pengaju</th><th>Status</th><th>Disetujui</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agenda as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->formatted_date_range }}</td>
                        <td>{{ $item->start_time }} - {{ $item->end_time }}</td>
                        <td>{{ $item->event_name }}</td>
                        <td>{{ $item->room?->room_name ?? '-' }}</td>
                        <td>{{ $item->user?->name ?? '-' }}</td>
                        <td>{{ $item->status === 'Disetujui' ? $item->status_pelaksanaan : $item->status }}</td>
                        <td>{{ $item->approver?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;">Tidak ada agenda pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<style>
@media print {
    .sidebar, .navbar-top, .report-screen-only, button { display:none !important; }
    .main-wrapper, .content-body, #agenda-report { width:100% !important; margin:0 !important; padding:0 !important; }
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
