@extends('layouts.app')

@section('title', 'Tambah Agenda')

@section('judul', 'Tambah Agenda')

@section('sidebar')

<form method="POST" action="{{ route('logout') }}">
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

@if ($errors->any())

    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>

@endif

@if (session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif


<form
    method="POST"
    action="{{ route('events.store') }}"
>

    @csrf


    {{-- JUDUL --}}
    <div class="form-group">

        <label for="judul">
            Judul Kegiatan
        </label>

        <input
            type="text"
            name="judul"
            id="judul"
            class="form-control"
            value="{{ old('judul') }}"
            placeholder="Masukkan judul kegiatan"
            required
        >

    </div>


    {{-- TANGGAL MULAI & TANGGAL SELESAI --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">

        <div class="form-group">
            <label for="tanggal">
                Tanggal Mulai <span style="color: #ef4444;">*</span>
            </label>

            <input
                type="date"
                name="tanggal"
                id="tanggal"
                class="form-control"
                min="{{ today()->toDateString() }}"
                value="{{ old('tanggal') }}"
                required
            >

            <small class="form-help">Admin dapat mengajukan agenda mulai hari ini.</small>
        </div>

        <div class="form-group">
            <label for="tanggal_selesai">
                Tanggal Selesai <small style="color: #64748b; font-weight: normal;">(Opsional, jika > 1 hari)</small>
            </label>

            <input
                type="date"
                name="tanggal_selesai"
                id="tanggal_selesai"
                class="form-control"
                min="{{ today()->toDateString() }}"
                value="{{ old('tanggal_selesai') }}"
            >

            <small class="form-help">Kosongkan jika agenda hanya berlangsung 1 hari.</small>
        </div>

    </div>


    {{-- SESI MULAI --}}
    <div class="form-group">
        <label for="sesi_mulai">Sesi Mulai</label>
        <select name="sesi_mulai" id="sesi_mulai" class="form-control" required>
            <option value="">-- Pilih Sesi Mulai --</option>
            @php($jamAwalSesi = \Carbon\Carbon::createFromTime(7, 30))
            @for($sesi = 1; $sesi <= 14; $sesi++)
                @php($jamMulai = $jamAwalSesi->copy()->addMinutes(($sesi - 1) * 45))
                <option value="{{ $sesi }}" {{ old('sesi_mulai') == $sesi ? 'selected' : '' }}>Sesi {{ $sesi }} ({{ $jamMulai->format('H:i') }} - {{ $jamMulai->copy()->addMinutes(45)->format('H:i') }})</option>
            @endfor
        </select>
    </div>

    {{-- SESI SELESAI --}}
    <div class="form-group">
        <label for="sesi_selesai">Sesi Selesai</label>
        <select name="sesi_selesai" id="sesi_selesai" class="form-control" required>
            <option value="">-- Pilih Sesi Selesai --</option>
            @php($jamAwalSesi = \Carbon\Carbon::createFromTime(7, 30))
            @for($sesi = 1; $sesi <= 14; $sesi++)
                @php($jamMulai = $jamAwalSesi->copy()->addMinutes(($sesi - 1) * 45))
                <option value="{{ $sesi }}" {{ old('sesi_selesai') == $sesi ? 'selected' : '' }}>Sesi {{ $sesi }} ({{ $jamMulai->format('H:i') }} - {{ $jamMulai->copy()->addMinutes(45)->format('H:i') }})</option>
            @endfor
        </select>
    </div>


    {{-- RUANGAN --}}
    @php
        $oldSelectedRooms = old('ruangan_id', []);
        if (!is_array($oldSelectedRooms)) {
            $oldSelectedRooms = $oldSelectedRooms ? [$oldSelectedRooms] : [];
        }
    @endphp
    <div class="form-group">
        <label>
            Ruangan <span style="color: #ef4444;">*</span>
        </label>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin-top: 6px;">
            @foreach($ruangan as $item)
                <label style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; background: #f8fafc; font-weight: normal; margin: 0; transition: background 0.15s ease;">
                    <input
                        type="checkbox"
                        name="ruangan_id[]"
                        value="{{ $item->id }}"
                        {{ in_array($item->id, $oldSelectedRooms) ? 'checked' : '' }}
                        style="width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb;"
                    >
                    <span style="font-size: 14px; color: #1e293b; font-weight: 500;">{{ $item->room_name }}</span>
                </label>
            @endforeach
        </div>
        <small class="form-help">Centang satu atau lebih ruangan yang ingin dipinjam.</small>
    </div>


    {{-- KETERANGAN --}}
    <div class="form-group">

        <label for="keterangan">
            Keterangan
        </label>

        <textarea
            name="keterangan"
            id="keterangan"
            class="form-control"
            rows="4"
            placeholder="Masukkan keterangan"
        >{{ old('keterangan') }}</textarea>

    </div>


    {{-- BUTTON --}}
    <div style="margin-top: 20px;">

        <button
            type="submit"
            class="btn-login"
        >
            Simpan Agenda
        </button>

        <a
            href="{{ route('events.index') }}"
            class="btn btn-back"
            style="text-decoration:none; margin-left:10px;"
        >
            Kembali
        </a>

    </div>

</form>

@endsection
