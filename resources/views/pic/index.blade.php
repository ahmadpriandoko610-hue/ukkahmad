@extends('layouts.app')

@section('title', 'Verifikasi Agenda')

@section('judul', 'Verifikasi Agenda')

@section('sidebar')

<a href="{{ route('dashboard.pic') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('pic.agenda') }}" class="menu-item active">
    Verifikasi Agenda
</a>

<a href="{{ route('pic.calendar') }}" class="menu-item">
    Kalender Agenda
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection

@section('content')

@php
    $picFilterRoute = request()->routeIs('pic.agenda') ? route('pic.agenda') : route('pic.events');
@endphp

<h2>Daftar Agenda Ruangan</h2>

@if(session('success'))
<div class="alert-success">
    {{ session('success') }}
</div>
@endif

@if(session('warning'))
<div class="alert-warning">
    {{ session('warning') }}
</div>
@endif

@if(session('error'))
<div class="alert-danger">
    {{ session('error') }}
</div>
@endif

@if($errors->any())
<div class="alert-danger">
    @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

<form action="{{ $picFilterRoute }}" method="GET" class="date-filter-form">
    <div class="filter-field filter-field-search">
        <label for="pic_agenda_search">Pencarian</label>
        <input
            type="search"
            id="pic_agenda_search"
            name="search"
            value="{{ old('search', $filters['search'] ?? request('search')) }}"
            class="form-control"
            placeholder="Cari judul, ruangan, atau guru..."
        >
    </div>

    <div class="filter-field">
        <label for="pic_tanggal_mulai">Tanggal Mulai</label>
        <input
            type="date"
            id="pic_tanggal_mulai"
            name="tanggal_mulai"
            value="{{ old('tanggal_mulai', $filters['tanggal_mulai'] ?? request('tanggal_mulai')) }}"
            class="form-control"
        >
    </div>

    <div class="filter-field">
        <label for="pic_tanggal_selesai">Tanggal Selesai</label>
        <input
            type="date"
            id="pic_tanggal_selesai"
            name="tanggal_selesai"
            value="{{ old('tanggal_selesai', $filters['tanggal_selesai'] ?? request('tanggal_selesai')) }}"
            class="form-control"
        >
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-edit">Terapkan Filter</button>
        <a href="{{ $picFilterRoute }}" class="btn btn-back">Reset Filter</a>
    </div>
</form>

<div class="agenda-table-wrapper">

<table class="agenda-table">

    <thead>

        <tr>
            <th>No</th>
            <th>Judul</th>
            <th>Guru</th>
            <th>Ruangan</th>
            <th>Tanggal</th>
            <th>Waktu Sesi</th>
            <th>Jam Mulai</th>
            <th>Jam Selesai</th>
            <th>Status</th>
            <th>Disetujui</th>
            <th>Aksi</th>
        </tr>

    </thead>

    <tbody>

    @forelse($agenda as $item)

    <tr>

        <td>{{ method_exists($agenda, 'firstItem') && $agenda->firstItem() ? ($agenda->firstItem() + $loop->index) : $loop->iteration }}</td>

        <td>{{ $item->judul }}</td>

        <td>{{ $item->user?->name ?? '-' }}</td>

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

        <td>{{ $item->formatted_date_range }}</td>

        <td>{{ $item->session_range }}</td>

        <td>{{ $item->start_time ? substr((string) $item->start_time, 0, 5) : '-' }}</td>

        <td>{{ $item->end_time ? substr((string) $item->end_time, 0, 5) : '-' }}</td>

        <td>

            @if($item->status == 'Menunggu')
                <span class="badge-warning">
                    Menunggu
                </span>
            @elseif($item->status == 'Ditolak')
                <span class="badge-danger">
                    Ditolak
                </span>
                @if($item->rejection_reason)
                    <div class="rejection-reason-note">
                        <strong>Alasan:</strong> {{ $item->rejection_reason }}
                    </div>
                @endif
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

        <td class="room-actions-cell">

            @if($item->status == 'Menunggu')

            <div class="room-actions">
                <form action="{{ route('agenda.setujui',$item->id) }}"
                      method="POST"
                      class="room-delete-form">

                    @csrf

                    <button type="submit" class="btn btn-edit">
                        Setujui
                    </button>

                </form>

                <a href="{{ route('agenda.tolak.form', $item) }}" class="btn btn-delete">Tolak</a>
            </div>

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

<div style="margin-top:20px;">
    {{ $agenda->withQueryString()->links() }}
</div>

@endsection
