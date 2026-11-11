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
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
        @error('name')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
        @error('email')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    @php
        $initialRole = old('role', 'admin');
        $canHaveWhatsApp = in_array($initialRole, ['guru', 'pic'], true);
    @endphp

    <div class="form-group">
        <label for="phone">Nomor WhatsApp</label>
        <input
            type="text"
            name="phone"
            id="phone"
            class="form-control"
            value="{{ $canHaveWhatsApp ? old('phone') : '' }}"
            placeholder="{{ $canHaveWhatsApp ? 'Contoh: 081234567890 atau +6281234567890' : 'Khusus untuk Guru / Organisasi dan PIC' }}"
            {{ $canHaveWhatsApp ? '' : 'disabled' }}
            style="{{ $canHaveWhatsApp ? '' : 'background-color: #f1f5f9; cursor: not-allowed; color: #94a3b8;' }}"
        >
        <small id="phoneHelp">{{ $canHaveWhatsApp ? 'Diisi untuk user dengan role Guru / Organisasi atau PIC (format 08..., 62..., atau +62...).' : 'Nomor WhatsApp hanya dapat diisi untuk user dengan role Guru / Organisasi atau PIC.' }}</small>
        @error('phone')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label>Password</label>
        <div class="password-wrapper">
            <input type="password" name="password" id="password" class="form-control" required>
            <span class="toggle-password" id="togglePassword" role="button" tabindex="0" aria-label="Tampilkan password" aria-pressed="false">👁</span>
        </div>
        @error('password')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label for="role">Role</label>

        <select name="role" id="role" class="form-control" required>

            <option value="admin" {{ old('role', 'admin') == 'admin' ? 'selected' : '' }}>Admin</option>
            <option value="pic" {{ old('role') == 'pic' ? 'selected' : '' }}>PIC</option>
            <option value="guru" {{ old('role') == 'guru' ? 'selected' : '' }}>Guru / Organisasi</option>
            <option value="siswa" {{ old('role') == 'siswa' ? 'selected' : '' }}>Siswa</option>

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
document.addEventListener('DOMContentLoaded', function () {
    const password = document.getElementById('password');
    const toggle = document.getElementById('togglePassword');

    if (password && toggle) {
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
    }

    const roleSelect = document.getElementById('role');
    const phoneInput = document.getElementById('phone');
    const phoneHelp = document.getElementById('phoneHelp');

    if (roleSelect && phoneInput) {
        function updateWhatsAppField() {
            const canHaveWhatsApp = roleSelect.value === 'pic' || roleSelect.value === 'guru';

            if (canHaveWhatsApp) {
                phoneInput.disabled = false;
                phoneInput.readOnly = false;
                phoneInput.placeholder = 'Contoh: 081234567890 atau +6281234567890';
                phoneInput.style.backgroundColor = '';
                phoneInput.style.cursor = '';
                phoneInput.style.color = '';
                if (phoneHelp) {
                    phoneHelp.textContent = 'Diisi untuk user dengan role Guru / Organisasi atau PIC (format 08..., 62..., atau +62...).';
                }
            } else {
                phoneInput.value = '';
                phoneInput.disabled = true;
                phoneInput.readOnly = true;
                phoneInput.placeholder = 'Khusus untuk Guru / Organisasi dan PIC';
                phoneInput.style.backgroundColor = '#f1f5f9';
                phoneInput.style.cursor = 'not-allowed';
                phoneInput.style.color = '#94a3b8';
                if (phoneHelp) {
                    phoneHelp.textContent = 'Nomor WhatsApp hanya dapat diisi untuk user dengan role Guru / Organisasi atau PIC.';
                }
            }
        }

        roleSelect.addEventListener('change', updateWhatsAppField);

        phoneInput.addEventListener('keydown', function (e) {
            if (roleSelect.value !== 'pic' && roleSelect.value !== 'guru') {
                e.preventDefault();
            }
        });

        phoneInput.addEventListener('paste', function (e) {
            if (roleSelect.value !== 'pic' && roleSelect.value !== 'guru') {
                e.preventDefault();
            }
        });

        updateWhatsAppField();
    }
});
</script>
@endsection
