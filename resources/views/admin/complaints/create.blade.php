@extends('layouts.app')

@section('title', 'Buat Pengaduan')
@section('judul', 'Buat Pengaduan')

@section('sidebar')
<a href="{{ route('dashboard.admin') }}" class="menu-item">Dashboard</a>
<a href="{{ route('agenda.index') }}" class="menu-item active">Kelola Agenda</a>
<a href="{{ route('admin.complaints') }}" class="menu-item">Pengaduan Kerusakan</a>
<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">Logout</button>
</form>
@endsection

@section('content')
<style>
    .camera-button { display:inline-flex; align-items:center; gap:8px; width:auto; padding:8px 12px; border:0; border-radius:7px; background:#2563eb; color:#fff; font:inherit; font-size:14px; cursor:pointer; }
    .camera-button:hover { background:#1d4ed8; }
</style>

<h2>Buat Pengaduan Kerusakan</h2>

<p>
    <strong>Agenda:</strong> {{ $event->event_name }}<br>
    <strong>Tanggal:</strong> {{ $event->date?->format('d-m-Y') }} | {{ $event->session_range }}<br>
    <strong>Ruangan:</strong> {{ $event->room->room_name ?? '-' }}
</p>

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<form action="{{ route('admin.complaints.store', $event) }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="form-group">
        <label for="description">Deskripsi Kerusakan</label>
        <textarea name="description" id="description" class="form-control" rows="5" required>{{ old('description') }}</textarea>
    </div>
    <div class="form-group">
        <label for="photo">Foto Kerusakan</label>
        <input type="file" name="photo" id="photo" class="form-control" accept="image/jpeg,image/png,image/webp" required>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
            <button type="button" id="open-camera" class="camera-button">Foto dengan Kamera</button>
            <button type="button" id="take-photo" class="btn-login" hidden>Ambil Foto</button>
            <button type="button" id="close-camera" class="btn btn-back" hidden>Tutup Kamera</button>
            <button type="button" id="cancel-photo" class="btn btn-back" hidden>Batal / Foto Ulang</button>
        </div>
        <div id="camera-error" class="form-error" role="alert" hidden></div>
        <video id="camera-preview" autoplay playsinline style="display:none; width:100%; max-width:480px; margin-top:12px; border-radius:8px; background:#111;"></video>
        <img id="photo-preview" alt="Pratinjau foto kerusakan" style="display:none; width:100%; max-width:480px; margin-top:12px; border-radius:8px;">
        <canvas id="photo-canvas" hidden></canvas>
        <small class="form-help">JPG, JPEG, PNG, atau WEBP. Maksimal 2 MB.</small>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn-login">Kirim Pengaduan</button>
        <a href="{{ route('agenda.index') }}" class="btn btn-back">Batal</a>
    </div>
</form>

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
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
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
