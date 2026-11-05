@extends('layouts.app')

@section('title', 'Ajukan Pengaduan')

@section('judul', 'Pengaduan Kerusakan')

@section('sidebar')

<a href="{{ route('dashboard.guru') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('guru.agenda') }}" class="menu-item">
    Agenda Saya
</a>

<a href="{{ route('complaints.index') }}" class="menu-item">
    Pengaduan Saya
</a>

<a href="{{ route('complaints.create') }}" class="menu-item active">
    Ajukan Pengaduan
</a>

<form method="POST" action="{{ route('logout') }}">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection


@section('content')

<style>
    .camera-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        width: auto;
        padding: 8px 12px;
        border: 0;
        border-radius: 7px;
        background: #2563eb;
        color: #fff;
        font: inherit;
        font-size: 14px;
        cursor: pointer;
    }

    .camera-button:hover {
        background: #1d4ed8;
    }
</style>

<div class="complaint-form-container">

    <h2>Ajukan Pengaduan Kerusakan</h2>

    <p>
        Silakan isi formulir berikut untuk melaporkan kerusakan ruangan.
    </p>


    {{-- ERROR --}}

    @if ($errors->any())

        <div class="alert alert-danger">

            @foreach ($errors->all() as $error)

                <div>
                    {{ $error }}
                </div>

            @endforeach

        </div>

    @endif


    {{-- SUCCESS --}}

    @if (session('success'))

        <div class="alert alert-success">
            {{ session('success') }}
        </div>

    @endif


    {{-- FORM PENGADUAN --}}

    <form
        method="POST"
        action="{{ route('complaints.store') }}"
        enctype="multipart/form-data"
        class="complaint-form"
    >

        @csrf


        @if ($agenda)
            <input type="hidden" name="agenda_id" value="{{ $agenda->id }}">
        @endif

        {{-- RUANGAN --}}

        <div class="form-group">

            <label for="room_id">
                Ruangan
            </label>

            @if ($agenda)
                <input type="text" class="form-control" value="{{ $agenda->room?->room_name ?? 'Ruangan tidak tersedia' }}{{ $agenda->room?->location && $agenda->room->location !== $agenda->room->room_name ? ' - ' . $agenda->room->location : '' }}" readonly>
            @else
            <select
                name="room_id"
                id="room_id"
                class="form-control"
                required
            >

                <option value="">
                    -- Pilih Ruangan --
                </option>

                @foreach ($rooms as $room)

                    <option
                        value="{{ $room->id }}"
                        {{ old('room_id') == $room->id ? 'selected' : '' }}
                    >
                        {{ $room->room_name }}

                        @if ($room->location)
                            - {{ $room->location }}
                        @endif
                    </option>

                @endforeach

            </select>
            @endif

            @error('room_id')

                <small class="form-error">
                    {{ $message }}
                </small>

            @enderror

        </div>


        {{-- DESKRIPSI --}}

        <div class="form-group">

            <label for="description">
                Deskripsi Kerusakan
            </label>

            <textarea
                name="description"
                id="description"
                class="form-control"
                rows="6"
                placeholder="Jelaskan kerusakan yang terjadi pada ruangan..."
                required
            >{{ old('description') }}</textarea>

            @error('description')

                <small class="form-error">
                    {{ $message }}
                </small>

            @enderror

        </div>


        {{-- FOTO --}}

        <div class="form-group">

            <label for="photo">
                Foto Kerusakan
            </label>

            <input
                type="file"
                name="photo"
                id="photo"
                class="form-control"
                accept="image/jpeg,image/png,image/webp"
                required
            >

            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                <button type="button" id="open-camera" class="camera-button">
                    <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 4h-4L8 7H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2-3Z"/>
                        <circle cx="12" cy="13" r="3"/>
                    </svg>
                    Foto dengan Kamera
                </button>
                <button type="button" id="take-photo" class="btn-login" hidden>
                    Ambil Foto
                </button>
                <button type="button" id="close-camera" class="btn btn-back" hidden>
                    Tutup Kamera
                </button>
                <button type="button" id="cancel-photo" class="btn btn-back" hidden>
                    Batal / Foto Ulang
                </button>
            </div>

            <div id="camera-error" class="form-error" role="alert" hidden></div>
            <video id="camera-preview" autoplay playsinline
                   style="display:none; width:100%; max-width:480px; margin-top:12px; border-radius:8px; background:#111;"></video>
            <img id="photo-preview" alt="Pratinjau foto kerusakan"
                 style="display:none; width:100%; max-width:480px; margin-top:12px; border-radius:8px;">
            <canvas id="photo-canvas" hidden></canvas>

            <small class="form-help">
                Format yang diperbolehkan:
                JPG, JPEG, PNG, WEBP.
                Maksimal 2 MB.
            </small>

            @error('photo')

                <small class="form-error">
                    {{ $message }}
                </small>

            @enderror

        </div>


        {{-- BUTTON --}}

        <div class="form-actions">

            <button
                type="submit"
                class="btn-login"
            >
                Kirim Pengaduan
            </button>

            <a
                href="{{ route('complaints.index') }}"
                class="btn btn-back"
            >
                Kembali
            </a>

        </div>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const photoInput = document.getElementById('photo');
    const openButton = document.getElementById('open-camera');
    const captureButton = document.getElementById('take-photo');
    const closeButton = document.getElementById('close-camera');
    const cancelPhotoButton = document.getElementById('cancel-photo');
    const video = document.getElementById('camera-preview');
    const preview = document.getElementById('photo-preview');
    const canvas = document.getElementById('photo-canvas');
    const error = document.getElementById('camera-error');
    let cameraStream = null;
    let previewUrl = null;

    function stopCamera() {
        cameraStream?.getTracks().forEach(track => track.stop());
        cameraStream = null;
        video.srcObject = null;
        video.style.display = 'none';
        captureButton.hidden = true;
        closeButton.hidden = true;
        openButton.hidden = false;
    }

    function showPreview(file) {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.style.display = 'block';
        cancelPhotoButton.hidden = false;
    }

    function clearPhoto() {
        photoInput.value = '';
        preview.removeAttribute('src');
        preview.style.display = 'none';
        cancelPhotoButton.hidden = true;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
    }

    photoInput.addEventListener('change', () => {
        if (photoInput.files?.[0]) showPreview(photoInput.files[0]);
    });

    openButton.addEventListener('click', async () => {
        error.hidden = true;
        error.textContent = '';

        if (!navigator.mediaDevices?.getUserMedia) {
            error.textContent = 'Kamera tidak tersedia. Buka aplikasi melalui HTTPS atau localhost, lalu pilih file foto.';
            error.hidden = false;
            return;
        }

        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            video.srcObject = cameraStream;
            video.style.display = 'block';
            captureButton.hidden = false;
            closeButton.hidden = false;
            openButton.hidden = true;
        } catch (cameraError) {
            error.textContent = cameraError.name === 'NotAllowedError'
                ? 'Izin kamera ditolak. Izinkan akses kamera di browser atau pilih file foto.'
                : 'Kamera tidak dapat dibuka. Periksa kamera atau pilih file foto.';
            error.hidden = false;
            stopCamera();
        }
    });

    captureButton.addEventListener('click', () => {
        if (!video.videoWidth || !video.videoHeight) {
            error.textContent = 'Kamera belum siap. Tunggu sebentar lalu coba lagi.';
            error.hidden = false;
            return;
        }

        const scale = Math.min(1, 1280 / video.videoWidth, 1280 / video.videoHeight);
        canvas.width = Math.round(video.videoWidth * scale);
        canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => {
            if (!blob) {
                error.textContent = 'Foto gagal diproses. Silakan coba lagi.';
                error.hidden = false;
                return;
            }

            const file = new File([blob], `foto-pengaduan-${Date.now()}.jpg`, { type: 'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(file);
            photoInput.files = transfer.files;
            showPreview(file);
            error.hidden = true;
            stopCamera();
        }, 'image/jpeg', 0.82);
    });

    closeButton.addEventListener('click', stopCamera);
    cancelPhotoButton.addEventListener('click', clearPhoto);
    window.addEventListener('beforeunload', stopCamera);
});
</script>

@endsection
