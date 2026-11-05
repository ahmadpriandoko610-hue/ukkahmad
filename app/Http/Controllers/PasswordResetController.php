<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    private const CODE_TTL_MINUTES = 10;
    private const CODE_RESEND_SECONDS = 60;

    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['email' => ['required', 'email']],
            [
                'email.required' => 'Masukkan alamat email.',
                'email.email' => 'Format email tidak valid.',
            ]
        );

        if (!$this->smtpIsConfigured()) {
            return back()->withInput()->withErrors([
                'email' => 'Email belum dapat dikirim. Administrator perlu mengatur SMTP pada file .env.',
            ]);
        }

        $email = Str::lower($validated['email']);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            $existing = DB::table('password_reset_tokens')->where('email', $user->email)->first();
            if ($existing && $existing->created_at && Carbon::parse($existing->created_at)->gt(now()->subSeconds(self::CODE_RESEND_SECONDS))) {
                return redirect()->route('password.code.form', ['email' => $user->email])
                    ->with('status', 'Jika email terdaftar, kode verifikasi sudah dikirim. Tunggu 1 menit sebelum meminta kode baru.');
            }

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($code), 'created_at' => now()]
            );

            try {
                Mail::raw(
                    "Kode verifikasi reset password RAPI-Sekolah Anda adalah: {$code}\n\nKode berlaku selama 10 menit. Jangan berikan kode ini kepada siapa pun.",
                    function ($message) use ($user): void {
                        $message->to($user->email)
                            ->subject('Kode Verifikasi Reset Password RAPI-Sekolah');
                    }
                );
            } catch (Throwable) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                Log::error('Pengiriman kode reset password gagal.');

                return back()->withInput()->withErrors([
                    'email' => 'Kode verifikasi gagal dikirim. Periksa konfigurasi SMTP dan coba lagi.',
                ]);
            }
        }

        return redirect()->route('password.code.form', ['email' => $email])
            ->with('status', 'Jika email terdaftar, kode verifikasi telah dikirim. Periksa kotak masuk atau folder spam.');
    }

    public function codeForm(Request $request): View
    {
        return view('auth.verify-password-code', [
            'email' => $request->query('email', ''),
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'regex:/^[0-9]{6}$/'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'code.required' => 'Masukkan kode verifikasi.',
            'code.regex' => 'Kode verifikasi harus terdiri dari 6 angka.',
        ]);

        $email = Str::lower($validated['email']);
        $record = DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', [$email])->first();

        if (
            !$record
            || !$record->created_at
            || Carbon::parse($record->created_at)->lte(now()->subMinutes(self::CODE_TTL_MINUTES))
            || !Hash::check($validated['code'], $record->token)
        ) {
            return back()->withInput()->withErrors([
                'code' => 'Kode salah atau sudah kedaluwarsa. Minta kode baru lalu coba lagi.',
            ]);
        }

        $request->session()->put([
            'password_reset_verified_email' => $record->email,
            'password_reset_verified_at' => now()->timestamp,
            'password_reset_verified_token_hash' => $record->token,
        ]);

        return redirect()->route('password.reset.form');
    }

    public function resetForm(Request $request, ?string $token = null): View|RedirectResponse
    {
        // Tautan reset lama tidak lagi digunakan setelah beralih ke kode email.
        if ($token !== null) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Minta kode verifikasi baru untuk mengatur ulang password.',
            ]);
        }

        if (!$this->hasFreshVerifiedCode($request)) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Verifikasi kode email terlebih dahulu.',
            ]);
        }

        return view('auth.reset-password', [
            'email' => $request->session()->get('password_reset_verified_email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        if (!$this->hasFreshVerifiedCode($request)) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Sesi verifikasi kedaluwarsa. Minta dan verifikasi kode baru.',
            ]);
        }

        $email = $request->session()->get('password_reset_verified_email');
        $request->merge(['email' => $email]);
        $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'password.required' => 'Masukkan password baru.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        $user = User::where('email', $email)->first();
        if (!$user) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Akun tidak ditemukan. Periksa email dan coba lagi.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        $request->session()->forget([
            'password_reset_verified_email',
            'password_reset_verified_at',
            'password_reset_verified_token_hash',
        ]);
        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', 'Password berhasil diubah. Silakan masuk dengan password baru.');
    }

    public static function smtpIsConfigured(): bool
    {
        $smtp = config('mail.mailers.smtp');

        return config('mail.default') === 'smtp'
            && filled($smtp['host'] ?? null)
            && filled($smtp['username'] ?? null)
            && filled($smtp['password'] ?? null)
            && filled(config('mail.from.address'));
    }

    private function hasFreshVerifiedCode(Request $request): bool
    {
        $email = $request->session()->get('password_reset_verified_email');
        $verifiedAt = $request->session()->get('password_reset_verified_at');
        $verifiedTokenHash = $request->session()->get('password_reset_verified_token_hash');
        $record = $email
            ? DB::table('password_reset_tokens')->where('email', $email)->first()
            : null;

        return $email
            && $verifiedAt
            && $verifiedTokenHash
            && $record
            && hash_equals($verifiedTokenHash, $record->token)
            && now()->timestamp - (int) $verifiedAt < self::CODE_TTL_MINUTES * 60
            && $record->created_at
            && Carbon::parse($record->created_at)->gt(now()->subMinutes(self::CODE_TTL_MINUTES));
    }
}
