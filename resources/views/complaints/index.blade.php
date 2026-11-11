@extends('layouts.app')

@section('title', 'Pengaduan Saya')

@section('judul', 'Pengaduan Kerusakan')

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

<div class="complaint-neat-page">

    {{-- HEADER --}}
    <div class="complaint-neat-header">
        <div>
            <h2 class="complaint-neat-title">Pengaduan Kerusakan Saya</h2>
            <p class="complaint-neat-subtitle">
                Pantau progres dan status penanganan laporan kerusakan fasilitas yang Anda ajukan.
            </p>
        </div>

        <a href="{{ route('complaints.create') }}" class="complaint-header-btn">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Buat Pengaduan Baru
        </a>
    </div>

    {{-- PESAN NOTIFIKASI --}}
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- DAFTAR KARTU PENGADUAN --}}
    @forelse ($complaints as $complaint)
        <div class="complaint-neat-card">

            {{-- ATAS: RUANGAN, LOKASI, TANGGAL & STATUS --}}
            <div class="complaint-neat-top">
                <div>
                    <h3 class="complaint-room-name">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <line x1="9" y1="3" x2="9" y2="21"/>
                        </svg>
                        {{ $complaint->room->room_name ?? 'Ruangan Tidak Ditemukan' }}
                    </h3>

                    <div class="complaint-meta-row">
                        <span class="complaint-meta-pill">
                            {{ $complaint->room->location ?? 'Lokasi -' }}
                        </span>

                        <span class="complaint-meta-pill">
                            Waktu Lapor: {{ $complaint->reported_at ? $complaint->reported_at->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') : '-' }}
                        </span>

                        @if ($complaint->event)
                            <span class="complaint-meta-pill highlight">
                                Terkait Agenda: {{ $complaint->event->event_name }} ({{ $complaint->event->date?->format('d-m-Y') ?? '-' }})
                            </span>
                        @endif
                    </div>
                </div>

                <div>
                    @if ($complaint->status_repair === 'Belum Diperbaiki')
                        <span class="complaint-status-pill belum">
                            <span class="dot"></span> Belum Diperbaiki
                        </span>
                    @elseif ($complaint->status_repair === 'Menunggu Spare Part')
                        <span class="complaint-status-pill proses">
                            <span class="dot"></span> Menunggu Spare Part
                        </span>
                    @elseif ($complaint->status_repair === 'Sudah Diperbaiki')
                        <span class="complaint-status-pill selesai">
                            <span class="dot"></span> Sudah Diperbaiki
                        </span>
                    @else
                        <span class="complaint-status-pill">
                            {{ $complaint->status_repair }}
                        </span>
                    @endif
                </div>
            </div>

            {{-- TENGAH: ISI LAPORAN & FOTO --}}
            <div class="complaint-neat-body">
                <div class="complaint-neat-content">
                    <div class="complaint-meta-bar">
                        <span>
                            <strong>Petugas Penanganan:</strong>
                            @if ($complaint->handler)
                                <strong style="color: #0f172a;">{{ $complaint->handler->name }}</strong>
                            @else
                                <span style="color: #94a3b8; font-style: italic;">Menunggu tindak lanjut PIC ruangan</span>
                            @endif
                        </span>

                        @if ($complaint->repaired_at)
                            <span style="color: #16a34a;">
                                <strong>Selesai pada:</strong> {{ $complaint->repaired_at->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') }}
                            </span>
                        @endif
                    </div>

                    <div class="complaint-desc-box">
                        <strong style="color: #475569; font-size: 12px; display: block; margin-bottom: 3px;">DESKRIPSI KERUSAKAN:</strong>
                        {{ $complaint->description }}
                    </div>

                    @if ($complaint->repair_note)
                        <div class="complaint-repair-note-box">
                            <div class="complaint-repair-note-header">
                                <strong>Keterangan Perbaikan dari PIC:</strong>
                            </div>
                            <div class="complaint-repair-note-text">
                                {{ $complaint->repair_note }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- THUMBNAIL FOTO --}}
                @if ($complaint->photo_path)
                    <a href="{{ asset('storage/' . $complaint->photo_path) }}" target="_blank" rel="noopener noreferrer" class="complaint-thumb-box" title="Klik untuk melihat foto ukuran penuh">
                        <img src="{{ asset('storage/' . $complaint->photo_path) }}" alt="Foto Kerusakan" class="complaint-thumb-img">
                        <span class="complaint-thumb-tag">Perbesar</span>
                    </a>
                @else
                    <div class="complaint-thumb-box" style="display: flex; align-items: center; justify-content: center; width: 95px; height: 95px; color: #94a3b8; font-size: 11px; text-align: center; padding: 6px;">
                        <span>Tanpa Foto</span>
                    </div>
                @endif
            </div>

            {{-- BAWAH: STATUS PENJELASAN --}}
            <div class="complaint-neat-footer">
                @if ($complaint->status_repair === 'Sudah Diperbaiki')
                    <span style="font-size: 12.5px; color: #16a34a; font-weight: 500;">
                        Fasilitas ini telah selesai diperbaiki dan siap digunakan kembali.
                    </span>
                @elseif ($complaint->status_repair === 'Menunggu Spare Part')
                    <span style="font-size: 12.5px; color: #d97706; font-weight: 500;">
                        Menunggu suku cadang / peralatan perbaikan dari tim sarana prasarana.
                    </span>
                @else
                    <span style="font-size: 12.5px; color: #64748b;">
                        Laporan telah diterima oleh sistem dan menunggu penanganan dari PIC ruangan ({{ $complaint->room?->pic?->name ?? 'PIC' }}).
                    </span>
                @endif
            </div>

        </div>
    @empty
        <div class="alert" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 32px 20px; text-align: center; color: #64748b;">
            <h4 style="margin: 0 0 6px 0; color: #1e293b; font-size: 16px;">Belum Ada Pengaduan</h4>
            <p style="margin: 0 0 16px 0; font-size: 13.5px;">Anda belum pernah mengirim laporan kerusakan ruangan.</p>
            <a href="{{ route('complaints.create') }}" class="complaint-header-btn" style="margin: 0 auto;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Ajukan Pengaduan Sekarang
            </a>
        </div>
    @endforelse

    {{-- PAGINATION --}}
    @if ($complaints->hasPages())
        <div class="complaint-pagination-wrapper">
            <div class="complaint-pagination-info">
                Menampilkan <strong>{{ $complaints->firstItem() }}</strong> - <strong>{{ $complaints->lastItem() }}</strong> dari <strong>{{ $complaints->total() }}</strong> pengaduan
            </div>
            <div>
                {{ $complaints->links() }}
            </div>
        </div>
    @endif

</div>

@endsection
