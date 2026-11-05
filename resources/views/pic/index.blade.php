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

@if(session('success'))
<div class="alert-success">
    {{ session('success') }}
</div>
@endif

@if($errors->any())
<div class="alert-danger">
    {{ $errors->first('rejection_reason') }}
</div>
@endif

<table>

    <thead>

        <tr>
            <th>No</th>
            <th>Judul</th>
            <th>Guru</th>
            <th>Ruangan</th>
            <th>Tanggal</th>
            <th>Status</th>
            <th>Disetujui</th>
            <th width="220">Aksi</th>
        </tr>

    </thead>

    <tbody>

    @forelse($agenda as $item)

    <tr>

        <td>{{ $loop->iteration }}</td>

        <td>{{ $item->judul }}</td>

        <td>{{ $item->user->name }}</td>

        <td>{{ $item->ruangan->nama_ruangan }}</td>

        <td>{{ $item->formatted_date_range }}</td>

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

        <td>

            @if($item->status == 'Menunggu')

            <form action="{{ route('agenda.setujui',$item->id) }}"
                  method="POST"
                  style="display:inline-block">

                @csrf

                <button class="btn btn-edit">
                    Setujui
                </button>

            </form>

            <a href="{{ route('agenda.tolak.form', $item) }}" class="btn btn-delete">Tolak</a>

            @else

                -

            @endif

        </td>

    </tr>

    @empty

    <tr>

        <td colspan="8" style="text-align:center;">
            Belum ada agenda.
        </td>

    </tr>

    @endforelse

    </tbody>

</table>

<div style="margin-top:20px;">
    {{ $agenda->links() }}
</div>

@endsection
