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
     * Daftar role yang dapat menyimpan nomor WhatsApp di kolom users.phone.
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
     * Normalisasi dan validasi nomor WhatsApp Indonesia ke format wa.me (628...).
     * Mengembalikan null apabila nomor kosong atau tidak valid.
     */
    public static function normalizeWhatsAppPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $trimmed = trim($phone);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\+?[0-9\s\-\(\)\.]+$/', $trimmed) !== 1) {
            return null;
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed);

        if (!is_string($digits) || $digits === '') {
            return null;
        }

        if ($hasPlus) {
            if (!str_starts_with($digits, '62') || str_starts_with($digits, '620')) {
                return null;
            }

            $normalized = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $normalized = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '62')) {
            if (str_starts_with($digits, '620')) {
                return null;
            }

            $normalized = $digits;
        } else {
            return null;
        }

        if (preg_match('/^628[0-9]{7,12}$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }

    /**
     * Model boot events:
     * Pastikan user selain role yang diizinkan (guru/pic) tidak menyimpan nomor WhatsApp di database.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (!static::canHaveWhatsAppPhone($user->role)) {
                $user->phone = null;
            }
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
