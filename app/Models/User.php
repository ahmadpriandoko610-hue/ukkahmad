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
