@extends('layouts.app')

@section('title', 'Edit Perbaikan Pengaduan')

@section('judul', 'Edit Perbaikan Pengaduan')

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

@php
    $backRoute = request('from') === 'report'
        ? route('pic.complaints.report')
        : route('pic.complaints');
@endphp

<div class="complaint-form-container">

    <div class="detail-card" style="margin-bottom: 24px;">
        <div class="detail-card-header">
            <div>
                <h2 class="detail-card-title">{{ $complaint->room?->room_name ?? 'Ruangan Tidak Ditemukan' }}</h2>
                <p class="detail-card-subtitle">
                    Lokasi: {{ $complaint->room?->location ?? '-' }} • Dilaporkan {{ $complaint->reported_at?->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') ?? '-' }}
                </p>
            </div>

            <div>
                @if ($complaint->status_repair === 'Sudah Diperbaiki')
                    <span class="complaint-status-pill selesai">
                        <span class="dot"></span> Sudah Diperbaiki
                    </span>
                @elseif ($complaint->status_repair === 'Menunggu Spare Part')
                    <span class="complaint-status-pill proses">
                        <span class="dot"></span> Menunggu Spare Part
                    </span>
                @else
                    <span class="complaint-status-pill belum">
                        <span class="dot"></span> {{ $complaint->status_repair }}
                    </span>
                @endif
            </div>
        </div>

        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Pelapor</span>
                <strong class="detail-value">{{ $complaint->reporter?->name ?? '-' }} ({{ $complaint->reporter?->email ?? '-' }})</strong>
            </div>

            <div class="detail-item">
                <span class="detail-label">Ditangani Terakhir Oleh</span>
                <strong class="detail-value">{{ $complaint->handler?->name ?? 'Belum ada penanganan' }}</strong>
            </div>

            @if ($complaint->event)
                <div class="detail-item">
                    <span class="detail-label">Terkait Agenda</span>
                    <strong class="detail-value">{{ $complaint->event->event_name }} ({{ $complaint->event->date?->format('d-m-Y') ?? '-' }})</strong>
                </div>
            @endif

            <div class="detail-item">
                <span class="detail-label">Waktu Selesai Perbaikan</span>
                <strong class="detail-value">{{ $complaint->repaired_at?->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('d M Y, H:i') ?? '-' }}</strong>
            </div>
        </div>

        <div class="detail-section">
            <span class="detail-label">Deskripsi Kerusakan</span>
            <p class="detail-description">{{ $complaint->description }}</p>
        </div>

        @if ($complaint->photo_path)
            <div class="detail-section">
                <span class="detail-label">Foto Kerusakan</span>
                <a href="{{ asset('storage/' . $complaint->photo_path) }}" target="_blank" rel="noopener noreferrer" style="display: inline-block; margin-top: 8px;">
                    <img src="{{ asset('storage/' . $complaint->photo_path) }}" alt="Foto Kerusakan" class="complaint-photo" style="max-width: 280px;">
                </a>
            </div>
        @endif
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('pic.complaints.update', $complaint->id) }}" class="detail-card">
        @csrf
        @method('PUT')

        @if (request('from') === 'report')
            <input type="hidden" name="redirect_to" value="report">
        @endif

        <h3 style="margin: 0 0 18px; color: #0f172a; font-size: 18px;">Perbarui Informasi Perbaikan</h3>

        <div class="form-group">
            <label for="status_repair">Status Perbaikan</label>
            <select name="status_repair" id="status_repair" class="form-control" required>
                <option value="Belum Diperbaiki" @selected(old('status_repair', $complaint->status_repair) === 'Belum Diperbaiki')>
                    Belum Diperbaiki
                </option>
                <option value="Menunggu Spare Part" @selected(old('status_repair', $complaint->status_repair) === 'Menunggu Spare Part')>
                    Menunggu Spare Part
                </option>
                <option value="Sudah Diperbaiki" @selected(old('status_repair', $complaint->status_repair) === 'Sudah Diperbaiki')>
                    Sudah Diperbaiki
                </option>
            </select>
            @error('status_repair')
                <small class="form-error">{{ $message }}</small>
            @enderror
        </div>

        <div class="form-group">
            <label for="repair_note">Keterangan Perbaikan</label>
            <textarea
                name="repair_note"
                id="repair_note"
                class="form-control"
                rows="4"
                maxlength="2000"
                placeholder="Masukkan rincian tindakan perbaikan, penggantian komponen, atau catatan lanjutan..."
            >{{ old('repair_note', $complaint->repair_note) }}</textarea>
            <small class="form-help">Informasi ini akan ditampilkan kepada pelapor dan admin tanpa membuat pengaduan baru.</small>
            @error('repair_note')
                <small class="form-error">{{ $message }}</small>
            @enderror
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-login">Simpan Perubahan</button>
            <a href="{{ $backRoute }}" class="btn btn-back">Kembali</a>
        </div>
    </form>

</div>

@endsection
