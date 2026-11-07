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

<h2>Agenda Saya</h2>

@if(session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif

@foreach ($notifications as $notification)
    <div class="alert alert-warning" role="alert">
        <strong>{{ $notification->title }}</strong><br>
        {{ $notification->message }}
    </div>
@endforeach


<div class="table-container">

    <table>

        <thead>
            <tr>
                <th>No</th>
                <th>Judul</th>
                <th>Ruangan</th>
                <th>Tanggal</th>
                <th>Sesi</th>
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
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            @foreach($item->rooms as $r)
                                <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; display: inline-block;">
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

                <td colspan="9" style="text-align:center;">
                    Belum ada agenda.
                </td>

            </tr>

        @endforelse

        </tbody>

    </table>

</div>

@if(method_exists($agenda, 'hasPages') && $agenda->hasPages())
    <div style="margin-top: 20px;">
        {{ $agenda->links() }}
    </div>
@endif

@endsection
