<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PasswordResetController as WebPasswordResetController;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class PasswordResetController extends Controller
{
    public const CODE_TTL_MINUTES = 10;
    public const CODE_RESEND_SECONDS = 60;

    /**
     * Minta kode verifikasi 6 digit ke alamat email pengguna.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Masukkan alamat email.',
            'email.email' => 'Format email tidak valid.',
        ]);

        if (!WebPasswordResetController::smtpIsConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Email belum dapat dikirim. Administrator perlu mengatur SMTP pada file .env.',
            ], 503);
        }

        $email = Str::lower($validated['email']);
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            $existing = DB::table('password_reset_tokens')->where('email', $user->email)->first();
            if (
                $existing
                && $existing->created_at
                && Carbon::parse($existing->created_at)->gt(now()->subSeconds(self::CODE_RESEND_SECONDS))
            ) {
                $secondsPassed = abs(now()->diffInSeconds(Carbon::parse($existing->created_at)));
                $secondsRemaining = max(1, min(self::CODE_RESEND_SECONDS, self::CODE_RESEND_SECONDS - (int)$secondsPassed));

                return response()->json([
                    'success' => false,
                    'status' => 'cooldown_active',
                    'message' => "Pengiriman kode masih dibatasi. Harap tunggu {$secondsRemaining} detik sebelum meminta kode baru.",
                    'data' => [
                        'email' => $email,
                        'resend_cooldown_seconds' => $secondsRemaining,
                    ],
                ], 429);
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
            } catch (Throwable $e) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                Log::error('Pengiriman kode reset password API gagal: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Kode verifikasi gagal dikirim. Periksa konfigurasi SMTP dan coba lagi.',
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'status' => 'code_sent',
            'message' => 'Jika email terdaftar, kode verifikasi akan dikirimkan. Periksa kotak masuk atau folder spam.',
            'data' => [
                'email' => $email,
                'cooldown_seconds' => self::CODE_RESEND_SECONDS,
                'ttl_minutes' => self::CODE_TTL_MINUTES,
            ],
        ]);
    }

    /**
     * Verifikasi kode 6 digit dari email. Mengembalikan token reset sementara yang aman.
     */
    public function verifyCode(Request $request): JsonResponse
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
            return response()->json([
                'success' => false,
                'message' => 'Kode verifikasi salah atau sudah kedaluwarsa. Minta kode baru lalu coba lagi.',
            ], 422);
        }

        // Buat reset_token kriptografis sementara (valid 10 menit)
        $resetToken = 'rst_' . Str::random(48);
        $cacheKey = 'api_pwd_reset_' . hash('sha256', $resetToken);

        Cache::put(
            $cacheKey,
            [
                'email' => $record->email,
                'token_hash' => $record->token,
                'verified_at' => now()->timestamp,
            ],
            now()->addMinutes(self::CODE_TTL_MINUTES)
        );

        return response()->json([
            'success' => true,
            'message' => 'Kode verifikasi berhasil diverifikasi.',
            'data' => [
                'email' => $record->email,
                'reset_token' => $resetToken,
            ],
        ]);
    }

    /**
     * Atur password baru menggunakan reset_token yang telah diverifikasi.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'reset_token.required' => 'Token reset verifikasi wajib disertakan.',
            'password.required' => 'Masukkan password baru.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $resetToken = (string)$request->input('reset_token');
        $cacheKey = 'api_pwd_reset_' . hash('sha256', $resetToken);
        $cached = Cache::get($cacheKey);

        if (!$cached || !is_array($cached)) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi verifikasi telah kedaluwarsa atau tidak valid. Silakan minta kode baru.',
            ], 422);
        }

        $email = Str::lower($request->input('email'));
        if (strtolower($cached['email']) !== $email) {
            return response()->json([
                'success' => false,
                'message' => 'Identitas email tidak cocok dengan sesi verifikasi.',
            ], 422);
        }

        $record = DB::table('password_reset_tokens')->where('email', $cached['email'])->first();
        if (!$record || !hash_equals($cached['token_hash'], $record->token)) {
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'message' => 'Kode verifikasi telah kedaluwarsa atau sudah digunakan.',
            ], 422);
        }

        $user = User::where('email', $cached['email'])->first();
        if (!$user) {
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'message' => 'Akun pengguna tidak ditemukan.',
            ], 404);
        }

        // Perbarui password dengan hash aman, tanpa mengubah role, nama, atau profil
        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'remember_token' => Str::random(60),
        ])->save();

        // Hapus kode verifikasi dari database dan cache bukti verifikasi agar tidak bisa dipakai ulang
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        Cache::forget($cacheKey);

        // Cabut token API aktif pengguna jika ada
        $activeTokenHash = Cache::get('user_active_token_' . $user->id);
        if ($activeTokenHash) {
            Cache::forget('api_token_' . $activeTokenHash);
            Cache::forget('user_active_token_' . $user->id);
        }

        event(new PasswordReset($user));

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah. Silakan masuk dengan password baru.',
        ]);
    }
}

