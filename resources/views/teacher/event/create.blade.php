@extends('layouts.app')

@section('title', 'Ajukan Agenda')

@section('judul', 'Ajukan Agenda')

@section('sidebar')

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit" class="menu-item btn-logout-sidebar">
            Logout
        </button>
    </form>

@endsection

@section('content')

{{-- ERROR VALIDASI --}}
@if ($errors->any())

    <div class="alert alert-danger">

        @foreach ($errors->all() as $error)
            <div>
                {{ $error }}
            </div>
        @endforeach

    </div>

@endif


{{-- PESAN SUCCESS --}}
@if (session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif


{{-- FORM AJUKAN AGENDA --}}
<form method="POST" action="{{ route('guru.events.store') }}">

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
                min="{{ today()->addDay()->toDateString() }}"
                value="{{ old('tanggal') }}"
                required
            >

            <small class="form-help">Peminjaman ruang wajib diajukan minimal H-1.</small>
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
                min="{{ today()->addDay()->toDateString() }}"
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
    <div class="form-group">

        <label for="ruangan_id">
            Ruangan
        </label>

        <select
            name="ruangan_id"
            id="ruangan_id"
            class="form-control"
            required
        >

            <option value="">
                -- Pilih Ruangan --
            </option>

            @foreach($ruangan as $item)

                <option
                    value="{{ $item->id }}"
                    {{ old('ruangan_id') == $item->id ? 'selected' : '' }}
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
            name="keterangan"
            id="keterangan"
            class="form-control"
            rows="4"
            placeholder="Masukkan keterangan kegiatan"
        >{{ old('keterangan') }}</textarea>

    </div>


    {{-- BUTTON --}}
    <div style="margin-top: 20px;">

        <button
            type="submit"
            class="btn-login"
        >
            Ajukan Agenda
        </button>

        <a
            href="{{ route('guru.agenda') }}"
            class="btn btn-back"
            style="text-decoration:none; margin-left:10px;"
        >
            Kembali
        </a>

    </div>

</form>

<script>
(() => {
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
})();
</script>

@endsection
