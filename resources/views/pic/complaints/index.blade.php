@extends('layouts.app')

@section('title', 'Pengaduan Kerusakan PIC')

@section('judul', 'Pengaduan Kerusakan')

@section('sidebar')

<a href="{{ route('dashboard.pic') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('pic.agenda') }}" class="menu-item">
    Verifikasi Agenda
</a>

<a href="{{ route('pic.calendar') }}" class="menu-item">
    Kalender Agenda
</a>

<a href="{{ route('pic.complaints') }}" class="menu-item active">
    Pengaduan Kerusakan
</a>

<a href="{{ route('pic.complaints.report') }}" class="menu-item">
    Laporan Kerusakan
</a>

<a href="{{ route('pic.schedule') }}" class="menu-item">
    Jadwal Ruangan
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
            <h2 class="complaint-neat-title">Pengaduan Kerusakan Ruangan</h2>
            <p class="complaint-neat-subtitle">
                Daftar laporan kerusakan fasilitas untuk ruangan yang Anda kelola sebagai PIC.
            </p>
        </div>

        <a href="{{ route('pic.complaints.report') }}" class="complaint-header-btn">
            Cetak Laporan
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
                            Dilaporkan: {{ $complaint->reported_at ? $complaint->reported_at->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') : '-' }}
                        </span>

                        @if ($complaint->event)
                            <span class="complaint-meta-pill highlight">
                                Agenda: {{ $complaint->event->event_name }} ({{ $complaint->event->date?->format('d-m-Y') ?? '-' }})
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
                            <strong>Pelapor:</strong> {{ $complaint->reporter->name ?? '-' }} ({{ $complaint->reporter->email ?? '-' }})
                        </span>
                        <span>
                            <strong>Ditangani:</strong>
                            @if ($complaint->handler)
                                <strong style="color: #0f172a;">{{ $complaint->handler->name }}</strong>
                            @else
                                <span style="color: #94a3b8; font-style: italic;">Belum ada penanganan</span>
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
                                <strong>Keterangan Perbaikan:</strong>
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

            {{-- BAWAH: TINDAKAN UPDATE STATUS PIC --}}
            <div class="complaint-neat-footer" style="align-items: flex-start;">
                <div style="flex: 1; min-width: 0;">
                    <span style="font-size: 12.5px; color: #64748b; display: block; margin-bottom: 4px;">
                        Kelola status perbaikan fasilitas ini:
                    </span>
                    @if ($complaint->repaired_at)
                        <span style="font-size: 12px; color: #16a34a; font-weight: 500; display: block; margin-bottom: 8px;">
                            Ditandai selesai pada: {{ $complaint->repaired_at->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') }}
                        </span>
                    @endif
                    <a href="{{ route('pic.complaints.edit', $complaint->id) }}" class="btn btn-edit" style="font-size: 12.5px;">
                        Edit Perbaikan
                    </a>
                </div>

                <form method="POST" action="{{ route('pic.complaints.update', $complaint->id) }}" class="complaint-pic-form" style="flex: 2; min-width: 0; width: 100%; justify-content: flex-end;">
                    @csrf
                    @method('PUT')

                    <div style="display: flex; align-items: center; gap: 8px; width: 100%; justify-content: flex-end; flex-wrap: wrap;">
                        <label for="status_{{ $complaint->id }}" style="font-size: 12.5px; font-weight: 600; color: #334155; margin: 0;">
                            Status:
                        </label>

                        <select name="status_repair" id="status_{{ $complaint->id }}" class="complaint-pic-select" required onchange="toggleRepairNote(this, '{{ $complaint->id }}')">
                            <option value="Belum Diperbaiki" {{ $complaint->status_repair === 'Belum Diperbaiki' ? 'selected' : '' }}>
                                Belum Diperbaiki
                            </option>
                            <option value="Menunggu Spare Part" {{ $complaint->status_repair === 'Menunggu Spare Part' ? 'selected' : '' }}>
                                Menunggu Spare Part
                            </option>
                            <option value="Sudah Diperbaiki" {{ $complaint->status_repair === 'Sudah Diperbaiki' ? 'selected' : '' }}>
                                Sudah Diperbaiki
                            </option>
                        </select>

                        <button type="submit" class="complaint-pic-btn">
                            Simpan Perubahan
                        </button>
                    </div>

                    <div id="repair_note_group_{{ $complaint->id }}" style="{{ $complaint->status_repair === 'Sudah Diperbaiki' ? 'display: block;' : 'display: none;' }} width: 100%; margin-top: 8px;">
                        <label for="repair_note_{{ $complaint->id }}" style="display: block; font-size: 12px; font-weight: 600; color: #166534; margin-bottom: 4px;">
                            Keterangan Perbaikan:
                        </label>
                        <input type="text" name="repair_note" id="repair_note_{{ $complaint->id }}" class="complaint-pic-select" style="width: 100%; box-sizing: border-box;" placeholder="Tambahkan keterangan perbaikan (contoh: Kabel proyektor telah diganti baru)..." value="{{ old('repair_note', $complaint->repair_note) }}">
                    </div>
                </form>
            </div>

        </div>
    @empty
        <div class="alert" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; text-align: center; color: #64748b;">
            <p style="margin: 0; font-size: 15px;">Belum ada pengaduan kerusakan untuk ruangan tanggung jawab Anda.</p>
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

<script>
    function toggleRepairNote(selectElem, id) {
        var group = document.getElementById('repair_note_group_' + id);
        if (!group) return;
        if (selectElem.value === 'Sudah Diperbaiki') {
            group.style.display = 'block';
            var input = document.getElementById('repair_note_' + id);
            if (input) input.focus();
        } else {
            group.style.display = 'none';
        }
    }
</script>

@endsection
