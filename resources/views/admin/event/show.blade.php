@extends('layouts.app')

@section('title', 'Detail Agenda')

@section('judul', 'Detail Agenda')

@section('content')

@php
    $sisterEvents = $event->getSisterEvents()->loadMissing('room');
    $roomsList = $sisterEvents->pluck('room')->filter()->unique('id')->values();
    if ($roomsList->isEmpty() && $event->room) {
        $roomsList = collect([$event->room]);
    }
@endphp

<div class="detail-card">
    <div class="detail-card-header">
        <div>
            <h2 class="detail-card-title">{{ $event->event_name }}</h2>
            <p class="detail-card-subtitle">Diajukan oleh {{ $event->user?->name ?? '-' }}</p>
        </div>

        <div>
            @if($event->status_pelaksanaan === 'Sedang Berlangsung')
                <span class="badge-ongoing">Sedang Berlangsung</span>
            @elseif($event->status_pelaksanaan === 'Belum Terlaksana')
                <span class="badge-upcoming">Belum Terlaksana</span>
            @elseif($event->status_pelaksanaan === 'Sudah Terlaksana')
                <span class="badge-completed">Sudah Terlaksana</span>
            @elseif($event->status === 'Menunggu')
                <span class="badge-warning">Menunggu</span>
            @else
                <span class="badge-danger">{{ $event->status }}</span>
            @endif
        </div>
    </div>

    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Tanggal Pelaksanaan</span>
            <strong class="detail-value">{{ $event->formatted_date_range }}</strong>
        </div>

        <div class="detail-item">
            <span class="detail-label">Waktu Sesi</span>
            <strong class="detail-value">{{ $event->session_range }}</strong>
        </div>

        <div class="detail-item">
            <span class="detail-label">Jam Mulai</span>
            <strong class="detail-value">{{ $event->start_time ? substr((string) $event->start_time, 0, 5) : '-' }}</strong>
        </div>

        <div class="detail-item">
            <span class="detail-label">Jam Selesai</span>
            <strong class="detail-value">{{ $event->end_time ? substr((string) $event->end_time, 0, 5) : '-' }}</strong>
        </div>

        <div class="detail-item">
            <span class="detail-label">Ruangan Digunakan</span>
            <div class="room-pill-list" style="margin-top: 6px;">
                @forelse($roomsList as $roomItem)
                    <span class="room-pill-badge">
                        {{ $roomItem->room_name }}{{ $roomItem->location ? ' - ' . $roomItem->location : '' }}
                    </span>
                @empty
                    <span>-</span>
                @endforelse
            </div>
        </div>

        <div class="detail-item">
            <span class="detail-label">Peninjau (Approver)</span>
            <strong class="detail-value">{{ $event->status === 'Menunggu' ? '-' : ($event->approver?->name ?? '-') }}</strong>
        </div>
    </div>

    <div class="detail-section">
        <span class="detail-label">Keterangan Agenda</span>
        <p class="detail-description">{{ $event->description ?: '-' }}</p>
    </div>

    @includeWhen(!empty($roomMoveWhatsapp['available']), 'admin.event.partials.room-move-whatsapp-notice', ['roomMoveWhatsapp' => $roomMoveWhatsapp ?? null])

    @if($event->move_reason)
        <div class="alert-warning" style="margin-top: 16px;">
            <strong>Alasan Pemindahan Ruangan:</strong><br>
            {{ $event->move_reason }}
        </div>
    @endif

    @if($event->status === 'Ditolak' && $event->rejection_reason)
        <div class="alert-danger" style="margin-top: 16px;">
            <strong>Keterangan Penolakan:</strong><br>
            {{ $event->rejection_reason }}
        </div>
    @endif

    <div class="form-actions">
        @if($event->status !== 'Ditolak')
            <a href="{{ route('agenda.edit', $event->id) }}" class="btn btn-edit">Edit Agenda</a>
        @endif

        @if($event->status === 'Disetujui')
            <a href="{{ route('events.move.form', $event->id) }}" class="btn btn-edit">Pindah Ruangan</a>
        @endif

        <a href="{{ route('agenda.index') }}" class="btn btn-back">Kembali</a>
    </div>
</div>

@endsection
