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

    /**
     * Memilah dan mengurutkan koleksi ruangan berdasarkan aturan resmi RAPI-Sekolah:
     * - Prioritas Pertama: Ruangan tanpa nomor selalu di paling atas (misal Lapangan Basket, Ruang Wijaya, Aula Bawah).
     * - Prioritas Kedua: Ruangan yang memiliki nomor diurutkan secara numerik dari terkecil ke terbesar
     *   (misal LAB 1, LAB 2, LAB 3, ..., LAB 9, LAB 10, LAB 11, ...).
     */
    public static function sortRooms($rooms)
    {
        if (empty($rooms)) {
            return collect();
        }

        $collection = $rooms instanceof \Illuminate\Support\Collection ? $rooms : collect($rooms);

        return $collection->sort(function ($a, $b) {
            $nameA = trim((string) ($a->room_name ?? $a->nama_ruangan ?? ''));
            $nameB = trim((string) ($b->room_name ?? $b->nama_ruangan ?? ''));

            $hasDigitA = (bool) preg_match('/\d/', $nameA);
            $hasDigitB = (bool) preg_match('/\d/', $nameB);

            // Prioritas 1: Ruangan tanpa nomor selalu di atas
            if (!$hasDigitA && $hasDigitB) {
                return -1;
            }
            if ($hasDigitA && !$hasDigitB) {
                return 1;
            }

            // Jika keduanya TIDAK memiliki nomor -> urutkan secara alfabetis / natural (case-insensitive)
            if (!$hasDigitA && !$hasDigitB) {
                return strnatcasecmp($nameA, $nameB);
            }

            // Prioritas 2: Keduanya memiliki nomor -> urutkan prefix kemudian angka numerik
            $parsedA = static::parseRoomNameForSorting($nameA);
            $parsedB = static::parseRoomNameForSorting($nameB);

            // Bandingkan prefix teks (misal 'lab' vs 'ruang')
            $prefixCmp = strcmp($parsedA['prefix'], $parsedB['prefix']);
            if ($prefixCmp !== 0) {
                return $prefixCmp;
            }

            // Bandingkan angka numerik (1, 2, ..., 9, 10, 11...)
            if ($parsedA['number'] !== $parsedB['number']) {
                return $parsedA['number'] <=> $parsedB['number'];
            }

            // Fallback jika prefix dan nomor identik (misal 'LAB 1' vs 'LAB1')
            return strnatcasecmp($nameA, $nameB);
        })->values();
    }

    /**
     * Parsing nama ruangan untuk ekstraksi prefix dan nomor numerik pertama.
     */
    public static function parseRoomNameForSorting(string $name): array
    {
        if (preg_match('/^(.*?)(?:\s*)(\d+)(.*)$/u', $name, $matches)) {
            $prefix = trim(preg_replace('/[^a-zA-Z0-9]+$/u', '', mb_strtolower($matches[1])));
            $number = (int) $matches[2];
            $suffix = trim(mb_strtolower($matches[3]));

            return [
                'prefix' => $prefix,
                'number' => $number,
                'suffix' => $suffix,
            ];
        }

        return [
            'prefix' => trim(mb_strtolower($name)),
            'number' => 0,
            'suffix' => '',
        ];
    }
}