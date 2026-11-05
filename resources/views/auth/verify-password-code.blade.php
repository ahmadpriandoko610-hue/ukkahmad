<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Password | RAPI-Sekolah</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-container">
    <main class="login-card">
        <header class="login-header">
            <h1>Kode Verifikasi</h1>
            <p>Masukkan kode 6 angka yang dikirim ke email Anda. Kode berlaku selama 10 menit.</p>
        </header>

        @if (session('status'))
            <div class="alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('password.code.verify') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" class="form-control"
                       value="{{ old('email', $email) }}" required autocomplete="email" readonly>
            </div>
            <div class="form-group">
                <label for="code">Kode Verifikasi</label>
                <input id="code" type="text" name="code" class="form-control"
                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6" minlength="6"
                       placeholder="Masukkan 6 angka" required autocomplete="one-time-code" autofocus>
            </div>
            <button type="submit" class="btn-login">Verifikasi Kode</button>
        </form>

        <div class="login-links"><a href="{{ route('password.request') }}">Minta kode baru</a></div>
    </main>
</div>
</body>
</html>
