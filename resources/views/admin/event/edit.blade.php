@extends('layouts.app')

@section('title', 'Edit Agenda')

@section('judul', 'Edit Agenda')

@section('sidebar')

<a href="{{ route('dashboard.admin') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('users.index') }}" class="menu-item">
    Kelola User
</a>

<a href="{{ route('ruangan.index') }}" class="menu-item">
    Kelola Ruangan
</a>

<a href="{{ route('agenda.index') }}" class="menu-item active">
    Kelola Agenda
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const startSelect = document.getElementById('sesi_mulai');
    const endSelect = document.getElementById('sesi_selesai');
    const tanggalMulai = document.getElementById('tanggal');
    const tanggalSelesai = document.getElementById('tanggal_selesai');

    if (tanggalMulai && tanggalSelesai) {
        tanggalMulai.addEventListener('change', function () {
            if (this.value) {
                tanggalSelesai.min = this.value;
                if (tanggalSelesai.value && tanggalSelesai.value < this.value) {
                    tanggalSelesai.value = this.value;
                }
            }
        });
    }

    if (!startSelect || !endSelect) return;

    function updateEndSessions() {
        const start = Number(startSelect.value);
        [...endSelect.options].forEach((option) => {
            if (!option.value) return;
            option.disabled = start > 0 && Number(option.value) < start;
        });

        if (start > 0 && Number(endSelect.value) < start) {
            endSelect.value = '';
        }
    }

    startSelect.addEventListener('change', updateEndSessions);
    updateEndSessions();
});
</script>

@endsection


@section('content')

{{-- Pesan error validasi --}}
@if ($errors->any())
    <div class="alert-error">
        <ul style="margin: 0; padding-left: 20px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


<form action="{{ route('agenda.update', $agenda->id) }}" method="POST">

    @csrf
    @method('PUT')


    {{-- JUDUL --}}
    <div class="form-group">

        <label for="judul">
            Judul Kegiatan
        </label>

        <input
            type="text"
            id="judul"
            name="judul"
            class="form-control"
            value="{{ old('judul', $agenda->event_name) }}"
            required
        >

    </div>


    {{-- TANGGAL MULAI & SELESAI --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">

        <div class="form-group">
            <label for="tanggal">
                Tanggal Mulai <span style="color: #ef4444;">*</span>
            </label>

            <input
                type="date"
                id="tanggal"
                name="tanggal"
                class="form-control"
                min="{{ today()->toDateString() }}"
                value="{{ old('tanggal', $agenda->date?->format('Y-m-d')) }}"
                required
            >

            <small class="form-help">Admin dapat menentukan tanggal mulai hari ini.</small>
        </div>

        <div class="form-group">
            <label for="tanggal_selesai">
                Tanggal Selesai <small style="color: #64748b; font-weight: normal;">(Opsional, jika > 1 hari)</small>
            </label>

            <input
                type="date"
                id="tanggal_selesai"
                name="tanggal_selesai"
                class="form-control"
                min="{{ old('tanggal', $agenda->date?->format('Y-m-d')) ?: today()->toDateString() }}"
                value="{{ old('tanggal_selesai', $agenda->end_date?->format('Y-m-d')) }}"
            >

            <small class="form-help">Kosongkan jika hanya 1 hari kegiatan.</small>
        </div>

    </div>


    @php
        $jamAwalSekolah = \Carbon\Carbon::createFromTime(7, 30);
        $sesiMulaiTersimpan = max(1, min(14, (int) floor($jamAwalSekolah->diffInMinutes(\Carbon\Carbon::parse($agenda->start_time), false) / 45) + 1));
        $sesiSelesaiTersimpan = max($sesiMulaiTersimpan, min(14, (int) ceil($jamAwalSekolah->diffInMinutes(\Carbon\Carbon::parse($agenda->end_time), false) / 45)));
    @endphp

    {{-- SESI MULAI --}}
    <div class="form-group">
        <label for="sesi_mulai">Sesi Mulai</label>
        <select id="sesi_mulai" name="sesi_mulai" class="form-control" required>
            @php
                $jamAwalDaftarSesi = \Carbon\Carbon::createFromTime(7, 30);
            @endphp
            @for($sesi = 1; $sesi <= 14; $sesi++)
                @php
                    $jamMulai = $jamAwalDaftarSesi->copy()->addMinutes(($sesi - 1) * 45);
                @endphp
                <option value="{{ $sesi }}" {{ old('sesi_mulai', $sesiMulaiTersimpan) == $sesi ? 'selected' : '' }}>Sesi {{ $sesi }} ({{ $jamMulai->format('H:i') }} - {{ $jamMulai->copy()->addMinutes(45)->format('H:i') }})</option>
            @endfor
        </select>
    </div>

    {{-- SESI SELESAI --}}
    <div class="form-group">
        <label for="sesi_selesai">Sesi Selesai</label>
        <select id="sesi_selesai" name="sesi_selesai" class="form-control" required>
            @php
                $jamAwalDaftarSesi = \Carbon\Carbon::createFromTime(7, 30);
            @endphp
            @for($sesi = 1; $sesi <= 14; $sesi++)
                @php
                    $jamMulai = $jamAwalDaftarSesi->copy()->addMinutes(($sesi - 1) * 45);
                @endphp
                <option value="{{ $sesi }}" {{ old('sesi_selesai', $sesiSelesaiTersimpan) == $sesi ? 'selected' : '' }}>Sesi {{ $sesi }} ({{ $jamMulai->format('H:i') }} - {{ $jamMulai->copy()->addMinutes(45)->format('H:i') }})</option>
            @endfor
        </select>
    </div>


    {{-- RUANGAN --}}
    <div class="form-group">

        <label for="ruangan_id">
            Ruangan
        </label>

        <select
            id="ruangan_id"
            name="ruangan_id"
            class="form-control"
            required
        >

            <option value="">
                -- Pilih Ruangan --
            </option>

            @foreach($ruangan as $item)

                <option
                    value="{{ $item->id }}"
                    {{ old('ruangan_id', $agenda->room_id) == $item->id ? 'selected' : '' }}
                >
                    {{ $item->room_name }}
                </option>

            @endforeach

        </select>

    </div>


    {{-- KETERANGAN --}}
    <div class="form-group">

        <label for="keterangan">
            Keterangan
        </label>

        <textarea
            id="keterangan"
            name="keterangan"
            class="form-control"
            rows="4"
        >{{ old('keterangan', $agenda->description) }}</textarea>

    </div>


    {{-- TOMBOL --}}
    <div style="margin-top: 20px;">

        <button
            type="submit"
            class="btn-login"
        >
            Update Agenda
        </button>

        <a
            href="{{ route('agenda.index') }}"
            class="btn"
            style="display: inline-block; text-decoration: none; margin-left: 8px;"
        >
            Batal
        </a>

    </div>

</form>

@endsection
