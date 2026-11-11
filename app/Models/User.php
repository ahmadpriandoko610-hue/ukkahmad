<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use App\Models\Event;
use App\Models\Room;
use App\Models\Complaint;
use App\Models\Notification;
use App\Models\ScheduleNotification;
use App\Models\WhatsAppNotificationLog;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    private bool $allowConsentOnPhoneChange = false;

    /**
     * Field yang boleh diisi secara mass assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'whatsapp_opt_in',
        'whatsapp_opt_in_at',
        'whatsapp_opt_in_source',
    ];

    /**
     * Field yang disembunyikan.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email',
        'phone',
    ];

    /**
     * Casting atribut.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'whatsapp_opt_in' => 'boolean',
            'whatsapp_opt_in_at' => 'datetime',
        ];
    }

    /**
     * Daftar role yang diizinkan menyimpan nomor WhatsApp di kolom users.phone.
     *
     * @var array<int, string>
     */
    public const WHATSAPP_ENABLED_ROLES = ['guru', 'pic'];

    /**
     * Cek apakah role berhak menyimpan nomor WhatsApp.
     */
    public static function canHaveWhatsAppPhone(?string $role): bool
    {
        return in_array($role, self::WHATSAPP_ENABLED_ROLES, true);
    }

    /**
     * Validasi dan normalisasi nomor WhatsApp Indonesia ke format E.164 tanpa tanda '+': 628...
     * Mengembalikan null jika kosong atau tidak valid.
     */
    public static function normalizePhoneNumber(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        // Izinkan awalan opsional '+' diikuti digit dan pemisah umum (spasi, tanda hubung, titik, kurung).
        if (!preg_match('/^\+?[0-9\s\-\(\)\.]+$/', $trimmed)) {
            return null;
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed);

        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if ($hasPlus) {
            // Input bertanda '+' wajib kode negara +62 dan tidak boleh +620...
            if (!str_starts_with($digits, '62') || str_starts_with($digits, '620')) {
                return null;
            }

            $normalized = $digits;
        } elseif (str_starts_with($digits, '08')) {
            $normalized = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '62')) {
            if (str_starts_with($digits, '620')) {
                return null;
            }

            $normalized = $digits;
        } else {
            return null;
        }

        // Nomor seluler Indonesia E.164: 628 + 8..12 digit (total 11..15 digit, setara 08 + 8..12 digit).
        if (!preg_match('/^628[0-9]{8,12}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Format nomor telepon tersamar (mis. 6281******890) agar aman ditampilkan di UI dan log.
     */
    public static function maskPhoneNumber(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        $normalized = static::normalizePhoneNumber($trimmed) ?? preg_replace('/\s+/', '', $trimmed);
        $length = strlen((string) $normalized);
        if ($length === 0) {
            return null;
        }

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr((string) $normalized, 0, 4)
            . str_repeat('*', $length - 7)
            . substr((string) $normalized, -3);
    }

    public function getMaskedPhoneAttribute(): ?string
    {
        return static::maskPhoneNumber($this->phone);
    }

    /**
     * Cek apakah user memiliki persetujuan aktif untuk menerima notifikasi WhatsApp pemindahan ruangan.
     * Hanya berlaku untuk role guru dengan nomor WhatsApp valid dan status opt-in aktif.
     */
    public function hasWhatsAppConsent(): bool
    {
        return $this->role === 'guru'
            && (bool) $this->whatsapp_opt_in
            && static::normalizePhoneNumber($this->phone) !== null;
    }

    /**
     * Mencatat persetujuan eksplisit Guru terhadap notifikasi WhatsApp pemindahan ruangan.
     */
    public function grantWhatsAppConsent(string $source = 'guru_self_service', ?string $newPhone = null): self
    {
        if ($newPhone !== null) {
            $normalizedNewPhone = static::normalizePhoneNumber($newPhone);
            $this->phone = $normalizedNewPhone;
            $this->allowConsentOnPhoneChange = true;
        }

        $normalizedCurrent = static::normalizePhoneNumber($this->phone);
        if ($this->role !== 'guru' || $normalizedCurrent === null) {
            $this->allowConsentOnPhoneChange = false;

            return $this->revokeWhatsAppConsent();
        }

        $this->phone = $normalizedCurrent;
        $this->whatsapp_opt_in = true;
        $this->whatsapp_opt_in_at = now();
        $this->whatsapp_opt_in_source = substr(trim($source), 0, 50) ?: 'guru_self_service';

        return $this;
    }

    /**
     * Mencabut persetujuan notifikasi WhatsApp.
     */
    public function revokeWhatsAppConsent(): self
    {
        $this->whatsapp_opt_in = false;
        $this->whatsapp_opt_in_at = null;
        $this->whatsapp_opt_in_source = null;

        return $this;
    }

    /**
     * Model boot events:
     * Pastikan hanya role guru dan pic yang dapat menyimpan nomor WhatsApp ternormalisasi,
     * sementara role admin dan siswa selalu menyimpan NULL, dan persetujuan hanya berlaku
     * bagi role guru dengan pencabutan otomatis saat nomor dihapus, diganti, atau role berubah.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (!static::canHaveWhatsAppPhone($user->role)) {
                $user->phone = null;
                $user->revokeWhatsAppConsent();

                return;
            }

            $rawPhone = trim((string) ($user->phone ?? ''));
            $normalizedPhone = $rawPhone !== '' ? static::normalizePhoneNumber($rawPhone) : null;
            $user->phone = $normalizedPhone;

            // Persetujuan notifikasi pemindahan ruangan hanya berlaku untuk role guru
            // dan wajib dicabut apabila nomor WhatsApp kosong/dihapus.
            if ($user->role !== 'guru' || $normalizedPhone === null) {
                $user->revokeWhatsAppConsent();

                return;
            }

            // Jika nomor WhatsApp diganti ke nomor lain tanpa pembaruan consent eksplisit,
            // cabut persetujuan lama agar nomor baru wajib mendapatkan persetujuan ulang.
            if ($user->exists) {
                $originalPhone = static::normalizePhoneNumber($user->getOriginal('phone'));
                if ($originalPhone !== $normalizedPhone && !$user->allowConsentOnPhoneChange) {
                    $user->revokeWhatsAppConsent();

                    return;
                }
            }

            if ($user->whatsapp_opt_in) {
                $user->whatsapp_opt_in = true;
                $user->whatsapp_opt_in_at = $user->whatsapp_opt_in_at ?? now();
                $user->whatsapp_opt_in_source = $user->whatsapp_opt_in_source ?: 'guru_self_service';
            } else {
                $user->revokeWhatsAppConsent();
            }
        });

        static::saved(function (User $user) {
            $user->allowConsentOnPhoneChange = false;
        });
    }

    public function getRoleLabelAttribute(): string
    {
        if ($this->role === 'guru') {
            return 'Guru / Organisasi';
        }
        if ($this->role === 'siswa') {
            return 'Siswa';
        }
        return ucfirst($this->role);
    }

    /**
     * Agenda yang dibuat/diajukan oleh user.
     */
    public function events()
    {
        return $this->hasMany(
            Event::class,
            'requester_id'
        );
    }

    /**
     * Ruangan yang menjadi tanggung jawab PIC.
     */
    public function rooms()
    {
        return $this->hasMany(
            Room::class,
            'pic_id'
        );
    }

    /**
     * Pengaduan yang dibuat oleh user/guru.
     */
    public function reportedComplaints()
    {
        return $this->hasMany(
            Complaint::class,
            'reporter_id'
        );
    }

    /**
     * Pengaduan yang ditangani oleh PIC.
     */
    public function handledComplaints()
    {
        return $this->hasMany(
            Complaint::class,
            'handled_by'
        );
    }

    /**
     * Notifikasi perpindahan/jadwal.
     */
    public function scheduleNotifications()
    {
        return $this->hasMany(
            ScheduleNotification::class,
            'user_id'
        );
    }

    /**
     * Notifikasi perubahan status pengaduan.
     */
    public function complaintNotifications(): HasMany
    {
        return $this->hasMany(
            Notification::class,
            'user_id'
        );
    }

    /**
     * Riwayat log notifikasi WhatsApp pemindahan ruangan.
     */
    public function whatsappNotificationLogs(): HasMany
    {
        return $this->hasMany(
            WhatsAppNotificationLog::class,
            'recipient_user_id'
        );
    }
}
