<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppNotificationLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DRY_RUN = 'dry_run';
    public const STATUS_SKIPPED_NO_CONSENT = 'skipped_no_consent';
    public const STATUS_SKIPPED_NOT_ALLOWLISTED = 'skipped_not_allowlisted';
    public const STATUS_UNCONFIGURED = 'unconfigured';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_AMBIGUOUS_TIMEOUT = 'ambiguous_timeout';
    public const STATUS_CONNECTION_FAILED = 'connection_failed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';

    /**
     * Status yang wajib memblokir pengiriman ulang otomatis untuk transisi yang sama.
     *
     * @var array<int, string>
     */
    public const DUPLICATE_BLOCKING_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_DELIVERED,
        self::STATUS_READ,
        self::STATUS_DRY_RUN,
        self::STATUS_AMBIGUOUS_TIMEOUT,
    ];

    protected $table = 'whatsapp_notification_logs';

    protected $attributes = [
        'attempts' => 0,
        'max_attempts' => 3,
    ];

    protected $fillable = [
        'idempotency_key',
        'event_id',
        'old_room_id',
        'new_room_id',
        'room_move_version',
        'transition_id',
        'recipient_user_id',
        'driver',
        'template_name',
        'recipient_phone_masked',
        'recipient_phone_hmac',
        'is_dry_run',
        'status',
        'meta_message_id',
        'http_status',
        'error_code',
        'error_message',
        'attempts',
        'max_attempts',
        'last_attempt_at',
        'next_retry_at',
        'delivered_at',
        'read_at',
        'reconciled_at',
        'reconciliation_note',
    ];

    protected $hidden = [
        'recipient_phone_hmac',
    ];

    protected function casts(): array
    {
        return [
            'is_dry_run' => 'boolean',
            'room_move_version' => 'integer',
            'http_status' => 'integer',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'reconciled_at' => 'datetime',
        ];
    }

    /**
     * Label status yang aman & akurat untuk tampilan Admin (membedakan accepted vs delivered).
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Sedang Diproses (Pending)',
            self::STATUS_DRY_RUN => 'Simulasi (Dry-Run)',
            self::STATUS_SKIPPED_NO_CONSENT => 'Dilewati (Belum Opt-In)',
            self::STATUS_SKIPPED_NOT_ALLOWLISTED => 'Dilewati (Di Luar Allowlist)',
            self::STATUS_UNCONFIGURED => 'Konfigurasi Belum Lengkap',
            self::STATUS_ACCEPTED => 'Diterima Meta (Menunggu Pengiriman)',
            self::STATUS_DELIVERED => 'Terkirim ke Perangkat',
            self::STATUS_READ => 'Dibaca Penerima',
            self::STATUS_AMBIGUOUS_TIMEOUT => 'Timeout Ambigu (Perlu Rekonsiliasi)',
            self::STATUS_CONNECTION_FAILED => 'Gagal Koneksi (Belum Terkirim)',
            self::STATUS_FAILED => 'Gagal Dikirim',
            default => (string) $this->status,
        };
    }

    public function isPermanentFailure(): bool
    {
        if ($this->status !== self::STATUS_FAILED) {
            return false;
        }

        if ($this->error_code === 'INVALID_META_RESPONSE') {
            return true;
        }

        return $this->http_status !== null
            && $this->http_status >= 400
            && $this->http_status < 500
            && $this->http_status !== 429;
    }

    public function isRetryableFailure(): bool
    {
        if ($this->is_dry_run || $this->attempts >= $this->max_attempts) {
            return false;
        }

        if ($this->status === self::STATUS_CONNECTION_FAILED) {
            return true;
        }

        return $this->status === self::STATUS_FAILED && (int) $this->http_status === 429;
    }

    public function needsReconciliation(): bool
    {
        if (in_array($this->status, [self::STATUS_PENDING, self::STATUS_AMBIGUOUS_TIMEOUT], true)) {
            return true;
        }

        return $this->status === self::STATUS_FAILED
            && $this->http_status !== null
            && $this->http_status >= 500
            && $this->http_status < 600
            && $this->reconciled_at === null;
    }

    /**
     * Menghitung HMAC-SHA256 berbasis APP_KEY untuk korelasi audit nomor penerima
     * tanpa menyimpan nomor telepon lengkap maupun hash SHA-256 mentah di tabel log.
     */
    public static function computePhoneHmac(?string $normalizedPhone): ?string
    {
        if ($normalizedPhone === null || trim($normalizedPhone) === '') {
            return null;
        }

        $appKey = (string) config('app.key', '');
        if ($appKey === '') {
            return null;
        }

        return hash_hmac('sha256', trim($normalizedPhone), $appKey);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function oldRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'old_room_id');
    }

    public function newRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'new_room_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}

