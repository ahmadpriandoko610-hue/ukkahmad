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

    @php
        $initialRole = old('role', $user->role);
        $canHaveWhatsApp = in_array($initialRole, ['guru', 'pic'], true);
    @endphp

    <div class="form-group">
        <label for="phone">Nomor WhatsApp</label>
        <input
            type="text"
            name="phone"
            id="phone"
            class="form-control"
            value="{{ $canHaveWhatsApp ? old('phone', $user->phone) : '' }}"
            placeholder="{{ $canHaveWhatsApp ? 'Contoh: 081234567890 atau +6281234567890' : 'Khusus untuk Guru / Organisasi dan PIC' }}"
            {{ $canHaveWhatsApp ? '' : 'disabled' }}
            style="{{ $canHaveWhatsApp ? '' : 'background-color: #f1f5f9; cursor: not-allowed; color: #94a3b8;' }}"
        >
        <small id="phoneHelp">{{ $canHaveWhatsApp ? 'Diisi untuk user dengan role Guru / Organisasi atau PIC (format 08..., 62..., atau +62...).' : 'Nomor WhatsApp hanya dapat diisi untuk user dengan role Guru / Organisasi atau PIC.' }}</small>
        @error('phone')
            <small class="form-error">{{ $message }}</small>
        @enderror
    </div>

    @if($user->role === 'guru')
        <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; font-size: 13.5px; color: #334155;">
            <strong>Status Persetujuan Notifikasi WhatsApp Guru (Read-Only):</strong><br>
            <span>Nomor Tersamar: <code>{{ $user->masked_phone ?? '-' }}</code></span><br>
            <span>Status:
                @if($user->hasWhatsAppConsent())
                    <strong style="color: #15803d;">Sudah Menyetujui</strong>
                    @if($user->whatsapp_opt_in_at)
                        (sejak {{ $user->whatsapp_opt_in_at->timezone('Asia/Jakarta')->format('d-m-Y H:i') }} WIB)
                    @endif
                @else
                    <strong style="color: #b45309;">Belum Menyetujui</strong>
                @endif
            </span><br>
            <small style="color: #64748b;">
                Persetujuan hanya dapat diberikan atau dicabut secara mandiri oleh Guru melalui halaman Pengaturan Notifikasi WhatsApp. Jika Admin mengubah nomor WhatsApp Guru, persetujuan lama otomatis dicabut.
            </small>
        </div>
    @endif

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
            <option value="siswa" {{ old('role', $user->role) == 'siswa' ? 'selected' : '' }}>Siswa</option>
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
