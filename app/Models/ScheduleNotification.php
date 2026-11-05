<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleNotification extends Model
{
    protected $fillable = [
        'user_id',
        'event_id',
        'title',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * User penerima notifikasi
     */
    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /**
     * Agenda yang berkaitan dengan notifikasi
     */
    public function event()
    {
        return $this->belongsTo(
            Event::class,
            'event_id'
        );
    }
}