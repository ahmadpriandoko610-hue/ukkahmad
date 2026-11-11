@extends('layouts.app')

@section('title', 'Pindah Ruangan Agenda')

@section('judul', 'Pindah Ruangan Agenda')

@section('sidebar')
<a href="{{ route('dashboard.admin') }}" class="menu-item">Dashboard</a>
<a href="{{ route('agenda.index') }}" class="menu-item active">Kelola Agenda</a>
<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">Logout</button>
</form>
@endsection

@section('content')
<h2>Pindah Ruangan Agenda</h2>

<p>
    <strong>{{ $event->event_name }}</strong><br>
    {{ $event->formatted_date_range }} | {{ $event->session_range }}<br>
    Ruangan saat ini: {{ $event->room->room_name }}
</p>

@php
    $requester = $event->requester;
    $isGuruRequester = $requester && $requester->role === 'guru';
@endphp

<div class="{{ ($isGuruRequester && $requester->hasWhatsAppConsent()) ? 'alert-success' : 'alert-warning' }}" style="margin-bottom: 16px;">
    <strong>Status Notifikasi WhatsApp Pemilik Agenda:</strong><br>
    @if(!$isGuruRequester)
        Pemilik agenda bukan role Guru / Organisasi. Hanya notifikasi internal dashboard yang akan dibuat.
    @elseif(!$requester->masked_phone)
        Guru pemilik agenda belum memiliki nomor WhatsApp valid. Hanya notifikasi internal dashboard yang akan dibuat.
    @elseif(!$requester->hasWhatsAppConsent())
        Guru pemilik agenda belum menyetujui (opt-in) notifikasi WhatsApp. Pesan WhatsApp tidak akan dikirim, namun notifikasi internal dashboard tetap dibuat.
    @elseif(!config('services.whatsapp.enabled'))
        Guru sudah menyetujui notifikasi (<code>{{ $requester->masked_phone }}</code>), namun integrasi Meta Cloud API server sedang nonaktif.
    @elseif(config('services.whatsapp.dry_run', true))
        Guru sudah menyetujui notifikasi (<code>{{ $requester->masked_phone }}</code>). Mode simulasi (dry-run) aktif: hanya akan dicatat di log audit tanpa mengirim ke WhatsApp.
    @else
        Guru sudah menyetujui notifikasi (<code>{{ $requester->masked_phone }}</code>) dan siap dikirim melalui Meta Cloud API sesuai kebijakan allowlist.
    @endif
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form action="{{ route('events.move', $event) }}" method="POST">
    @csrf
    @method('PUT')
    <input type="hidden" name="current_room_id" value="{{ $event->room_id }}">

    <div class="form-group">
        <label for="room_id">Ruangan Tujuan</label>
        <select name="room_id" id="room_id" class="form-control" required>
            <option value="">-- Pilih ruangan --</option>
            @foreach ($rooms as $room)
                <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>
                    {{ $room->room_name }} - {{ $room->location }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group">
        <label for="move_reason">Alasan Pemindahan</label>
        <textarea name="move_reason" id="move_reason" class="form-control" rows="5" required>{{ old('move_reason') }}</textarea>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn-login">Simpan Pemindahan</button>
        <a href="{{ route('agenda.index') }}" class="btn btn-back">Batal</a>
    </div>
</form>
@endsection
