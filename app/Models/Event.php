<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Room;
use App\Models\ScheduleNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Event extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'event_name',
        'date',
        'end_date',
        'start_time',
        'end_time',
        'requester_id',
        'room_id',
        'group_id',
        'status',
        'approved_by',
        'move_reason',
        'room_move_version',
        'last_room_move_transition_id',
        'rejection_reason',
        'rejected_at',
        'description',
    ];

    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
        'room_move_version' => 'integer',
        'rejected_at' => 'datetime',
    ];

    /**
     * Agenda yang selesai dalam 24 jam terakhir atau belum selesai.
     * Agenda yang ditolak lebih dari 30 hari lalu juga tidak muncul di daftar aktif.
     * Data lama tetap tersimpan.
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        $cutoff = Carbon::now()->subDay();
        $rejectionCutoff = Carbon::now()->subDays(30);

        return $query
                ->where(function (Builder $query) use ($cutoff) {
                    $query->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(end_date, date)'), '>', $cutoff->toDateString())
                        ->orWhere(function (Builder $query) use ($cutoff) {
                            $query->whereDate(\Illuminate\Support\Facades\DB::raw('COALESCE(end_date, date)'), '=', $cutoff->toDateString())
                                ->where('end_time', '>=', $cutoff->format('H:i:s'));
                        });
                })
                ->where(function (Builder $query) use ($rejectionCutoff) {
                    $query->where('status', '!=', 'Ditolak')
                        ->orWhere('rejected_at', '>', $rejectionCutoff);
                });
    }

    /**
     * Scope khusus untuk tabel "Agenda Saya" Guru / Organisasi:
     * - Status Menunggu: tampil selama tanggal pelaksanaan belum terlewati (>= hari ini).
     *   Jika tanggal sudah lewat (< hari ini), otomatis tersembunyi.
     * - Status Sudah Terlaksana / Disetujui: tampil pada tanggal pelaksanaan dan H+1 (>= kemarin).
     *   Jika sudah lewat 1 hari setelah tanggal pelaksanaan, otomatis tersembunyi.
     * - Status Ditolak: tampil jika ditolak dalam 30 hari terakhir.
     * - Data di database TIDAK dihapus.
     */
    public function scopeVisibleInTeacherAgenda(Builder $query, ?Carbon $now = null): Builder
    {
        $now = $now ?: Carbon::now('Asia/Jakarta');
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();
        $rejectionCutoff = $now->copy()->subDays(30);

        return $query->where(function (Builder $q) use ($today, $yesterday, $rejectionCutoff) {
            // A. Status Menunggu: tampil jika belum terlewati (>= hari ini)
            $q->where(function (Builder $sub) use ($today) {
                $sub->where('status', 'Menunggu')
                    ->where(function (Builder $dateQ) use ($today) {
                        $dateQ->whereDate('end_date', '>=', $today)
                            ->orWhere(function (Builder $single) use ($today) {
                                $single->whereNull('end_date')
                                    ->whereDate('date', '>=', $today);
                            });
                    });
            })
            // B. Status Disetujui / Sudah Terlaksana: tampil pada hari pelaksanaan dan H+1 (>= kemarin)
            ->orWhere(function (Builder $sub) use ($yesterday) {
                $sub->whereIn('status', ['Disetujui', 'Sudah Terlaksana'])
                    ->where(function (Builder $dateQ) use ($yesterday) {
                        $dateQ->whereDate('end_date', '>=', $yesterday)
                            ->orWhere(function (Builder $single) use ($yesterday) {
                                $single->whereNull('end_date')
                                    ->whereDate('date', '>=', $yesterday);
                            });
                    });
            })
            // C. Status Ditolak: tampil jika ditolak dalam 30 hari terakhir (atau legacy data belum lewat hari ini)
            ->orWhere(function (Builder $sub) use ($today, $rejectionCutoff) {
                $sub->where('status', 'Ditolak')
                    ->where(function (Builder $rej) use ($today, $rejectionCutoff) {
                        $rej->where('rejected_at', '>', $rejectionCutoff)
                            ->orWhere(function (Builder $legacy) use ($today) {
                                $legacy->whereNull('rejected_at')
                                    ->where(function (Builder $dateQ) use ($today) {
                                        $dateQ->whereDate('end_date', '>=', $today)
                                            ->orWhere(function (Builder $single) use ($today) {
                                                $single->whereNull('end_date')
                                                    ->whereDate('date', '>=', $today);
                                            });
                                    });
                            });
                    });
            });
        });
    }

    /**
     * User yang mengajukan agenda
     */
    public function requester()
    {
        return $this->belongsTo(
            User::class,
            'requester_id'
        );
    }

    /**
     * Alias user untuk kompatibilitas
     * dengan Blade/controller lama
     */
    public function user()
    {
        return $this->belongsTo(
            User::class,
            'requester_id'
        );
    }

    /**
     * User yang menyetujui/menolak agenda
     */
    public function approver()
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    /**
     * Ruangan yang digunakan agenda
     */
    public function room()
    {
        return $this->belongsTo(
            Room::class,
            'room_id'
        );
    }

    /**
     * Alias ruangan untuk kompatibilitas
     * dengan Blade lama
     */
    public function ruangan()
    {
        return $this->belongsTo(
            Room::class,
            'room_id'
        );
    }

    /**
     * Notifikasi perpindahan ruangan
     */
    public function moveNotifications()
    {
        return $this->hasMany(
            ScheduleNotification::class,
            'event_id'
        );
    }

    /**
     * Log audit notifikasi WhatsApp pemindahan ruangan.
     */
    public function whatsappNotificationLogs()
    {
        return $this->hasMany(
            WhatsAppNotificationLog::class,
            'event_id'
        )->orderByDesc('id');
    }

    /**
     * Pengaduan kerusakan yang terkait dengan agenda
     */
    public function complaints()
    {
        return $this->hasMany(
            Complaint::class,
            'event_id'
        );
    }

    /**
     * Accessor judul
     */
    public function getJudulAttribute()
    {
        return $this->event_name;
    }

    /**
     * Accessor tanggal
     */
    public function getTanggalAttribute()
    {
        return $this->date;
    }

    /**
     * Accessor tanggal selesai
     */
    public function getTanggalSelesaiAttribute()
    {
        return $this->end_date ?? $this->date;
    }

    /**
     * Label rentang tanggal untuk tampilan
     */
    public function getFormattedDateRangeAttribute(): string
    {
        if ($this->end_date && $this->end_date->gt($this->date)) {
            return $this->date->format('d-m-Y') . ' s/d ' . $this->end_date->format('d-m-Y');
        }

        return $this->date ? $this->date->format('d-m-Y') : '-';
    }

    /**
     * Accessor jam mulai
     */
    public function getJamMulaiAttribute()
    {
        return $this->start_time;
    }

    /**
     * Accessor jam selesai
     */
    public function getJamSelesaiAttribute()
    {
        return $this->end_time;
    }

    /** Rentang sesi yang bersesuaian dengan jam tersimpan. */
    public function getSessionRangeAttribute(): string
    {
        if (empty($this->start_time) || empty($this->end_time) || !str_contains((string) $this->start_time, ':') || !str_contains((string) $this->end_time, ':')) {
            return '-';
        }

        $startMinutes = $this->minutesSinceMidnight((string) $this->start_time);
        $endMinutes = $this->minutesSinceMidnight((string) $this->end_time);
        $sessionStart = max(1, min(14, (int) floor(($startMinutes - 450) / 45) + 1));
        $sessionEnd = max($sessionStart, min(14, (int) ceil(($endMinutes - 450) / 45)));

        return "Sesi {$sessionStart} - Sesi {$sessionEnd}";
    }

    private function minutesSinceMidnight(string $time): int
    {
        $parts = explode(':', $time);
        $hours = (int) ($parts[0] ?? 0);
        $minutes = (int) ($parts[1] ?? 0);

        return ($hours * 60) + $minutes;
    }

    /**
     * Accessor status pelaksanaan agenda:
     * - Sedang Berlangsung (waktu saat ini berada dalam rentang jadwal kegiatan)
     * - Belum Terlaksana (jadwal kegiatan belum dimulai / di masa depan)
     * - Sudah Terlaksana (jadwal kegiatan telah selesai / di masa lalu)
     * - Menunggu / Ditolak (jika belum disetujui)
     */
    public function getStatusPelaksanaanAttribute(): string
    {
        if ($this->status === 'Menunggu') {
            return 'Menunggu';
        }

        if ($this->status === 'Ditolak') {
            return 'Ditolak';
        }

        if (!$this->date) {
            return $this->status ?? 'Belum Terlaksana';
        }

        $now = \Illuminate\Support\Carbon::now();

        $startDateStr = $this->date instanceof \DateTimeInterface
            ? $this->date->format('Y-m-d')
            : \Illuminate\Support\Carbon::parse($this->date)->format('Y-m-d');

        $endDateObj = $this->end_date ?: $this->date;
        $endDateStr = $endDateObj instanceof \DateTimeInterface
            ? $endDateObj->format('Y-m-d')
            : \Illuminate\Support\Carbon::parse($endDateObj)->format('Y-m-d');

        $startTimeStr = $this->start_time ? substr($this->start_time, 0, 8) : '00:00:00';
        $endTimeStr = $this->end_time ? substr($this->end_time, 0, 8) : '23:59:59';

        $startDateTime = \Illuminate\Support\Carbon::parse($startDateStr . ' ' . $startTimeStr);
        $endDateTime = \Illuminate\Support\Carbon::parse($endDateStr . ' ' . $endTimeStr);

        if ($now->lt($startDateTime)) {
            return 'Belum Terlaksana';
        } elseif ($now->gt($endDateTime)) {
            return 'Sudah Terlaksana';
        } else {
            return 'Sedang Berlangsung';
        }
    }

    public function getCreatorRoleAttribute(): string
    {
        return $this->requester?->role ?? $this->user?->role ?? 'guru';
    }

    public function getCreatorLabelAttribute(): string
    {
        return $this->creator_role === 'admin' ? 'Admin / Sekolah' : 'Guru / Organisasi';
    }

    /**
     * Mengambil seluruh event yang berasal dari pengajuan/booking yang sama
     */
    public function getSisterEvents()
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('events', 'group_id') && !empty($this->group_id)) {
            return static::where('group_id', $this->group_id)->get();
        }

        $dateStr = $this->date ? Carbon::parse($this->date)->toDateString() : null;
        $endDateStr = $this->end_date ? Carbon::parse($this->end_date)->toDateString() : $dateStr;

        $query = static::where('requester_id', $this->requester_id)
            ->where('event_name', $this->event_name)
            ->whereDate('date', $dateStr)
            ->whereTime('start_time', $this->start_time)
            ->whereTime('end_time', $this->end_time);

        if ($endDateStr) {
            $query->whereDate('end_date', $endDateStr);
        }

        return $query->get();
    }

    /**
     * Mengelompokkan event yang memiliki booking/jadwal multi-ruangan menjadi 1 data
     */
    public static function groupEventsForDisplay($events)
    {
        if (empty($events) || (is_object($events) && method_exists($events, 'isEmpty') && $events->isEmpty())) {
            return collect();
        }

        $collection = $events instanceof \Illuminate\Support\Collection ? $events : collect($events);

        return $collection->groupBy(function (Event $item) {
            if (!empty($item->group_id)) {
                return 'grp_' . $item->group_id;
            }

            $dateStr = $item->date ? Carbon::parse($item->date)->toDateString() : '';
            $endDateStr = $item->end_date ? Carbon::parse($item->end_date)->toDateString() : $dateStr;

            return implode('|', [
                $item->requester_id,
                trim(strtolower($item->event_name ?? '')),
                $dateStr,
                $endDateStr,
                substr($item->start_time ?? '', 0, 5),
                substr($item->end_time ?? '', 0, 5),
            ]);
        })->map(function ($group) {
            /** @var Event $primary */
            $primary = clone $group->first();

            $primary->all_events = $group;
            $primary->event_ids = $group->pluck('id')->all();

            $rooms = $group->map(function ($e) {
                return $e->room ?: $e->ruangan;
            })->filter()->unique('id')->values();

            $primary->rooms = $rooms;
            $primary->setRelation('rooms', $rooms);
            $primary->rooms_label = $rooms->map(fn($r) => $r->room_name ?: $r->nama_ruangan)->filter()->implode(', ');
            $primary->setAttribute('rooms_label', $primary->rooms_label);
            $primary->setAttribute('event_ids', $primary->event_ids);

            if ($rooms->count() > 1 && $primary->rooms_label) {
                $virtualRoom = clone ($primary->room ?: ($primary->ruangan ?: new Room()));
                $virtualRoom->room_name = $primary->rooms_label;
                $virtualRoom->nama_ruangan = $primary->rooms_label;
                $primary->setRelation('room', $virtualRoom);
                $primary->setRelation('ruangan', $virtualRoom);
            }

            // Status agregat jika berbeda
            $statuses = $group->pluck('status')->unique();
            if ($statuses->count() > 1) {
                if ($statuses->contains('Menunggu')) {
                    $primary->status = 'Menunggu';
                } elseif ($statuses->contains('Disetujui')) {
                    $primary->status = 'Disetujui';
                }
            }

            return $primary;
        })->values();
    }
}