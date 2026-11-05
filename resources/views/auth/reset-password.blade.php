<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Password Baru | RAPI-Sekolah</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-container">
    <main class="login-card">
        <header class="login-header">
            <h1>Atur Password Baru</h1>
            <p>Buat password baru untuk akun RAPI-Sekolah Anda.</p>
        </header>

        @if ($errors->any())
            <div class="alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('password.update') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" class="form-control"
                       value="{{ $email }}" readonly autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Password baru</label>
                <div class="password-wrapper">
                    <input id="password" type="password" name="password" class="form-control"
                           required minlength="6" autocomplete="new-password">
                    <span class="toggle-password" role="button" tabindex="0" aria-label="Tampilkan password baru" aria-pressed="false">👁</span>
                </div>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Konfirmasi password baru</label>
                <div class="password-wrapper">
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control"
                           required minlength="6" autocomplete="new-password">
                    <span class="toggle-password" role="button" tabindex="0" aria-label="Tampilkan konfirmasi password" aria-pressed="false">👁</span>
                </div>
            </div>
            <button type="submit" class="btn-login">Simpan Password Baru</button>
        </form>

        <div class="login-links"><a href="{{ route('login') }}">Kembali ke halaman login</a></div>
    </main>
</div>
<script>
document.querySelectorAll('.toggle-password').forEach((toggle) => {
    const password = toggle.parentElement.querySelector('input');

    function toggleVisibility() {
        const isHidden = password.type === 'password';
        password.type = isHidden ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(isHidden));
        toggle.setAttribute('aria-label', isHidden ? 'Sembunyikan password' : 'Tampilkan password');
    }

    toggle.addEventListener('click', toggleVisibility);
    toggle.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleVisibility();
        }
    });
});
</script>
</body>
</html>
