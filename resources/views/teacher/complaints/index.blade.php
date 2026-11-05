@extends('layouts.app')

@section('title', 'Pengaduan Saya')

@section('judul', 'Pengaduan Kerusakan Saya')

@section('sidebar')

<a href="{{ route('dashboard.guru') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('guru.agenda') }}" class="menu-item">
    Agenda Saya
</a>

<a href="{{ route('complaints.index') }}" class="menu-item active">
    Pengaduan Saya
</a>

<a href="{{ route('complaints.create') }}" class="menu-item">
    Ajukan Pengaduan
</a>

<form method="POST" action="{{ route('logout') }}">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection


@section('content')

<h2>Pengaduan Saya</h2>

<p>
    Berikut adalah daftar pengaduan kerusakan yang telah Anda kirim.
</p>


{{-- =====================================================
     PESAN BERHASIL
===================================================== --}}

@if (session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif


{{-- =====================================================
     PESAN ERROR
===================================================== --}}

@if ($errors->any())

    <div class="alert alert-danger">

        @foreach ($errors->all() as $error)

            <div>
                {{ $error }}
            </div>

        @endforeach

    </div>

@endif


{{-- =====================================================
     TOMBOL AJUKAN PENGADUAN
===================================================== --}}

<div style="margin-bottom: 20px;">

    <a
        href="{{ route('complaints.create') }}"
        class="btn-login"
        style="text-decoration: none; display: inline-block;"
    >
        + Ajukan Pengaduan
    </a>

</div>


{{-- =====================================================
     DAFTAR PENGADUAN
===================================================== --}}

@forelse ($complaints as $complaint)

    <div class="complaint-card">

        {{-- =================================================
             RUANGAN
        ================================================== --}}

        <h3>
            {{ $complaint->room->room_name ?? 'Ruangan tidak ditemukan' }}
        </h3>


        @if ($complaint->room)

            <p>
                <strong>Lokasi:</strong>
                {{ $complaint->room->location ?? '-' }}
            </p>

        @endif


        {{-- =================================================
             DESKRIPSI
        ================================================== --}}

        <p>
            <strong>Deskripsi Kerusakan:</strong>
        </p>

        <p>
            {{ $complaint->description }}
        </p>


        {{-- =================================================
             STATUS
        ================================================== --}}

        <p>
            <strong>Status Perbaikan:</strong>

            @if ($complaint->status_repair === 'Belum Diperbaiki')

                <span class="badge-danger">
                    Belum Diperbaiki
                </span>

            @elseif ($complaint->status_repair === 'Sedang Diperbaiki')

                <span class="badge-warning">
                    Sedang Diperbaiki
                </span>

            @elseif ($complaint->status_repair === 'Sudah Diperbaiki')

                <span class="badge-success">
                    Sudah Diperbaiki
                </span>

            @else

                <span class="status-badge">
                    {{ $complaint->status_repair }}
                </span>

            @endif

        </p>


        {{-- =================================================
             FOTO KERUSAKAN
        ================================================== --}}

        <p>
            <strong>Foto Kerusakan:</strong>
        </p>

        @if ($complaint->photo_path)

            <div style="margin: 15px 0;">

                <img
                    src="{{ asset('storage/' . $complaint->photo_path) }}"
                    alt="Foto kerusakan"
                    style="
                        width: 300px;
                        max-width: 100%;
                        max-height: 300px;
                        object-fit: cover;
                        border-radius: 10px;
                        display: block;
                    "
                >

            </div>

        @else

            <p>
                Tidak ada foto.
            </p>

        @endif


        {{-- =================================================
             PIC YANG MENANGANI
        ================================================== --}}

        @if ($complaint->handler)

            <p>
                <strong>Ditangani oleh:</strong>
                {{ $complaint->handler->name }}
            </p>

        @else

            <p>
                <strong>Ditangani oleh:</strong>
                Belum ada PIC
            </p>

        @endif


        {{-- =================================================
             TANGGAL LAPORAN
        ================================================== --}}

        <p>
            <strong>Dilaporkan:</strong>

            {{ $complaint->reported_at
                ? $complaint->reported_at->format('d-m-Y H:i')
                : '-' }}
        </p>

    </div>

@empty

    <div class="alert">

        <p>
            Belum ada pengaduan kerusakan.
        </p>

        <a
            href="{{ route('complaints.create') }}"
            class="btn-login"
            style="text-decoration: none; display: inline-block;"
        >
            + Buat Pengaduan
        </a>

    </div>

@endforelse


{{-- =====================================================
     PAGINATION
===================================================== --}}

@if ($complaints->hasPages())

    <div style="margin-top: 20px;">
        {{ $complaints->links() }}
    </div>

@endif

@endsection