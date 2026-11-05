<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login RAPI-Sekolah</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

<a href="{{ route('calendar.public') }}" class="back-calendar">
    <span>←</span>
    <span>Kembali ke Kalender</span>
</a>

<div class="login-container">

    <div class="login-card">

        <div class="login-header">
    <h1>Login</h1>
    <p>Silakan masuk ke sistem RAPI-Sekolah</p>
</div>

        @if(session('error'))
            <div class="alert-error">
                {{ session('error') }}
            </div>
        @endif

        @if(session('status'))
            <div class="alert-success" role="status">
                {{ session('status') }}
            </div>
        @endif

        <form action="{{ route('login.proses') }}" method="POST">
            @csrf

            <div class="form-group">
                <label>Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="Masukkan email"
                    required>
            </div>

            <div class="form-group">
                <label>Password</label>

                <div class="password-wrapper">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="form-control"
                        placeholder="Masukkan Password"
                        required>

                    <span class="toggle-password" id="togglePassword">👁</span>
                </div>
            </div>

            <button type="submit" class="btn-login">
                Login
            </button>

            <div class="login-links">
                <a href="{{ route('password.request') }}">Lupa Password?</a>
            </div>

        </form>

    </div>

</div>

<script>
const password = document.getElementById('password');
const toggle = document.getElementById('togglePassword');

toggle.addEventListener('click', function () {
    if (password.type === 'password') {
        password.type = 'text';
        toggle.textContent = '✖';
    } else {
        password.type = 'password';
        toggle.textContent = '👁';
    }
});
</script>

</body>
</html>
