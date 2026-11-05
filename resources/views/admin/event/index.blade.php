@extends('layouts.app')

@section('title', 'Kelola Agenda')

@section('judul', 'Kelola Agenda')

@section('sidebar')

<a href="{{ route('dashboard.admin') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('users.index') }}" class="menu-item">
    Kelola User
</a>

<a href="{{ route('ruangan.index') }}" class="menu-item">
    Kelola Ruangan
</a>

<a href="{{ route('agenda.index') }}" class="menu-item active">
    Kelola Agenda
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection


@section('content')

<h2>Daftar Agenda</h2>

@if(session('success'))
<div class="alert-success">
    {{ session('success') }}
</div>
@endif

@if(session('error'))
<div class="alert-danger">
    {{ session('error') }}
</div>
@endif


<div style="margin:20px 0;">

    <a href="{{ route('agenda.create') }}"
       class="btn-login"
       style="display:inline-block;width:auto;padding:10px 20px;text-decoration:none;">

        + Tambah Agenda

    </a>

</div>


<div class="agenda-table-wrapper">

<table class="agenda-table">

    <thead>

        <tr>
            <th>No</th>
            <th>Judul</th>
            <th>Tanggal</th>
            <th>Sesi</th>
            <th>Ruangan</th>
            <th>Pengaju</th>
            <th>Status</th>
            <th>Disetujui</th>
            <th>Aksi</th>
        </tr>

    </thead>


    <tbody>

    @forelse($agenda as $item)

        <tr>

            <td>
                {{ $loop->iteration }}
            </td>

            <td>
                {{ $item->judul }}
            </td>

            <td>
                {{ $item->formatted_date_range }}
            </td>

            <td>
                {{ $item->session_range }}
            </td>

            <td>
                {{ $item->ruangan->nama_ruangan }}
            </td>

            <td>
                {{ $item->user->name }}
            </td>

            <td>
                @if($item->status_pelaksanaan === 'Sedang Berlangsung')
                    <span class="badge-ongoing">Sedang Berlangsung</span>
                @elseif($item->status_pelaksanaan === 'Belum Terlaksana')
                    <span class="badge-upcoming">Belum Terlaksana</span>
                @elseif($item->status_pelaksanaan === 'Sudah Terlaksana')
                    <span class="badge-completed">Sudah Terlaksana</span>
                @elseif($item->status === 'Menunggu')
                    <span class="badge-warning">Menunggu</span>
                @else
                    <span class="badge-danger">{{ $item->status }}</span>
                @endif
            </td>

            <td>{{ $item->status === 'Menunggu' ? '-' : ($item->approver?->name ?? '-') }}</td>


            <td class="agenda-action-cell">

                <div class="agenda-actions">

                    @if($item->status === 'Menunggu')
                        <form action="{{ route('admin.agenda.approve', $item) }}" method="POST">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-edit"
                                onclick="return confirm('Setujui agenda ini?')"
                            >
                                Setujui
                            </button>
                        </form>
                        <form action="{{ route('agenda.cancel', $item->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                class="btn btn-delete"
                                onclick="return confirm('Yakin ingin membatalkan agenda ini?')"
                            >
                                Batal
                            </button>
                        </form>
                    @elseif($item->status === 'Ditolak')
                        <form
                            action="{{ route('agenda.destroy', $item->id) }}"
                            method="POST"
                            class="agenda-delete-form"
                        >
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="btn btn-delete"
                                onclick="return confirm('Yakin ingin menghapus agenda ini?')"
                            >
                                Hapus
                            </button>
                        </form>

                    @else

                    @if($item->status === 'Disetujui')
                    <a href="{{ route('admin.complaints.create', $item) }}" class="btn btn-edit" style="grid-column:1/-1">
                        Buat Pengaduan
                    </a>
                    @endif


                    {{-- EDIT --}}

                    <a href="{{ route('agenda.edit', $item->id) }}"
                       class="btn btn-edit agenda-edit-button">

                        Edit

                    </a>


                    {{-- PINDAH RUANGAN --}}

                    <a href="{{ route('events.move.form', $item->id) }}"
                       class="btn btn-edit agenda-move-button">

                        Pindah Ruangan

                    </a>
                    {{-- HAPUS --}}

                    <form
                        action="{{ route('agenda.destroy', $item->id) }}"
                        method="POST"
                        class="agenda-delete-form"
                    >

                        @csrf

                        @method('DELETE')

                        <button
                            type="submit"
                            class="btn btn-delete"
                            onclick="return confirm('Yakin ingin menghapus agenda ini?')"
                        >

                            Hapus

                        </button>

                    </form>

                    @endif


                </div>

            </td>

        </tr>

    @empty

        <tr>

            <td colspan="9" style="text-align:center;">

                Belum ada data agenda.

            </td>

        </tr>

    @endforelse

    </tbody>

</table>

</div>


<div style="margin-top:20px;">

    {{ $agenda->links() }}

</div>

@endsection
