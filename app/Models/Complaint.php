<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Event;

class Complaint extends Model
{
    protected $fillable = [
        'room_id',
        'event_id',
        'reporter_id',
        'description',
        'photo_path',
        'status_repair',
        'repair_note',
        'repaired_at',
        'handled_by',
        'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'repaired_at' => 'datetime',
    ];

    public function scopeVisibleInActiveList(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('status_repair', '!=', 'Sudah Diperbaiki')
                ->orWhere('repaired_at', '>=', now()->subDay());
        });
    }

    // Relasi ke Room
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // User yang membuat pengaduan
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    // User yang menangani pengaduan
    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function getKeteranganPerbaikanAttribute(): ?string
    {
        return $this->repair_note;
    }
}