@extends('layouts.app')

@section('title', 'Edit User')

@section('judul', 'Edit User')

@section('content')

<h2>Edit User</h2>

<form action="{{ route('users.update', $user->id) }}" method="POST">

    @csrf
    @method('PUT')

    <div class="form-group">
        <label for="name">Nama</label>
        <input
            type="text"
            name="name"
            id="name"
            class="form-control"
            value="{{ old('name', $user->name) }}"
            required
        >
        @error('name')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="email">Email</label>
        <input
            type="email"
            name="email"
            id="email"
            class="form-control"
            value="{{ old('email', $user->email) }}"
            required
        >
        @error('email')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="phone">Nomor WhatsApp</label>
        <input
            type="text"
            name="phone"
            id="phone"
            class="form-control"
            value="{{ old('phone', $user->phone) }}"
            placeholder="Contoh: 081234567890"
        >
        <small>Diisi khususnya untuk user dengan role PIC.</small>
        @error('phone')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="password">Password Baru</label>
        <div class="password-wrapper">
            <input
                type="password"
                name="password"
                id="password"
                class="form-control"
            >
            <span class="toggle-password" id="togglePassword" role="button" tabindex="0" aria-label="Tampilkan password" aria-pressed="false">👁</span>
        </div>
        <small>Kosongkan jika password tidak ingin diubah.</small>
        @error('password')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="role">Role</label>
        <select name="role" id="role" class="form-control" required>
            <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>Admin</option>
            <option value="pic" {{ old('role', $user->role) == 'pic' ? 'selected' : '' }}>PIC</option>
            <option value="guru" {{ old('role', $user->role) == 'guru' ? 'selected' : '' }}>Guru / Organisasi</option>
        </select>
        @error('role')
            <small class="form-error">{{ $message }}</small>
        @enderror
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
