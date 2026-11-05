@extends('layouts.app')

@section('title', 'Jadwal Ruangan')

@section('judul', 'Jadwal Ruangan')

@section('sidebar')

<a href="{{ route('dashboard.pic') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('pic.agenda') }}" class="menu-item">
    Verifikasi Agenda
</a>

<a href="{{ route('pic.calendar') }}" class="menu-item">
    Kalender Agenda
</a>

<a href="{{ route('pic.complaints') }}" class="menu-item">
    Pengaduan Kerusakan
</a>

<a href="{{ route('pic.schedule') }}" class="menu-item active">
    Jadwal Ruangan
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection


@section('content')

<p>
    Berikut adalah daftar agenda yang telah disetujui.
</p>


@if ($agenda->count() > 0)

<div class="table-container">

    <table class="data-table">

        <thead>
            <tr>
                <th>No</th>
                <th>Agenda</th>
                <th>Tanggal</th>
                <th>Sesi</th>
                <th>Ruangan</th>
                <th>Lokasi</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

            @foreach ($agenda as $index => $event)

            <tr>

                <td>
                    {{ $index + 1 }}
                </td>

                <td>
                    {{ $event->event_name }}
                </td>

                <td>
                    {{ $event->formatted_date_range }}
                </td>

                <td>
                    {{ $event->session_range }}
                </td>

                <td>
                    {{ $event->room?->room_name ?? '-' }}
                </td>

                <td>
                    {{ $event->room?->location ?? '-' }}
                </td>

                <td>
                    @if($event->status_pelaksanaan === 'Sedang Berlangsung')
                        <span class="badge-ongoing">Sedang Berlangsung</span>
                    @elseif($event->status_pelaksanaan === 'Belum Terlaksana')
                        <span class="badge-upcoming">Belum Terlaksana</span>
                    @else
                        <span class="badge-completed">Sudah Terlaksana</span>
                    @endif
                </td>

            </tr>

            @endforeach

        </tbody>

    </table>

</div>

@else

<div class="alert">
    Belum ada agenda yang disetujui.
</div>

@endif

@endsection
