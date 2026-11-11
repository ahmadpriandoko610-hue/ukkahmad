@extends('layouts.app')

@section('title', 'Manage Rooms')

@section('judul', 'Kelola Ruangan')

@section('sidebar')

<a href="{{ route('dashboard.admin') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('users.index') }}" class="menu-item">
    Kelola User
</a>

<a href="{{ route('rooms.index') }}" class="menu-item active">
    Kelola Ruangan
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection


@section('content')

<h2>Daftar Ruangan</h2>


{{-- PESAN SUKSES --}}

@if (session('success'))

    <div class="alert-success">
        {{ session('success') }}
    </div>

@endif

@if (session('error'))

    <div class="alert-danger">
        {{ session('error') }}
    </div>

@endif


{{-- TOMBOL TAMBAH RUANGAN --}}

<div class="page-action-bar">

    <a
        href="{{ route('rooms.create') }}"
        class="btn-login page-primary-btn"
    >
        + Tambah Ruangan
    </a>

</div>

<form action="{{ route('rooms.index') }}" method="GET" class="filter-bar-form">
    <input
        type="search"
        name="search"
        value="{{ $search }}"
        class="form-control"
        placeholder="Cari nama ruangan, lokasi, atau PIC..."
        aria-label="Cari ruangan"
    >
    <div class="filter-actions">
        <button type="submit" class="btn btn-edit">Cari</button>
        @if ($search !== '')
            <a href="{{ route('rooms.index') }}" class="btn btn-back">Reset</a>
        @endif
    </div>
</form>


{{-- TABEL RUANGAN --}}

<div class="agenda-table-wrapper">

<table class="agenda-table data-table-medium">

    <thead>

        <tr>

            <th>No</th>

            <th>Nama Ruangan</th>

            <th>Lokasi</th>

            <th>Kapasitas</th>

            <th>PIC</th>

            <th>Keterangan</th>

            <th>Aksi</th>

        </tr>

    </thead>


    <tbody>

        @forelse ($rooms as $room)

            <tr>

                {{-- NO --}}

                <td>
                    {{ method_exists($rooms, 'firstItem') && $rooms->firstItem() ? ($rooms->firstItem() + $loop->index) : $loop->iteration }}
                </td>


                {{-- NAMA RUANGAN --}}

                <td>
                    {{ $room->room_name }}
                </td>


                {{-- LOKASI --}}

                <td>
                    {{ $room->location }}
                </td>


                {{-- KAPASITAS --}}

                <td>
                    {{ $room->capacity }}
                </td>


                {{-- PIC --}}

                <td>

                    @if ($room->pic)

                        {{ $room->pic->name }}

                    @else

                        <span class="text-muted">
                            Belum ada PIC
                        </span>

                    @endif

                </td>


                {{-- KETERANGAN --}}

                <td>
                    {{ $room->description ?? '-' }}
                </td>


                {{-- AKSI --}}

                <td class="room-actions-cell">

                    <div class="room-actions">

                    <a
                        href="{{ route('rooms.edit', $room->id) }}"
                        class="btn btn-edit"
                    >
                        Edit
                    </a>


                    <form
                        action="{{ route('rooms.destroy', $room->id) }}"
                        method="POST"
                        class="room-delete-form"
                    >

                        @csrf

                        @method('DELETE')

                        <button
                            type="submit"
                            class="btn btn-delete"
                            onclick="return confirm('Yakin ingin menghapus data ruangan ini?')"
                        >
                            Hapus
                        </button>

                    </form>

                    </div>

                </td>

            </tr>

        @empty

            <tr>

                <td
                    colspan="7"
                    style="text-align: center;"
                >
                    {{ $search !== '' ? 'Ruangan tidak ditemukan.' : 'Belum ada data ruangan.' }}
                </td>

            </tr>

        @endforelse

    </tbody>

</table>

</div>

@if(method_exists($rooms, 'hasPages') && $rooms->hasPages())
<div style="margin-top:20px;">
    {{ $rooms->withQueryString()->links() }}
</div>
@endif

@endsection
