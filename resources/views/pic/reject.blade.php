@extends('layouts.app')

@section('title', 'Tolak Agenda')
@section('judul', 'Tolak Agenda')

@section('sidebar')
<a href="{{ route('dashboard.pic') }}" class="menu-item">Dashboard</a>
<a href="{{ route('pic.agenda') }}" class="menu-item active">Verifikasi Agenda</a>
<a href="{{ route('pic.calendar') }}" class="menu-item">Kalender Agenda</a>
<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">Logout</button>
</form>
@endsection

@section('content')
<h2>Tolak Agenda</h2>

<p>
    <strong>{{ $event->event_name }}</strong><br>
    {{ $event->formatted_date_range }} | {{ $event->session_range }}<br>
    Ruangan: {{ $event->room->room_name ?? '-' }}
</p>

<div class="form-group">
    <label for="rejected_by">PIC yang menolak</label>
    <input type="text" id="rejected_by" class="form-control" value="{{ auth()->user()->name }}" readonly>
</div>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form action="{{ route('agenda.tolak', $event) }}" method="POST">
    @csrf
    <div class="form-group">
        <label for="rejection_reason">Keterangan Penolakan</label>
        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="5" maxlength="2000" required>{{ old('rejection_reason') }}</textarea>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn-login">Kirim Penolakan</button>
        <a href="{{ route('pic.agenda') }}" class="btn btn-back">Batal</a>
    </div>
</form>
@endsection
