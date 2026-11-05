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

            $request->session()->regenerate();

            if (Auth::user()->role == 'admin') {
    return redirect()->route('dashboard.admin');
}

if (Auth::user()->role == 'pic') {
    return redirect()->route('dashboard.pic');
}

if (Auth::user()->role === 'guru') {
    return redirect()->route('dashboard.guru');
}
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
