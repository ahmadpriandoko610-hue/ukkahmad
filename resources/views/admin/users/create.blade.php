@extends('layouts.app')

@section('title', 'Tambah User')

@section('judul', 'Tambah User')

@section('sidebar')

<a href="{{ route('dashboard.admin') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('users.index') }}" class="menu-item active">
    Kelola User
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection

@section('content')

<h2>Tambah User</h2>

<form action="{{ route('users.store') }}" method="POST">

    @csrf

    <div class="form-group">
        <label>Nama</label>
        <input type="text" name="name" class="form-control" required>
    </div>

    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required>
    </div>

    <div class="form-group">
        <label>Nomor WhatsApp</label>
        <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="Contoh: 081234567890">
        <small>Diisi khususnya untuk user dengan role PIC.</small>
    </div>

    <div class="form-group">
        <label>Password</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" class="form-control" required>
            <span class="toggle-password" id="togglePassword" role="button" tabindex="0" aria-label="Tampilkan password" aria-pressed="false">👁</span>
        </div>
    </div>

    <div class="form-group">
        <label>Role</label>

        <select name="role" class="form-control">

            <option value="admin">Admin</option>
            <option value="pic">PIC</option>
            <option value="guru">Guru / Organisasi</option>

        </select>

    </div>

    <div class="form-actions">
        <button type="submit" class="btn-login">
            Simpan
        </button>

        <a href="{{ route('users.index') }}" class="btn btn-back">
            Kembali
        </a>
    </div>

</form>

<script>
const password = document.getElementById('password');
const toggle = document.getElementById('togglePassword');

function togglePasswordVisibility() {
    const isHidden = password.type === 'password';
    password.type = isHidden ? 'text' : 'password';
    toggle.setAttribute('aria-pressed', String(isHidden));
    toggle.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
}

toggle.addEventListener('click', togglePasswordVisibility);
toggle.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        togglePasswordVisibility();
    }
});
</script>

@endsection
