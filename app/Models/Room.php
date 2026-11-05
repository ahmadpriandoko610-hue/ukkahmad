<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $table = 'rooms';

    protected $fillable = [
        'room_name',
        'location',
        'capacity',
        'pic_id',
        'description',
        'is_active',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Agenda yang menggunakan ruangan ini
     */
    public function events()
    {
        return $this->hasMany(
            Event::class,
            'room_id'
        );
    }

    /**
     * PIC yang bertanggung jawab atas ruangan
     */
    public function pic()
    {
        return $this->belongsTo(
            User::class,
            'pic_id'
        );
    }

    /**
     * Laporan kerusakan ruangan
     */
    public function complaints()
    {
        return $this->hasMany(
            Complaint::class,
            'room_id'
        );
    }

    /**
     * Accessor nama ruangan
     */
    public function getNamaRuanganAttribute()
    {
        return $this->room_name;
    }

    /**
     * Accessor lokasi
     */
    public function getLokasiAttribute()
    {
        return $this->location;
    }

    /**
     * Accessor keterangan
     */
    public function getKeteranganAttribute()
    {
        return $this->description;
    }
}