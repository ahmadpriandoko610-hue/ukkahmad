@extends('layouts.app')

@section('title', 'Dashboard PIC Ruang')

@section('judul', 'Dashboard PIC Ruang')

@section('content')
<div class="dash-wrapper">

    {{-- ========================================================= --}}
    {{-- 1. HERO BANNER PIC --}}
    {{-- ========================================================= --}}
    <div class="dash-hero">
        <div class="dash-hero-content">
            <h1 class="dash-hero-title">
                Selamat Datang, {{ Auth::user()->name }}
            </h1>
            <p class="dash-hero-subtitle">
                Pantau ruangan tanggung jawab Anda, verifikasi permohonan agenda guru secara cepat, dan tangani pengaduan kerusakan fasilitas agar tetap prima.
            </p>
        </div>

        <div class="dash-hero-side">
            <div class="dash-hero-date">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span>{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}</span>
            </div>

            <a href="{{ route('pic.events') }}" class="dash-hero-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 11 12 14 22 4"/>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                Verifikasi Agenda Masuk
            </a>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- 2. METRIC STAT CARDS --}}
    {{-- ========================================================= --}}
    <div class="dash-stats-grid">

        {{-- AGENDA MASUK --}}
        <a href="{{ route('pic.events') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon amber">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <span class="dash-stat-badge amber">Perlu Tindakan</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $agendaMasuk }}</div>
                <div class="dash-stat-label">Agenda Menunggu</div>
            </div>
            <div class="dash-stat-footer">
                <span>Perlu diverifikasi</span>
                <span class="dash-stat-link">
                    Tinjau Agenda
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- DISETUJUI --}}
        <a href="{{ route('pic.calendar') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon emerald">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <span class="dash-stat-badge emerald">Terkonfirmasi</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $disetujui }}</div>
                <div class="dash-stat-label">Agenda Disetujui</div>
            </div>
            <div class="dash-stat-footer">
                <span>Aktif di ruangan Anda</span>
                <span class="dash-stat-link">
                    Buka Kalender
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- DITOLAK --}}
        <a href="{{ route('pic.events') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon purple">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="15" y1="9" x2="9" y2="15"/>
                        <line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                <span class="dash-stat-badge purple">Arsip</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $ditolak }}</div>
                <div class="dash-stat-label">Agenda Ditolak</div>
            </div>
            <div class="dash-stat-footer">
                <span>Pengajuan tidak lolos</span>
                <span class="dash-stat-link">
                    Riwayat
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- PENGADUAN PERLU DITANGANI --}}
        <a href="{{ route('pic.complaints') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon rose">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                    </svg>
                </div>
                <span class="dash-stat-badge rose">{{ $pengaduanBelum }} Baru</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $pengaduanBelum + $pengaduanProses }}</div>
                <div class="dash-stat-label">Laporan Kerusakan</div>
            </div>
            <div class="dash-stat-footer">
                <span>{{ $pengaduanSelesai }} Selesai diperbaiki</span>
                <span class="dash-stat-link">
                    Kelola Laporan
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

    </div>

    {{-- ========================================================= --}}
    {{-- 3. RUANGAN TANGGUNG JAWAB PIC --}}
    {{-- ========================================================= --}}
    <div class="dash-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title">
                <span class="dash-card-title-icon" style="background: #ecfdf5; color: #059669;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"/>
                        <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                        <line x1="9" y1="9" x2="10" y2="9"/>
                        <line x1="9" y1="13" x2="10" y2="13"/>
                        <line x1="14" y1="9" x2="15" y2="9"/>
                        <line x1="14" y1="13" x2="15" y2="13"/>
                    </svg>
                </span>
                Ruangan Tanggung Jawab Saya ({{ $ruanganSaya->count() }})
            </h3>
            <a href="{{ route('pic.schedule') }}" class="dash-card-action">
                Cek Jadwal Semua Ruang
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>

        <div class="dash-card-body">
            @if($ruanganSaya->count() > 0)
                <div class="dash-room-grid">
                    @foreach($ruanganSaya as $room)
                        <div class="dash-room-card">
                            <div>
                                <div class="dash-room-header">
                                    <h4 class="dash-room-name">{{ $room->room_name }}</h4>
                                </div>

                                <div class="dash-room-info">
                                    <span>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                            <circle cx="12" cy="10" r="3"/>
                                        </svg>
                                        {{ $room->location ?? 'Lokasi tidak diset' }}
                                    </span>
                                    <span>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                            <circle cx="9" cy="7" r="4"/>
                                        </svg>
                                        Kapasitas: <strong>{{ $room->capacity ?? '-' }} Orang</strong>
                                    </span>
                                </div>
                            </div>

                            <div style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                                <a href="{{ route('pic.schedule') }}" style="display: inline-flex; align-items: center; gap: 4px; font-size: 12.5px; font-weight: 600; color: #2563eb; text-decoration: none;">
                                    Buka Jadwal Ruang &rarr;
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="dash-empty">
                    <div class="dash-empty-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 21h18"/>
                            <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                        </svg>
                    </div>
                    <p class="dash-empty-title">Belum Ada Ruangan Ditugaskan</p>
                    <p class="dash-empty-desc">Saat ini belum ada ruangan yang terhubung dengan akun Anda. Silakan hubungi Administrator untuk penugasan ruangan.</p>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
