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

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Field yang boleh diisi secara mass assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
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
     * Model boot events:
     * Pastikan hanya role guru dan pic yang dapat menyimpan nomor WhatsApp ternormalisasi,
     * sementara role admin dan siswa selalu menyimpan NULL.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (!static::canHaveWhatsAppPhone($user->role)) {
                $user->phone = null;

                return;
            }

            $rawPhone = trim((string) ($user->phone ?? ''));
            $user->phone = $rawPhone !== '' ? static::normalizePhoneNumber($rawPhone) : null;
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
    
}
