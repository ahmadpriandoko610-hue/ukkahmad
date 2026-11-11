<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Menampilkan halaman login
    public function login()
    {
        return view('auth.login');
    }

    // Proses login
    public function prosesLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt([
            'email' => $request->email,
            'password' => $request->password,
        ])) {
            $user = Auth::user();

            if ($user->role === 'admin') {
                $request->session()->regenerate();

                return redirect()->route('dashboard.admin');
            }

            if ($user->role === 'pic') {
                $request->session()->regenerate();

                return redirect()->route('dashboard.pic');
            }

            if ($user->role === 'guru') {
                $request->session()->regenerate();

                return redirect()->route('dashboard.guru');
            }

            $isSiswa = $user->role === 'siswa';

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $isSiswa
                ? 'Akun Siswa tidak memiliki akses dashboard web. Silakan gunakan aplikasi mobile RAPI-Sekolah atau lihat kalender publik tanpa login.'
                : 'Akun Anda tidak memiliki akses ke dashboard web.';

            return redirect()->route('login')
                ->withInput($request->only('email'))
                ->with('error', $message);
        }

        return back()->with('error', 'Email atau Password salah!');
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
