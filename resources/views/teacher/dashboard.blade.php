@extends('layouts.app')

@section('title', 'Dashboard Guru')

@section('judul', 'Dashboard Guru')

@section('content')
<div class="dash-wrapper">

    {{-- ========================================================= --}}
    {{-- 1. HERO BANNER GURU --}}
    {{-- ========================================================= --}}
    <div class="dash-hero">
        <div class="dash-hero-content">
            <h1 class="dash-hero-title">
                Selamat Datang, {{ Auth::user()->name }}
            </h1>
            <p class="dash-hero-subtitle">
                Ajukan peminjaman ruangan dengan praktis, pantau status persetujuan kegiatan Anda, dan laporkan sarana atau prasarana sekolah yang perlu perbaikan.
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

            <a href="{{ route('guru.events.create') }}" class="dash-hero-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Ajukan Agenda Baru
            </a>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- 2. METRIC STAT CARDS --}}
    {{-- ========================================================= --}}
    <div class="dash-stats-grid">

        {{-- TOTAL AGENDA SAYA --}}
        <a href="{{ route('guru.agenda') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
                <span class="dash-stat-badge blue">Pengajuan</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $agendaSaya }}</div>
                <div class="dash-stat-label">Agenda Saya</div>
            </div>
            <div class="dash-stat-footer">
                <span>Seluruh agenda aktif</span>
                <span class="dash-stat-link">
                    Buka Agenda
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- DISETUJUI --}}
        <a href="{{ route('guru.calendar') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon emerald">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                </div>
                <span class="dash-stat-badge emerald">Siap Pakai</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $disetujui }}</div>
                <div class="dash-stat-label">Disetujui PIC</div>
            </div>
            <div class="dash-stat-footer">
                <span>Terkonfirmasi</span>
                <span class="dash-stat-link">
                    Buka Kalender
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- MENUNGGU VERIFIKASI --}}
        <a href="{{ route('guru.agenda') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon amber">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <span class="dash-stat-badge amber">Proses Review</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $menunggu }}</div>
                <div class="dash-stat-label">Menunggu Verifikasi</div>
            </div>
            <div class="dash-stat-footer">
                <span>Sedang ditinjau PIC</span>
                <span class="dash-stat-link">
                    Cek Status
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- PENGADUAN KERUSAKAN SAYA --}}
        <a href="{{ route('complaints.index') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon rose">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                    </svg>
                </div>
                <span class="dash-stat-badge rose">Fasilitas</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $totalPengaduanSaya }}</div>
                <div class="dash-stat-label">Laporan Fasilitas</div>
            </div>
            <div class="dash-stat-footer">
                <span>Pengaduan kerusakan</span>
                <span class="dash-stat-link">
                    Lihat Laporan
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

    </div>

    {{-- ========================================================= --}}
    {{-- 3. TWO-COLUMN GRID: RECENT GURU AGENDAS & STEP GUIDE --}}
    {{-- ========================================================= --}}
    <div class="dash-grid-2">

        {{-- LEFT COLUMN: AGENDA SAYA TERKINI --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <h3 class="dash-card-title">
                    <span class="dash-card-title-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </span>
                    Pengajuan Agenda Terakhir Saya
                </h3>
                <a href="{{ route('guru.agenda') }}" class="dash-card-action">
                    Semua Agenda
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </a>
            </div>

            <div class="dash-card-body p-0">
                @if(isset($agendaTerbaru) && $agendaTerbaru->count() > 0)
                    <div class="dash-table-responsive">
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th>Agenda</th>
                                    <th>Ruangan</th>
                                    <th>Tanggal & Waktu</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($agendaTerbaru as $item)
                                    <tr>
                                        <td>
                                            <strong style="color: #0f172a;">{{ $item->event_name }}</strong>
                                        </td>
                                        <td>
                                            <span style="display: inline-flex; align-items: center; gap: 4px; color: #475569;">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M3 21h18"/>
                                                    <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                                                </svg>
                                                {{ $item->room->room_name ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 12.5px; color: #334155;">
                                                @if($item->end_date && $item->end_date->gt($item->date))
                                                    <span style="font-weight:600;">{{ \Carbon\Carbon::parse($item->date)->translatedFormat('d M') }} - {{ \Carbon\Carbon::parse($item->end_date)->translatedFormat('d M Y') }}</span>
                                                @else
                                                    {{ \Carbon\Carbon::parse($item->date)->translatedFormat('d M Y') }}
                                                @endif
                                            </div>
                                            <small style="color: #94a3b8;">
                                                {{ substr($item->start_time, 0, 5) }} - {{ substr($item->end_time, 0, 5) }}
                                            </small>
                                        </td>
                                        <td>
                                            @if($item->status_pelaksanaan === 'Sedang Berlangsung')
                                                <span class="dash-badge ongoing" title="Sedang berlangsung saat ini">
                                                    <span class="dash-badge-dot"></span> Sedang Berlangsung
                                                </span>
                                            @elseif($item->status_pelaksanaan === 'Belum Terlaksana')
                                                <span class="dash-badge upcoming" title="Agenda belum dimulai">
                                                    <span class="dash-badge-dot"></span> Belum Terlaksana
                                                </span>
                                            @elseif($item->status_pelaksanaan === 'Sudah Terlaksana')
                                                <span class="dash-badge completed" title="Agenda telah selesai">
                                                    <span class="dash-badge-dot"></span> Sudah Terlaksana
                                                </span>
                                            @elseif($item->status === 'Menunggu')
                                                <span class="dash-badge warning">
                                                    <span class="dash-badge-dot"></span> Menunggu
                                                </span>
                                            @else
                                                <span class="dash-badge danger">
                                                    <span class="dash-badge-dot"></span> {{ $item->status }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="dash-empty">
                        <div class="dash-empty-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                        </div>
                        <p class="dash-empty-title">Belum Ada Pengajuan Agenda</p>
                        <p class="dash-empty-desc">Anda belum pernah mengajukan agenda ruangan. Klik tombol di bawah untuk membuat agenda baru.</p>
                        <a href="{{ route('guru.events.create') }}" class="dash-quick-btn primary" style="margin-top: 10px;">
                            + Ajukan Agenda Sekarang
                        </a>
                    </div>
                @endif
            </div>

            <div class="dash-card-footer">
                <small style="color: #64748b;">Pantau persetujuan agenda sebelum kegiatan dimulai</small>
                <a href="{{ route('guru.agenda') }}" style="font-size: 12.5px; font-weight: 600; color: #2563eb; text-decoration: none;">
                    Riwayat Agenda Lengkap &rarr;
                </a>
            </div>
        </div>

        {{-- RIGHT COLUMN: ALUR PEMINJAMAN RUANGAN --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <h3 class="dash-card-title">
                    <span class="dash-card-title-icon" style="background: #eff6ff; color: #2563eb;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                    </span>
                    Alur & Tata Tertib Peminjaman Ruangan
                </h3>
            </div>

            <div class="dash-card-body">
                <div class="dash-steps-grid" style="grid-template-columns: repeat(2, 1fr);">
                    {{-- STEP 1 --}}
                    <div class="dash-step-card">
                        <div class="dash-step-number">1</div>
                        <h4 class="dash-step-title">Cek Ketersediaan</h4>
                        <p class="dash-step-text">
                            Lihat kalender atau menu Jadwal untuk memastikan ruangan belum dipinjam oleh agenda lain di jam yang Anda inginkan.
                        </p>
                    </div>

                    {{-- STEP 2 --}}
                    <div class="dash-step-card">
                        <div class="dash-step-number">2</div>
                        <h4 class="dash-step-title">Ajukan Permohonan</h4>
                        <p class="dash-step-text">
                            Isi formulir peminjaman dengan nama acara, ruangan yang dipilih, tanggal pelaksanaan, serta jam mulai dan selesai.
                        </p>
                    </div>

                    {{-- STEP 3 --}}
                    <div class="dash-step-card">
                        <div class="dash-step-number">3</div>
                        <h4 class="dash-step-title">Verifikasi PIC</h4>
                        <p class="dash-step-text">
                            PIC Ruangan akan meninjau kelayakan dan ketersediaan, lalu menyetujui atau memberikan catatan permohonan Anda.
                        </p>
                    </div>

                    {{-- STEP 4 --}}
                    <div class="dash-step-card">
                        <div class="dash-step-number">4</div>
                        <h4 class="dash-step-title">Jaga Fasilitas</h4>
                        <p class="dash-step-text">
                            Gunakan sarana prasarana dengan tertib. Segera laporkan melalui fitur Pengaduan jika menemukan peralatan yang rusak.
                        </p>
                    </div>
                </div>
            </div>

            <div class="dash-card-footer">
                <small style="color: #64748b;">Peminjaman disarankan dilakukan minimal H-1 kegiatan</small>
                <a href="{{ route('jadwal.index') }}" style="font-size: 12.5px; font-weight: 600; color: #2563eb; text-decoration: none;">
                    Cek Ketersediaan Ruangan &rarr;
                </a>
            </div>
        </div>

    </div>

    {{-- ========================================================= --}}
    {{-- 5. BOTTOM SECTION: PENGADUAN KERUSAKAN OLEH GURU --}}
    {{-- ========================================================= --}}
    <div class="dash-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title">
                <span class="dash-card-title-icon" style="background: #fef2f2; color: #dc2626;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                    </svg>
                </span>
                Pengaduan Kerusakan Sarana yang Anda Laporkan
            </h3>
            <a href="{{ route('complaints.create') }}" class="dash-card-action">
                + Buat Pengaduan Baru
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>

        <div class="dash-card-body p-0">
            @if(isset($pengaduanSaya) && $pengaduanSaya->count() > 0)
                <div class="dash-table-responsive">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Ruangan</th>
                                <th>Deskripsi Kendala</th>
                                <th>Waktu Lapor</th>
                                <th>Status Penanganan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pengaduanSaya as $complaint)
                                <tr>
                                    <td>
                                        <strong style="color: #0f172a;">{{ $complaint->room->room_name ?? '-' }}</strong>
                                    </td>
                                    <td>
                                        <span style="color: #475569;">
                                            {{ \Illuminate\Support\Str::limit($complaint->description, 80) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size: 12.5px; color: #64748b;">
                                            {{ $complaint->created_at->diffForHumans() }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($complaint->status_repair === 'Sudah Diperbaiki')
                                            <span class="dash-badge success">
                                                <span class="dash-badge-dot"></span> Sudah Diperbaiki
                                            </span>
                                        @elseif($complaint->status_repair === 'Menunggu Spare Part')
                                            <span class="dash-badge info">
                                                <span class="dash-badge-dot"></span> Menunggu Spare Part
                                            </span>
                                        @else
                                            <span class="dash-badge danger">
                                                <span class="dash-badge-dot"></span> Belum Diperbaiki
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="dash-empty">
                    <div class="dash-empty-icon" style="background: #ecfdf5; color: #059669;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <p class="dash-empty-title">Tidak Ada Laporan Kerusakan</p>
                    <p class="dash-empty-desc">Anda belum pernah membuat laporan kerusakan fasilitas ruangan.</p>
                </div>
            @endif
        </div>

        <div class="dash-card-footer">
            <small style="color: #64748b;">Bantu sekolah menjaga kualitas fasilitas bersama</small>
            <a href="{{ route('complaints.index') }}" style="font-size: 12.5px; font-weight: 600; color: #2563eb; text-decoration: none;">
                Lihat Semua Pengaduan Saya &rarr;
            </a>
        </div>
    </div>

</div>
@endsection
