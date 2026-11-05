@extends('layouts.app')

@section('title', 'Pengaduan Kerusakan')

@section('judul', 'Pengaduan Kerusakan')

@section('sidebar')

<a href="{{ route('dashboard.admin') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('admin.complaints') }}" class="menu-item active">
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

<div class="complaint-neat-page">

    {{-- HEADER --}}
    <div class="complaint-neat-header">
        <div>
            <h2 class="complaint-neat-title">Pengaduan Kerusakan</h2>
            <p class="complaint-neat-subtitle">
                Daftar laporan kerusakan fasilitas dan ruangan di lingkungan sekolah.
            </p>
        </div>
    </div>

    {{-- BANNER INFORMASI ADMIN --}}
    <div class="complaint-info-banner admin">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="16" x2="12" y2="12"/>
            <line x1="12" y1="8" x2="12.01" y2="8"/>
        </svg>
        <span>
            <strong>Informasi Admin:</strong> Seluruh laporan kerusakan dari Guru & Organisasi tercatat di sini. Admin/Waka dapat memantau dan mengirim pengingat WhatsApp kepada PIC ruangan jika diperlukan.
        </span>
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
                            📍 {{ $complaint->room->location ?? 'Lokasi -' }}
                        </span>

                        <span class="complaint-meta-pill">
                            🕒 Dilaporkan: {{ $complaint->reported_at ? $complaint->reported_at->format('d M Y, H:i') : '-' }}
                        </span>

                        @if ($complaint->event)
                            <span class="complaint-meta-pill highlight" title="Terkait agenda peminjaman ruangan">
                                📅 Agenda: {{ $complaint->event->event_name }} ({{ $complaint->event->date?->format('d-m-Y') ?? '-' }})
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
                            👤 <strong>Pelapor:</strong> {{ $complaint->reporter->name ?? '-' }} ({{ $complaint->reporter->email ?? '-' }})
                        </span>
                        <span>
                            🛠️ <strong>Ditangani:</strong>
                            @if ($complaint->handler)
                                <strong style="color: #0f172a;">{{ $complaint->handler->name }}</strong>
                            @else
                                <span style="color: #94a3b8; font-style: italic;">Belum ditangani PIC</span>
                            @endif
                        </span>
                    </div>

                    <div class="complaint-desc-box">
                        <strong style="color: #475569; font-size: 12px; display: block; margin-bottom: 3px;">DESKRIPSI KERUSAKAN:</strong>
                        {{ $complaint->description }}
                    </div>

                    @if ($complaint->repair_note)
                        <div class="complaint-repair-note-box">
                            <div class="complaint-repair-note-header">
                                <span>🔧</span>
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
                        <span class="complaint-thumb-tag">🔍 Perbesar</span>
                    </a>
                @else
                    <div class="complaint-thumb-box" style="display: flex; align-items: center; justify-content: center; width: 95px; height: 95px; color: #94a3b8; font-size: 11px; text-align: center; padding: 6px;">
                        <span>📷 Tanpa Foto</span>
                    </div>
                @endif
            </div>

            {{-- BAWAH: TINDAKAN ADMIN --}}
            @if ($complaint->status_repair === 'Belum Diperbaiki')
                <div class="complaint-neat-footer">
                    <span style="font-size: 12.5px; color: #64748b;">
                        PIC Ruangan: <strong>{{ $complaint->room?->pic?->name ?? 'Belum ada PIC' }}</strong>
                    </span>

                    <form action="{{ route('complaint.whatsapp', $complaint->id) }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="complaint-wa-btn" onclick="return confirm('Kirim peringatan WhatsApp kepada PIC ruangan ini?')">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.969.586 1.761.88 2.791.88 3.182 0 5.768-2.587 5.768-5.766.001-3.187-2.575-5.766-5.768-5.766zm9.969 5.766c0 5.514-4.486 10-10 10-1.823 0-3.539-.49-5.031-1.355l-5.969 1.562 1.594-5.823c-.928-1.536-1.455-3.327-1.455-5.245 0-5.514 4.486-10 10-10s10 4.486 10 10z"/>
                            </svg>
                            Kirim WhatsApp ke PIC
                        </button>
                    </form>
                </div>
            @endif

        </div>
    @empty
        <div class="alert" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; text-align: center; color: #64748b;">
            <p style="margin: 0; font-size: 15px;">Belum ada pengaduan kerusakan yang masuk.</p>
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
