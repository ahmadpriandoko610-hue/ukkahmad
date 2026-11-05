<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password | RAPI-Sekolah</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-container">
    <main class="login-card">
        <header class="login-header">
            <h1>Lupa Password?</h1>
            <p>Masukkan email akun Anda untuk menerima kode verifikasi reset password.</p>
        </header>

        @if (session('status'))
            <div class="alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('password.email') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" class="form-control"
                       value="{{ old('email') }}" placeholder="Masukkan email" required autocomplete="email" autofocus>
            </div>
            <button type="submit" class="btn-login">Kirim Kode Verifikasi</button>
        </form>

        <div class="login-links"><a href="{{ route('login') }}">Kembali ke halaman login</a></div>
    </main>
</div>
</body>
</html>
