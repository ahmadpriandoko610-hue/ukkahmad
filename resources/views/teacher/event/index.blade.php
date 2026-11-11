@extends('layouts.app')

@section('title', 'Agenda Saya')

@section('judul', 'Agenda Saya')

@section('sidebar')

<a href="{{ route('dashboard.guru') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('guru.events') }}" class="menu-item">
    Pengajuan Event
</a>

<a href="{{ route('guru.agenda') }}" class="menu-item active">
    Agenda Saya
</a>

<a href="{{ route('complaints.index') }}" class="menu-item">
    Pengaduan Kerusakan
</a>

<form method="POST" action="{{ route('logout') }}">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection


@section('content')

@php
    $guruFilterRoute = request()->routeIs('guru.events') ? route('guru.events') : route('guru.agenda');
@endphp

<h2>Agenda Saya</h2>

@if(session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif

@if(session('warning'))
    <div class="alert alert-warning">
        {{ session('warning') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@foreach ($notifications as $notification)
    <div class="alert alert-warning" role="alert">
        <strong>{{ $notification->title }}</strong><br>
        {{ $notification->message }}
    </div>
@endforeach

<form action="{{ $guruFilterRoute }}" method="GET" class="date-filter-form">
    <div class="filter-field filter-field-search">
        <label for="guru_agenda_search">Pencarian</label>
        <input
            type="search"
            id="guru_agenda_search"
            name="search"
            value="{{ old('search', $filters['search'] ?? request('search')) }}"
            class="form-control"
            placeholder="Cari judul agenda atau ruangan..."
        >
    </div>

    <div class="filter-field">
        <label for="guru_tanggal_mulai">Tanggal Mulai</label>
        <input
            type="date"
            id="guru_tanggal_mulai"
            name="tanggal_mulai"
            value="{{ old('tanggal_mulai', $filters['tanggal_mulai'] ?? request('tanggal_mulai')) }}"
            class="form-control"
        >
    </div>

    <div class="filter-field">
        <label for="guru_tanggal_selesai">Tanggal Selesai</label>
        <input
            type="date"
            id="guru_tanggal_selesai"
            name="tanggal_selesai"
            value="{{ old('tanggal_selesai', $filters['tanggal_selesai'] ?? request('tanggal_selesai')) }}"
            class="form-control"
        >
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-edit">Terapkan Filter</button>
        <a href="{{ $guruFilterRoute }}" class="btn btn-back">Reset Filter</a>
    </div>
</form>


<div class="table-container agenda-table-wrapper">

    <table class="agenda-table">

        <thead>
            <tr>
                <th>No</th>
                <th>Judul</th>
                <th>Ruangan</th>
                <th>Tanggal</th>
                <th>Waktu Sesi</th>
                <th>Jam Mulai</th>
                <th>Jam Selesai</th>
                <th>Status</th>
                <th>Disetujui</th>
                <th>Keterangan Penolakan</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody>

        @forelse($agenda as $item)

            <tr>

                <td>
                    {{ method_exists($agenda, 'firstItem') && $agenda->firstItem() ? ($agenda->firstItem() + $loop->index) : $loop->iteration }}
                </td>

                <td>
                    {{ $item->judul }}
                </td>

                <td>
                    @if(isset($item->rooms) && $item->rooms->count() > 1)
                        <div class="room-pill-list">
                            @foreach($item->rooms as $r)
                                <span class="room-pill-badge">
                                    {{ $r->room_name ?: $r->nama_ruangan }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        {{ $item->ruangan->nama_ruangan ?? ($item->room->room_name ?? '-') }}
                    @endif
                </td>

                <td>
                    {{ $item->formatted_date_range }}
                </td>

                <td>
                    {{ $item->session_range }}
                </td>

                <td>
                    {{ $item->start_time ? substr((string) $item->start_time, 0, 5) : '-' }}
                </td>

                <td>
                    {{ $item->end_time ? substr((string) $item->end_time, 0, 5) : '-' }}
                </td>

                <td>

                    @if($item->status == 'Menunggu')
                        <span class="badge-warning">
                            Menunggu
                        </span>
                    @elseif($item->status == 'Ditolak')
                        <span class="badge-danger">
                            Ditolak
                        </span>
                    @elseif($item->status_pelaksanaan === 'Sedang Berlangsung')
                        <span class="badge-ongoing">
                            Sedang Berlangsung
                        </span>
                    @elseif($item->status_pelaksanaan === 'Belum Terlaksana')
                        <span class="badge-upcoming">
                            Belum Terlaksana
                        </span>
                    @else
                        <span class="badge-completed">
                            Sudah Terlaksana
                        </span>
                    @endif

                </td>

                <td>{{ $item->status == 'Menunggu' ? '-' : ($item->approver?->name ?? '-') }}</td>

                <td>{{ $item->status === 'Ditolak' ? ($item->rejection_reason ?: '-') : '-' }}</td>

                <td>
                    @if($item->status === 'Disetujui')
                        <a href="{{ route('complaints.create', ['agenda_id' => $item->id]) }}" class="btn btn-report-damage">Lapor Kerusakan</a>
                    @else
                        -
                    @endif
                </td>

            </tr>

        @empty

            <tr>

                <td colspan="11" style="text-align:center;">
                    Belum ada agenda.
                </td>

            </tr>

        @endforelse

        </tbody>

    </table>

</div>

@if(method_exists($agenda, 'hasPages') && $agenda->hasPages())
    <div style="margin-top: 20px;">
        {{ $agenda->withQueryString()->links() }}
    </div>
@endif

@endsection
