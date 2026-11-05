@extends('layouts.app')

@section('title', 'Dashboard Administrator')

@section('judul', 'Dashboard Administrator')

@section('content')
<div class="dash-wrapper">

    {{-- ========================================================= --}}
    {{-- 1. HERO WELCOME BANNER --}}
    {{-- ========================================================= --}}
    <div class="dash-hero">
        <div class="dash-hero-content">
            <h1 class="dash-hero-title">
                Selamat Datang, {{ Auth::user()->name }}
            </h1>
            <p class="dash-hero-subtitle">
                Pantau ketersediaan ruangan, verifikasi agenda kegiatan sekolah, kelola data pengguna, serta tindak lanjuti laporan sarana prasarana secara terpadu.
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

            <a href="{{ route('events.create') }}" class="dash-hero-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Buat Agenda Baru
            </a>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- 2. METRIC STAT CARDS --}}
    {{-- ========================================================= --}}
    <div class="dash-stats-grid">

        {{-- USER STAT CARD --}}
        <a href="{{ route('users.index') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <span class="dash-stat-badge blue">Sistem</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $jumlahUser }}</div>
                <div class="dash-stat-label">Total Pengguna</div>
            </div>
            <div class="dash-stat-footer">
                <span>Admin, Guru & PIC</span>
                <span class="dash-stat-link">
                    Kelola User
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- RUANGAN STAT CARD --}}
        <a href="{{ route('rooms.index') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon emerald">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"/>
                        <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"/>
                        <path d="M9 9h1"/>
                        <path d="M9 13h1"/>
                        <path d="M9 17h1"/>
                        <path d="M14 9h1"/>
                        <path d="M14 13h1"/>
                        <path d="M14 17h1"/>
                    </svg>
                </div>
                <span class="dash-stat-badge emerald">Sarana</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $jumlahRuangan }}</div>
                <div class="dash-stat-label">Ruangan Terdaftar</div>
            </div>
            <div class="dash-stat-footer">
                <span>Fasilitas Sekolah</span>
                <span class="dash-stat-link">
                    Lihat Ruangan
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- AGENDA STAT CARD --}}
        <a href="{{ route('events.index') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon amber">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <span class="dash-stat-badge amber">{{ $agendaMenunggu }} Menunggu</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $jumlahAgenda }}</div>
                <div class="dash-stat-label">Agenda Aktif</div>
            </div>
            <div class="dash-stat-footer">
                <span>{{ $agendaDisetujui }} Disetujui</span>
                <span class="dash-stat-link">
                    Daftar Agenda
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

        {{-- PENGADUAN STAT CARD --}}
        <a href="{{ route('admin.complaints') }}" class="dash-stat-card">
            <div class="dash-stat-top">
                <div class="dash-stat-icon rose">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
                <span class="dash-stat-badge rose">{{ $pengaduanBelum }} Belum Selesai</span>
            </div>
            <div>
                <div class="dash-stat-value">{{ $jumlahPengaduan }}</div>
                <div class="dash-stat-label">Pengaduan Kerusakan</div>
            </div>
            <div class="dash-stat-footer">
                <span>Laporan Sarpras</span>
                <span class="dash-stat-link">
                    Tindak Lanjut
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </span>
            </div>
        </a>

    </div>

    {{-- ========================================================= --}}
    {{-- 3. TWO-COLUMN GRID: RECENT EVENTS & COMPLAINTS --}}
    {{-- ========================================================= --}}
    <div class="dash-grid-2">

        {{-- LEFT COLUMN: AGENDA TERKINI & MENDATANG --}}
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
                    Agenda Terkini & Mendatang
                </h3>
                <a href="{{ route('events.index') }}" class="dash-card-action">
                    Lihat Semua
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                                    <th>Jadwal</th>
                                    <th>Pengaju</th>
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
                                            <span style="font-size: 13px; color: #475569;">
                                                {{ $item->requester->name ?? '-' }}
                                            </span>
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
                        <p class="dash-empty-title">Belum Ada Agenda Aktif</p>
                        <p class="dash-empty-desc">Saat ini belum ada permohonan atau kegiatan agenda yang dijadwalkan.</p>
                    </div>
                @endif
            </div>

            <div class="dash-card-footer">
                <small style="color: #64748b;">Menampilkan 5 agenda kegiatan terkini</small>
                <a href="{{ route('events.index') }}" style="font-size: 12.5px; font-weight: 600; color: #2563eb; text-decoration: none;">
                    Kelola Semua Agenda &rarr;
                </a>
            </div>
        </div>

        {{-- RIGHT COLUMN: PENGADUAN KERUSAKAN TERKINI --}}
        <div class="dash-card">
            <div class="dash-card-header">
                <h3 class="dash-card-title">
                    <span class="dash-card-title-icon" style="background: #fef2f2; color: #dc2626;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                        </svg>
                    </span>
                    Pengaduan Kerusakan Terbaru
                </h3>
                <a href="{{ route('admin.complaints') }}" class="dash-card-action">
                    Kelola
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </a>
            </div>

            <div class="dash-card-body p-0">
                @if(isset($pengaduanTerbaru) && $pengaduanTerbaru->count() > 0)
                    <div class="dash-list">
                        @foreach($pengaduanTerbaru as $complaint)
                            <div class="dash-list-item">
                                <div class="dash-list-main">
                                    <div class="dash-list-icon" style="background: {{ $complaint->status_repair === 'Sudah Diperbaiki' ? '#ecfdf5' : ($complaint->status_repair === 'Menunggu Spare Part' ? '#eff6ff' : '#fef2f2') }}; color: {{ $complaint->status_repair === 'Sudah Diperbaiki' ? '#059669' : ($complaint->status_repair === 'Menunggu Spare Part' ? '#2563eb' : '#dc2626') }};">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="dash-list-title">
                                            {{ $complaint->room->room_name ?? 'Ruangan' }}
                                        </div>
                                        <p class="dash-list-desc">
                                            {{ \Illuminate\Support\Str::limit($complaint->description, 70) }}
                                        </p>
                                        <div class="dash-list-meta">
                                            <span>Oleh: <strong>{{ $complaint->reporter->name ?? 'Pengguna' }}</strong></span>
                                            <span>•</span>
                                            <span>{{ $complaint->created_at->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div style="text-align: right; flex-shrink: 0;">
                                    @if($complaint->status_repair === 'Sudah Diperbaiki')
                                        <span class="dash-badge success">
                                            <span class="dash-badge-dot"></span> Selesai
                                        </span>
                                    @elseif($complaint->status_repair === 'Menunggu Spare Part')
                                        <span class="dash-badge info">
                                            <span class="dash-badge-dot"></span> Menunggu Part
                                        </span>
                                    @else
                                        <span class="dash-badge danger">
                                            <span class="dash-badge-dot"></span> Belum Diperbaiki
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="dash-empty">
                        <div class="dash-empty-icon" style="background: #ecfdf5; color: #059669;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <p class="dash-empty-title">Semua Fasilitas Berfungsi Normal</p>
                        <p class="dash-empty-desc">Tidak ada pengaduan kerusakan yang belum ditangani saat ini.</p>
                    </div>
                @endif
            </div>

            <div class="dash-card-footer">
                <small style="color: #64748b;">Pantau terus sarana & prasarana sekolah</small>
                <a href="{{ route('admin.complaints') }}" style="font-size: 12.5px; font-weight: 600; color: #2563eb; text-decoration: none;">
                    Buka Panel Pengaduan &rarr;
                </a>
            </div>
        </div>

    </div>

    {{-- ========================================================= --}}
    {{-- 5. BOTTOM SECTION: RUANGAN SEKOLAH PREVIEW --}}
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
                Fasilitas & Ruangan Sekolah
            </h3>
            <a href="{{ route('rooms.index') }}" class="dash-card-action">
                Kelola Semua Ruangan
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
        </div>

        <div class="dash-card-body">
            @if(isset($ruanganList) && $ruanganList->count() > 0)
                <div class="dash-room-grid">
                    @foreach($ruanganList as $room)
                        <div class="dash-room-card">
                            <div>
                                <div class="dash-room-header">
                                    <h4 class="dash-room-name">{{ $room->room_name }}</h4>
                                    @if($room->is_active ?? true)
                                        <span class="dash-badge success" style="font-size: 11px;">Aktif</span>
                                    @else
                                        <span class="dash-badge danger" style="font-size: 11px;">Nonaktif</span>
                                    @endif
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
                                    <span>
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"/>
                                            <polyline points="12 6 12 12 16 14"/>
                                        </svg>
                                        PIC: <strong>{{ $room->pic->name ?? 'Belum Ditugaskan' }}</strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="dash-empty">
                    <p class="dash-empty-title">Belum Ada Data Ruangan</p>
                    <p class="dash-empty-desc">Tambahkan data ruangan sekolah agar dapat digunakan untuk peminjaman agenda.</p>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
