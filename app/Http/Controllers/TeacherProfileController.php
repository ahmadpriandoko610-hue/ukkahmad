<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherProfileController extends Controller
{
    /**
     * Menampilkan halaman Pengaturan Notifikasi WhatsApp khusus role Guru / Organisasi.
     */
    public function whatsappSettings(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'guru', 403, 'Anda tidak memiliki hak akses.');

        return view('teacher.whatsapp-settings', compact('user'));
    }

    /**
     * Menyimpan pembaruan nomor WhatsApp dan status persetujuan (opt-in / opt-out) Guru.
     */
    public function updateWhatsAppSettings(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'guru', 403, 'Anda tidak memiliki hak akses.');

        $request->validate([
            'phone' => [
                'nullable',
                'string',
                'max:25',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $raw = trim((string) $value);
                    if ($raw !== '' && User::normalizePhoneNumber($raw) === null) {
                        $fail('Format nomor WhatsApp tidak valid. Gunakan format 08..., 628..., atau +628... dengan 10-14 digit lokal.');
                    }
                },
            ],
            'whatsapp_opt_in' => ['nullable', 'boolean'],
        ], [
            'phone.string' => 'Nomor WhatsApp harus berupa teks.',
            'phone.max' => 'Nomor WhatsApp maksimal 25 karakter.',
        ]);

        $existingPhone = User::normalizePhoneNumber($user->phone);
        $hasSubmittedPhone = $request->filled('phone');
        $submittedPhone = $hasSubmittedPhone
            ? User::normalizePhoneNumber((string) $request->input('phone'))
            : null;

        $targetPhone = $hasSubmittedPhone ? $submittedPhone : $existingPhone;
        $phoneChanged = $hasSubmittedPhone && $submittedPhone !== $existingPhone;
        $wantsOptIn = $request->boolean('whatsapp_opt_in');

        if ($wantsOptIn && $targetPhone === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'phone' => 'Silakan masukkan nomor WhatsApp yang valid terlebih dahulu sebelum menyetujui penerimaan notifikasi.',
                ]);
        }

        if ($phoneChanged) {
            if ($wantsOptIn) {
                $user->grantWhatsAppConsent('guru_self_service', $targetPhone);
                $user->save();

                return redirect()
                    ->route('guru.whatsapp.settings')
                    ->with('success', 'Nomor WhatsApp baru dan persetujuan notifikasi perubahan ruangan berhasil disimpan.');
            }

            $user->phone = $targetPhone;
            $user->revokeWhatsAppConsent();
            $user->save();

            return redirect()
                ->route('guru.whatsapp.settings')
                ->with('success', 'Nomor WhatsApp berhasil diperbarui. Persetujuan lama dicabut otomatis; silakan centang persetujuan ulang bila bersedia menerima notifikasi.');
        }

        if ($wantsOptIn) {
            $user->grantWhatsAppConsent('guru_self_service');
            $user->save();

            return redirect()
                ->route('guru.whatsapp.settings')
                ->with('success', 'Persetujuan notifikasi WhatsApp perubahan ruangan berhasil diaktifkan.');
        }

        $user->revokeWhatsAppConsent();
        $user->save();

        return redirect()
            ->route('guru.whatsapp.settings')
            ->with('success', 'Pengaturan disimpan. Status persetujuan notifikasi WhatsApp saat ini tidak aktif.');
    }

    /**
     * Mencabut persetujuan notifikasi WhatsApp Guru kapan saja tanpa menghapus nomor.
     */
    public function revokeWhatsAppConsent(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'guru', 403, 'Anda tidak memiliki hak akses.');

        $user->revokeWhatsAppConsent();
        $user->save();

        return redirect()
            ->route('guru.whatsapp.settings')
            ->with('success', 'Persetujuan notifikasi WhatsApp telah dicabut.');
    }

    /**
     * Menghapus nomor WhatsApp tersimpan milik Guru dan mencabut persetujuan terkait.
     */
    public function removeWhatsAppPhone(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'guru', 403, 'Anda tidak memiliki hak akses.');

        $user->phone = null;
        $user->revokeWhatsAppConsent();
        $user->save();

        return redirect()
            ->route('guru.whatsapp.settings')
            ->with('success', 'Nomor WhatsApp berhasil dihapus dan persetujuan notifikasi telah dicabut.');
    }
}

