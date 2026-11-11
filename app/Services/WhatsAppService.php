<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use App\Models\WhatsAppNotificationLog;
use App\Services\WhatsApp\MetaCloudApiDriver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppService
{
    private const JAKARTA_TIMEZONE = 'Asia/Jakarta';

    /** @var array<int, string> */
    private const INDONESIAN_MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function __construct(private readonly ?MetaCloudApiDriver $driver = null)
    {
    }

    public function isConfigured(): bool
    {
        return $this->resolvedDriver()->isConfigured();
    }

    public function isDryRun(): bool
    {
        return (bool) config('services.whatsapp.dry_run', true);
    }

    public function requireConsent(): bool
    {
        return (bool) config('services.whatsapp.require_consent', true);
    }

    public function isTestAllowlistOnly(): bool
    {
        return (bool) config('services.whatsapp.test_allowlist_only', true);
    }

    public function configuredDriverName(): string
    {
        $driver = trim((string) config('services.whatsapp.driver', MetaCloudApiDriver::DRIVER_NAME));

        return $driver !== '' ? $driver : MetaCloudApiDriver::DRIVER_NAME;
    }

    public function normalizePhone(?string $phone): ?string
    {
        return User::normalizePhoneNumber($phone);
    }

    /**
     * Daftar nomor allowlist uji yang telah dinormalisasi ke format E.164 tanpa '+' (628...).
     *
     * @return array<int, string>
     */
    public function normalizedAllowlist(): array
    {
        $raw = config('services.whatsapp.test_allowlist', []);

        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        if (!is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $entry) {
            if (!is_string($entry) && !is_numeric($entry)) {
                continue;
            }

            $phone = $this->normalizePhone((string) $entry);
            if ($phone !== null) {
                $normalized[] = $phone;
            }
        }

        return array_values(array_unique($normalized));
    }

    public function isPhoneInAllowlist(string $normalizedPhone): bool
    {
        if (!$this->isTestAllowlistOnly()) {
            return true;
        }

        return in_array($normalizedPhone, $this->normalizedAllowlist(), true);
    }

    /**
     * Kumpulkan nomor WhatsApp penerima untuk pemindahan ruangan oleh Admin.
     * Hanya mengambil Event::requester jika role === 'guru' dan nomor valid,
     * tanpa duplikasi dan tanpa menyertakan PIC ruangan lama/baru, Admin, atau Guru lain.
     *
     * @return array<int, array{user_id: int|null, name: string, role: string|null, phone: string}>
     */
    public function resolveRecipientsForRoomMove(Event $event, ?Room $oldRoom = null, ?Room $newRoom = null): array
    {
        $event->loadMissing('requester');

        $requester = $event->requester;
        if (!$requester || $requester->role !== 'guru') {
            return [];
        }

        $normalizedPhone = $this->normalizePhone($requester->phone ?? null);
        if ($normalizedPhone === null) {
            return [];
        }

        $uniqueByPhone = [];
        $uniqueByPhone[$normalizedPhone] = [
            'user_id' => $requester->id ?? null,
            'name' => (string) ($requester->name ?? 'Guru / Organisasi'),
            'role' => $requester->role,
            'phone' => $normalizedPhone,
        ];

        return array_values($uniqueByPhone);
    }

    /**
     * Membangun 6 parameter terurut dan tersanitasi untuk template Meta `notifikasi_pemindahan_ruangan`.
     *
     * @return array<int, string>
     */
    public function buildRoomMoveParameters(
        Event $event,
        string $oldRoomName,
        string $newRoomName,
        ?string $reason = null
    ): array {
        return $this->resolvedDriver()->buildRoomMoveParameters(
            (string) ($event->event_name ?? ''),
            $this->formatIndonesianDateRange($event),
            $this->formatWibTimeRange($event),
            $oldRoomName,
            $newRoomName,
            $reason
        );
    }

    /**
     * Membangun pratinjau payload resmi Meta Cloud API tanpa mengirim permintaan HTTP.
     *
     * @return array<string, mixed>
     */
    public function previewTemplatePayload(
        Event $event,
        string $normalizedPhone,
        string $oldRoomName,
        string $newRoomName,
        ?string $reason = null
    ): array {
        $driver = $this->resolvedDriver();
        $parameters = $this->buildRoomMoveParameters($event, $oldRoomName, $newRoomName, $reason);

        return $driver->buildTemplatePayload($normalizedPhone, $parameters);
    }

    /**
     * Format pesan teks ringkas pemindahan ruangan (untuk referensi/notifikasi non-template).
     */
    public function formatRoomChangeMessage(
        Event $event,
        string $oldRoomName,
        string $newRoomName,
        ?string $reason = null
    ): string {
        $parameters = $this->buildRoomMoveParameters($event, $oldRoomName, $newRoomName, $reason);

        return implode("\n", [
            'Informasi Perubahan Ruangan RAPI-Sekolah',
            '',
            'Agenda: ' . $parameters[0],
            'Tanggal: ' . $parameters[1],
            'Waktu: ' . $parameters[2],
            'Ruangan sebelumnya: ' . $parameters[3],
            'Ruangan baru: ' . $parameters[4],
            'Alasan: ' . $parameters[5],
            '',
            'Mohon perhatikan perubahan lokasi agenda tersebut.',
            '',
            'Terima kasih.',
        ]);
    }

    /**
     * Memproses notifikasi WhatsApp pemindahan ruangan melalui Meta Cloud API (atau dry-run)
     * dengan urutan guard ketat, klaim pre-HTTP berbasis unique constraint (status pending),
     * idempotency berbasis transition ID atomik, serta ketahanan terhadap kegagalan log & timeout.
     *
     * @return array{
     *     status: string,
     *     sent_count: int,
     *     failed_count: int,
     *     recipients_count: int,
     *     is_dry_run: bool,
     *     meta_message_id: ?string,
     *     log_id: ?int,
     *     transition_id?: ?string,
     *     message: string
     * }
     */
    public function notifyRoomChanged(
        Event $event,
        ?Room $oldRoom,
        ?Room $newRoom,
        ?string $reason = null,
        ?string $idempotencyKey = null,
        ?string $transitionId = null
    ): array {
        $validatedExplicitKey = $this->validateExplicitIdempotencyKey($idempotencyKey);
        $isDryRun = $this->isDryRun();
        $driver = $this->resolvedDriver();
        $driverName = $this->configuredDriverName();
        $templateName = $driver->templateName() !== '' ? $driver->templateName() : null;

        // 1. Ruangan tidak berubah -> lewati tanpa HTTP maupun log WA
        if ($oldRoom && $newRoom && (int) $oldRoom->id === (int) $newRoom->id) {
            return $this->makeResult('skipped_unchanged', 0, 0, 0, $isDryRun, null, null, 'Ruangan agenda tidak berubah.');
        }

        // 2. Hanya Guru / Organisasi pemilik agenda yang berhak menerima notifikasi pemindahan ruangan
        $event->loadMissing('requester');
        $requester = $event->requester;

        if (!$requester || $requester->role !== 'guru') {
            Log::info('Notifikasi WhatsApp perubahan ruangan dilewati: pemilik agenda bukan role guru.', [
                'event_id' => $event->id,
                'old_room_id' => $oldRoom?->id,
                'new_room_id' => $newRoom?->id,
                'requester_role' => $requester?->role,
            ]);

            return $this->makeResult(
                'skipped_non_guru',
                0,
                0,
                0,
                $isDryRun,
                null,
                null,
                'Notifikasi WhatsApp hanya dikirim untuk agenda yang diajukan oleh Guru / Organisasi.'
            );
        }

        // 3. Periksa nomor WhatsApp Guru pemilik agenda
        $recipients = $this->resolveRecipientsForRoomMove($event, $oldRoom, $newRoom);

        if (empty($recipients)) {
            Log::warning('Notifikasi WhatsApp perubahan ruangan tidak dikirim: Guru pemilik agenda belum memiliki nomor WhatsApp valid.', [
                'event_id' => $event->id,
                'requester_id' => $requester->id,
                'old_room_id' => $oldRoom?->id,
                'new_room_id' => $newRoom?->id,
            ]);

            return $this->makeResult(
                'no_recipients',
                0,
                0,
                0,
                $isDryRun,
                null,
                null,
                'Notifikasi WhatsApp tidak dikirim karena nomor WhatsApp Guru pemilik agenda belum tersedia atau tidak valid.'
            );
        }

        $recipient = $recipients[0];
        $normalizedPhone = $recipient['phone'];
        $maskedPhone = User::maskPhoneNumber($normalizedPhone) ?? $this->maskPhone($normalizedPhone);
        $phoneHmac = WhatsAppNotificationLog::computePhoneHmac($normalizedPhone);

        // 4. Pastikan Guru sudah memberikan persetujuan opt-in eksplisit
        if ($this->requireConsent() && !$requester->hasWhatsAppConsent()) {
            $log = $this->recordAuditLog(
                $this->buildAuditIdempotencyKey($event, $recipient['user_id'], $oldRoom?->id, $newRoom?->id, $validatedExplicitKey, $transitionId),
                [
                    'event_id' => $event->id,
                    'old_room_id' => $oldRoom?->id,
                    'new_room_id' => $newRoom?->id,
                    'room_move_version' => $event->room_move_version ?: null,
                    'transition_id' => $this->normalizeTransitionReference($transitionId, $event->last_room_move_transition_id),
                    'recipient_user_id' => $recipient['user_id'],
                    'driver' => $driverName,
                    'template_name' => $templateName,
                    'recipient_phone_masked' => $maskedPhone,
                    'recipient_phone_hmac' => $phoneHmac,
                    'is_dry_run' => $isDryRun,
                    'status' => WhatsAppNotificationLog::STATUS_SKIPPED_NO_CONSENT,
                    'error_code' => 'CONSENT_REQUIRED',
                    'error_message' => 'Guru pemilik agenda belum memberikan persetujuan (opt-in) notifikasi WhatsApp.',
                    'attempts' => 0,
                ]
            );

            Log::info('Notifikasi WhatsApp perubahan ruangan dilewati: Guru belum memberikan persetujuan opt-in.', [
                'event_id' => $event->id,
                'recipient_user_id' => $recipient['user_id'],
                'recipient_phone' => $maskedPhone,
            ]);

            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_SKIPPED_NO_CONSENT,
                0,
                0,
                1,
                $isDryRun,
                null,
                $log?->id,
                'Notifikasi WhatsApp tidak dikirim karena Guru pemilik agenda belum menyetujui notifikasi WhatsApp.'
            );
        }

        // 5. Pastikan fitur diaktifkan dan menggunakan driver resmi Meta Cloud API
        $enabled = (bool) config('services.whatsapp.enabled', false);
        if (!$enabled || $driverName !== MetaCloudApiDriver::DRIVER_NAME) {
            $errorCode = !$enabled ? 'WHATSAPP_DISABLED' : 'UNSUPPORTED_DRIVER';
            $log = $this->recordAuditLog(
                $this->buildAuditIdempotencyKey($event, $recipient['user_id'], $oldRoom?->id, $newRoom?->id, $validatedExplicitKey, $transitionId),
                [
                    'event_id' => $event->id,
                    'old_room_id' => $oldRoom?->id,
                    'new_room_id' => $newRoom?->id,
                    'room_move_version' => $event->room_move_version ?: null,
                    'transition_id' => $this->normalizeTransitionReference($transitionId, $event->last_room_move_transition_id),
                    'recipient_user_id' => $recipient['user_id'],
                    'driver' => $driverName,
                    'template_name' => $templateName,
                    'recipient_phone_masked' => $maskedPhone,
                    'recipient_phone_hmac' => $phoneHmac,
                    'is_dry_run' => $isDryRun,
                    'status' => WhatsAppNotificationLog::STATUS_UNCONFIGURED,
                    'error_code' => $errorCode,
                    'error_message' => !$enabled
                        ? 'Fitur notifikasi WhatsApp belum diaktifkan (WHATSAPP_ENABLED=false).'
                        : 'Driver WhatsApp tidak valid; wajib menggunakan meta_cloud_api.',
                    'attempts' => 0,
                ]
            );

            Log::info('Notifikasi WhatsApp perubahan ruangan tidak dikirim karena fitur nonaktif atau driver tidak sesuai.', [
                'event_id' => $event->id,
                'driver' => $driverName,
                'enabled' => $enabled,
                'recipient_phone' => $maskedPhone,
            ]);

            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_UNCONFIGURED,
                0,
                0,
                1,
                $isDryRun,
                null,
                $log?->id,
                'Notifikasi WhatsApp tidak dikirim karena konfigurasi Meta Cloud API belum aktif atau belum lengkap.'
            );
        }

        // 6. Periksa allowlist saat mode pembatasan pengujian aktif
        if (!$this->isPhoneInAllowlist($normalizedPhone)) {
            $log = $this->recordAuditLog(
                $this->buildAuditIdempotencyKey($event, $recipient['user_id'], $oldRoom?->id, $newRoom?->id, $validatedExplicitKey, $transitionId),
                [
                    'event_id' => $event->id,
                    'old_room_id' => $oldRoom?->id,
                    'new_room_id' => $newRoom?->id,
                    'room_move_version' => $event->room_move_version ?: null,
                    'transition_id' => $this->normalizeTransitionReference($transitionId, $event->last_room_move_transition_id),
                    'recipient_user_id' => $recipient['user_id'],
                    'driver' => $driverName,
                    'template_name' => $templateName,
                    'recipient_phone_masked' => $maskedPhone,
                    'recipient_phone_hmac' => $phoneHmac,
                    'is_dry_run' => $isDryRun,
                    'status' => WhatsAppNotificationLog::STATUS_SKIPPED_NOT_ALLOWLISTED,
                    'error_code' => 'NOT_IN_ALLOWLIST',
                    'error_message' => 'Nomor penerima tidak termasuk dalam daftar allowlist uji.',
                    'attempts' => 0,
                ]
            );

            Log::info('Notifikasi WhatsApp perubahan ruangan dilewati: nomor belum masuk allowlist uji.', [
                'event_id' => $event->id,
                'recipient_user_id' => $recipient['user_id'],
                'recipient_phone' => $maskedPhone,
            ]);

            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_SKIPPED_NOT_ALLOWLISTED,
                0,
                0,
                1,
                $isDryRun,
                null,
                $log?->id,
                'Notifikasi WhatsApp tidak dikirim karena nomor Guru pemilik agenda belum masuk allowlist uji.'
            );
        }

        // 7. Validasi kelengkapan nama dan bahasa template sebelum dry-run maupun live send
        if (!$driver->hasCompleteTemplateConfiguration()) {
            $log = $this->recordAuditLog(
                $this->buildAuditIdempotencyKey($event, $recipient['user_id'], $oldRoom?->id, $newRoom?->id, $validatedExplicitKey, $transitionId),
                [
                    'event_id' => $event->id,
                    'old_room_id' => $oldRoom?->id,
                    'new_room_id' => $newRoom?->id,
                    'room_move_version' => $event->room_move_version ?: null,
                    'transition_id' => $this->normalizeTransitionReference($transitionId, $event->last_room_move_transition_id),
                    'recipient_user_id' => $recipient['user_id'],
                    'driver' => $driverName,
                    'template_name' => $templateName,
                    'recipient_phone_masked' => $maskedPhone,
                    'recipient_phone_hmac' => $phoneHmac,
                    'is_dry_run' => $isDryRun,
                    'status' => WhatsAppNotificationLog::STATUS_UNCONFIGURED,
                    'error_code' => 'TEMPLATE_INCOMPLETE',
                    'error_message' => 'Nama template atau kode bahasa template Meta Cloud API belum lengkap.',
                    'attempts' => 0,
                ]
            );

            Log::warning('Notifikasi WhatsApp tidak dikirim: konfigurasi template Meta Cloud API belum lengkap.', [
                'event_id' => $event->id,
                'recipient_phone' => $maskedPhone,
            ]);

            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_UNCONFIGURED,
                0,
                0,
                1,
                $isDryRun,
                null,
                $log?->id,
                'Notifikasi WhatsApp tidak dikirim karena konfigurasi template Meta Cloud API belum lengkap.'
            );
        }

        $oldRoomName = $oldRoom?->room_name ?: 'Ruangan sebelumnya';
        $newRoomName = $newRoom?->room_name ?: 'Ruangan baru';
        $parameters = $this->buildRoomMoveParameters($event, $oldRoomName, $newRoomName, $reason);

        // 8. Mode simulasi dry-run -> klaim deterministik via unique constraint tanpa HTTP request
        if ($isDryRun) {
            // Bangun payload untuk memverifikasi kelengkapan struktur template sebelum klaim dry-run
            $driver->buildTemplatePayload($normalizedPhone, $parameters);

            $identity = $this->resolveTransitionIdentity(
                $event,
                (int) $recipient['user_id'],
                $oldRoom?->id,
                $newRoom?->id,
                true,
                $validatedExplicitKey,
                $transitionId
            );

            $claimResult = $this->claimNotificationSlot($identity['idempotency_key'], [
                'event_id' => $event->id,
                'old_room_id' => $oldRoom?->id,
                'new_room_id' => $newRoom?->id,
                'room_move_version' => $identity['room_move_version'],
                'transition_id' => $identity['transition_id'],
                'recipient_user_id' => $recipient['user_id'],
                'driver' => $driverName,
                'template_name' => $templateName,
                'recipient_phone_masked' => $maskedPhone,
                'recipient_phone_hmac' => $phoneHmac,
                'is_dry_run' => true,
                'status' => WhatsAppNotificationLog::STATUS_DRY_RUN,
                'attempts' => 0,
            ], $normalizedPhone);

            if ($claimResult['outcome'] === 'duplicate') {
                return $this->makeResult(
                    'skipped_duplicate',
                    0,
                    0,
                    1,
                    true,
                    null,
                    $claimResult['existing_log']?->id,
                    'Simulasi dry-run notifikasi WhatsApp untuk pemindahan ruangan ini sudah tercatat sebelumnya.',
                    $identity['transition_id']
                );
            }

            if ($claimResult['outcome'] === 'claim_error') {
                return $this->makeResult(
                    WhatsAppNotificationLog::STATUS_FAILED,
                    0,
                    1,
                    1,
                    true,
                    null,
                    null,
                    'Gagal mencatat klaim simulasi dry-run ke log database.',
                    $identity['transition_id']
                );
            }

            $log = $claimResult['log'];

            Log::info('Simulasi dry-run notifikasi WhatsApp perubahan ruangan berhasil dicatat (tanpa kirim HTTP).', [
                'event_id' => $event->id,
                'recipient_user_id' => $recipient['user_id'],
                'recipient_phone' => $maskedPhone,
                'template_name' => $templateName,
                'transition_id' => $identity['transition_id'],
            ]);

            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_DRY_RUN,
                0,
                0,
                1,
                true,
                null,
                $log?->id,
                'Simulasi dry-run berhasil dicatat di log audit; pesan tidak dikirim ke WhatsApp penerima.',
                $identity['transition_id']
            );
        }

        // 9. Mode live: pastikan kredensial & endpoint Meta Cloud API lengkap
        if (!$driver->hasCompleteLiveConfiguration()) {
            $log = $this->recordAuditLog(
                $this->buildAuditIdempotencyKey($event, $recipient['user_id'], $oldRoom?->id, $newRoom?->id, $validatedExplicitKey, $transitionId),
                [
                    'event_id' => $event->id,
                    'old_room_id' => $oldRoom?->id,
                    'new_room_id' => $newRoom?->id,
                    'room_move_version' => $event->room_move_version ?: null,
                    'transition_id' => $this->normalizeTransitionReference($transitionId, $event->last_room_move_transition_id),
                    'recipient_user_id' => $recipient['user_id'],
                    'driver' => $driverName,
                    'template_name' => $templateName,
                    'recipient_phone_masked' => $maskedPhone,
                    'recipient_phone_hmac' => $phoneHmac,
                    'is_dry_run' => false,
                    'status' => WhatsAppNotificationLog::STATUS_UNCONFIGURED,
                    'error_code' => 'CREDENTIALS_INCOMPLETE',
                    'error_message' => 'Phone Number ID, Access Token, atau Graph API endpoint Meta Cloud API belum lengkap.',
                    'attempts' => 0,
                ]
            );

            Log::warning('Notifikasi WhatsApp tidak dikirim: kredensial Meta Cloud API belum lengkap.', [
                'event_id' => $event->id,
                'recipient_phone' => $maskedPhone,
            ]);

            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_UNCONFIGURED,
                0,
                0,
                1,
                false,
                null,
                $log?->id,
                'Notifikasi WhatsApp tidak dikirim karena konfigurasi Meta Cloud API belum lengkap.'
            );
        }

        // 10. Pre-HTTP Claim `pending` berbasis unique constraint database SEBELUM request HTTP
        $identity = $this->resolveTransitionIdentity(
            $event,
            (int) $recipient['user_id'],
            $oldRoom?->id,
            $newRoom?->id,
            false,
            $validatedExplicitKey,
            $transitionId
        );

        $claimedAt = now();
        $claimResult = $this->claimNotificationSlot($identity['idempotency_key'], [
            'event_id' => $event->id,
            'old_room_id' => $oldRoom?->id,
            'new_room_id' => $newRoom?->id,
            'room_move_version' => $identity['room_move_version'],
            'transition_id' => $identity['transition_id'],
            'recipient_user_id' => $recipient['user_id'],
            'driver' => $driverName,
            'template_name' => $templateName,
            'recipient_phone_masked' => $maskedPhone,
            'recipient_phone_hmac' => $phoneHmac,
            'is_dry_run' => false,
            'status' => WhatsAppNotificationLog::STATUS_PENDING,
            'meta_message_id' => null,
            'http_status' => null,
            'error_code' => null,
            'error_message' => null,
            'attempts' => 1,
            'max_attempts' => 3,
            'last_attempt_at' => $claimedAt,
            'next_retry_at' => null,
        ], $normalizedPhone);

        if ($claimResult['outcome'] === 'duplicate') {
            $existingLog = $claimResult['existing_log'];
            Log::info('Notifikasi WhatsApp perubahan ruangan dilewati oleh pre-HTTP idempotency claim.', [
                'event_id' => $event->id,
                'recipient_user_id' => $recipient['user_id'],
                'recipient_phone' => $maskedPhone,
                'existing_status' => $existingLog?->status,
                'transition_id' => $identity['transition_id'],
            ]);

            return $this->makeResult(
                'skipped_duplicate',
                0,
                0,
                1,
                false,
                null,
                $existingLog?->id,
                'Permintaan notifikasi WhatsApp untuk pemindahan ruangan ini sudah memiliki klaim atau pernah diproses sebelumnya.',
                $identity['transition_id']
            );
        }

        if ($claimResult['outcome'] !== 'claimed' || $claimResult['log'] === null) {
            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_FAILED,
                0,
                1,
                1,
                false,
                null,
                null,
                'Gagal membuat klaim pengiriman notifikasi WhatsApp sebelum menghubungi Meta Cloud API.',
                $identity['transition_id']
            );
        }

        /** @var WhatsAppNotificationLog $claimLog */
        $claimLog = $claimResult['log'];

        // 11. Kirim permintaan sinkron ke Meta WhatsApp Cloud API (klaim pending sudah terkomit, tanpa transaksi DB terbuka)
        $sendResult = $driver->sendTemplateMessage($normalizedPhone, $parameters);

        return $this->processPostSendClaimUpdate(
            $claimLog,
            $sendResult,
            $normalizedPhone,
            $maskedPhone,
            $driver->accessToken(),
            false
        );
    }

    /**
     * Mengambil log yang memerlukan pemeriksaan/rekonsiliasi manual (pending tertinggal,
     * ambiguous_timeout, atau HTTP 5xx yang belum direkonsiliasi).
     *
     * @return EloquentCollection<int, WhatsAppNotificationLog>
     */
    public function findLogsNeedingReconciliation(int $pendingOlderThanMinutes = 5): EloquentCollection
    {
        $pendingCutoff = now()->subMinutes(max(0, $pendingOlderThanMinutes));

        return WhatsAppNotificationLog::query()
            ->whereNull('reconciled_at')
            ->where(function ($query) use ($pendingCutoff) {
                $query->where('status', WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT)
                    ->orWhere(function ($pendingQuery) use ($pendingCutoff) {
                        $pendingQuery->where('status', WhatsAppNotificationLog::STATUS_PENDING)
                            ->where(function ($timeQuery) use ($pendingCutoff) {
                                $timeQuery->whereNull('last_attempt_at')
                                    ->orWhere('last_attempt_at', '<=', $pendingCutoff);
                            });
                    })
                    ->orWhere(function ($fiveXxQuery) {
                        $fiveXxQuery->where('status', WhatsAppNotificationLog::STATUS_FAILED)
                            ->where('http_status', '>=', 500)
                            ->where('http_status', '<', 600);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Prosedur rekonsiliasi status log `pending`, `ambiguous_timeout`, atau `5xx` setelah verifikasi
     * hasil penerimaan dari Meta WhatsApp Manager / webhook tanpa menghapus klaim maupun mengizinkan kirim ulang otomatis.
     */
    public function reconcileNotificationLog(
        WhatsAppNotificationLog $log,
        string $resolvedStatus,
        ?string $note = null,
        ?string $metaMessageId = null
    ): WhatsAppNotificationLog {
        $allowedTargetStatuses = [
            WhatsAppNotificationLog::STATUS_ACCEPTED,
            WhatsAppNotificationLog::STATUS_DELIVERED,
            WhatsAppNotificationLog::STATUS_READ,
            WhatsAppNotificationLog::STATUS_FAILED,
        ];

        if (!in_array($resolvedStatus, $allowedTargetStatuses, true)) {
            throw new \InvalidArgumentException('Status hasil rekonsiliasi tidak diizinkan.');
        }

        if (!$log->needsReconciliation()) {
            throw new \RuntimeException('Log notifikasi tidak berada pada status yang membutuhkan rekonsiliasi.');
        }

        $now = now();
        $cleanNote = trim((string) ($note ?? ''));
        if ($cleanNote !== '') {
            $cleanNote = $this->resolvedDriver()->redactSensitiveData($cleanNote, $this->resolvedDriver()->accessToken());
        } else {
            $cleanNote = 'Direkonsiliasi secara manual.';
        }

        $updateData = [
            'status' => $resolvedStatus,
            'next_retry_at' => null,
            'reconciled_at' => $now,
            'reconciliation_note' => $cleanNote,
        ];

        $cleanMessageId = trim((string) ($metaMessageId ?? ''));
        if ($cleanMessageId !== '') {
            $updateData['meta_message_id'] = substr($cleanMessageId, 0, 128);
        }

        if (in_array($resolvedStatus, [WhatsAppNotificationLog::STATUS_ACCEPTED, WhatsAppNotificationLog::STATUS_DELIVERED, WhatsAppNotificationLog::STATUS_READ], true)) {
            $updateData['error_code'] = null;
            $updateData['error_message'] = null;
        }

        if ($resolvedStatus === WhatsAppNotificationLog::STATUS_DELIVERED) {
            $updateData['delivered_at'] = $log->delivered_at ?? $now;
        } elseif ($resolvedStatus === WhatsAppNotificationLog::STATUS_READ) {
            $updateData['delivered_at'] = $log->delivered_at ?? $now;
            $updateData['read_at'] = $log->read_at ?? $now;
        }

        $log->forceFill($updateData)->save();

        Log::info('Rekonsiliasi status log notifikasi WhatsApp selesai dilakukan.', [
            'log_id' => $log->id,
            'event_id' => $log->event_id,
            'transition_id' => $log->transition_id,
            'resolved_status' => $resolvedStatus,
            'recipient_phone' => $log->recipient_phone_masked,
        ]);

        return $log->fresh() ?? $log;
    }

    /**
     * Kebijakan retry eksplisit dan terkontrol menggunakan identitas transisi & baris klaim yang sama.
     * Hanya mengizinkan status rate_limited (HTTP 429) atau kegagalan koneksi pra-kirim yang dapat dibuktikan.
     * Status ambiguous_timeout, pending, accepted, 400/401 permanen, serta 5xx tidak pernah di-retry otomatis.
     *
     * @return array{
     *     status: string,
     *     sent_count: int,
     *     failed_count: int,
     *     recipients_count: int,
     *     is_dry_run: bool,
     *     meta_message_id: ?string,
     *     log_id: ?int,
     *     transition_id?: ?string,
     *     message: string
     * }
     */
    public function retryFailedNotification(WhatsAppNotificationLog $log): array
    {
        if (!$log->isRetryableFailure()) {
            return $this->makeResult(
                'skipped_not_retryable',
                0,
                0,
                1,
                (bool) $log->is_dry_run,
                $log->meta_message_id,
                $log->id,
                'Log notifikasi ini tidak memenuhi syarat kebijakan retry terkontrol.',
                $log->transition_id
            );
        }

        $event = Event::with('requester')->find($log->event_id);
        $oldRoom = $log->old_room_id ? Room::find($log->old_room_id) : null;
        $newRoom = $log->new_room_id ? Room::find($log->new_room_id) : null;

        if (!$event || !$event->requester || $event->requester->role !== 'guru') {
            return $this->makeResult('skipped_non_guru', 0, 0, 0, false, null, $log->id, 'Pemilik agenda tidak valid untuk retry.', $log->transition_id);
        }

        $normalizedPhone = $this->normalizePhone($event->requester->phone);
        $driver = $this->resolvedDriver();
        if (
            $normalizedPhone === null
            || ($this->requireConsent() && !$event->requester->hasWhatsAppConsent())
            || !(bool) config('services.whatsapp.enabled', false)
            || $this->configuredDriverName() !== MetaCloudApiDriver::DRIVER_NAME
            || !$this->isPhoneInAllowlist($normalizedPhone)
            || !$driver->hasCompleteLiveConfiguration()
        ) {
            return $this->makeResult('skipped_guard', 0, 0, 1, false, null, $log->id, 'Guard penerima/konfigurasi tidak lagi terpenuhi saat retry.', $log->transition_id);
        }

        $currentAttempts = (int) $log->attempts;
        $retryClaimedAt = now();

        $claimedRows = WhatsAppNotificationLog::query()
            ->whereKey($log->id)
            ->whereIn('status', [
                WhatsAppNotificationLog::STATUS_CONNECTION_FAILED,
                WhatsAppNotificationLog::STATUS_FAILED,
            ])
            ->where('attempts', $currentAttempts)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->update([
                'status' => WhatsAppNotificationLog::STATUS_PENDING,
                'attempts' => $currentAttempts + 1,
                'last_attempt_at' => $retryClaimedAt,
                'next_retry_at' => null,
            ]);

        if ($claimedRows === 0) {
            return $this->makeResult(
                'skipped_duplicate',
                0,
                0,
                1,
                false,
                null,
                $log->id,
                'Slot retry sudah diklaim proses lain atau tidak lagi memenuhi syarat.',
                $log->transition_id
            );
        }

        $claimLog = $log->fresh();
        if (!$claimLog) {
            return $this->makeResult(WhatsAppNotificationLog::STATUS_FAILED, 0, 1, 1, false, null, $log->id, 'Gagal memuat klaim retry.', $log->transition_id);
        }

        $parameters = $this->buildRoomMoveParameters(
            $event,
            $oldRoom?->room_name ?: 'Ruangan sebelumnya',
            $newRoom?->room_name ?: 'Ruangan baru',
            $event->move_reason
        );

        $sendResult = $driver->sendTemplateMessage($normalizedPhone, $parameters);
        $maskedPhone = User::maskPhoneNumber($normalizedPhone) ?? $this->maskPhone($normalizedPhone);

        return $this->processPostSendClaimUpdate(
            $claimLog,
            $sendResult,
            $normalizedPhone,
            $maskedPhone,
            $driver->accessToken(),
            true
        );
    }

    /**
     * @param array<string, mixed> $sendResult
     * @return array{
     *     status: string,
     *     sent_count: int,
     *     failed_count: int,
     *     recipients_count: int,
     *     is_dry_run: bool,
     *     meta_message_id: ?string,
     *     log_id: ?int,
     *     transition_id?: ?string,
     *     message: string
     * }
     */
    private function processPostSendClaimUpdate(
        WhatsAppNotificationLog $claimLog,
        array $sendResult,
        string $normalizedPhone,
        string $maskedPhone,
        string $token,
        bool $isRetry
    ): array {
        $attemptedAt = now();
        $sendStatus = (string) ($sendResult['status'] ?? WhatsAppNotificationLog::STATUS_FAILED);

        if ($sendStatus === WhatsAppNotificationLog::STATUS_ACCEPTED) {
            $updated = $this->finalizeClaimLog(
                $claimLog,
                [
                    'status' => WhatsAppNotificationLog::STATUS_ACCEPTED,
                    'meta_message_id' => $sendResult['meta_message_id'],
                    'http_status' => $sendResult['http_status'],
                    'error_code' => null,
                    'error_message' => null,
                    'last_attempt_at' => $attemptedAt,
                    'next_retry_at' => null,
                ],
                $normalizedPhone,
                $token
            );

            if (!$updated) {
                return $this->makeResult(
                    WhatsAppNotificationLog::STATUS_PENDING,
                    0,
                    1,
                    1,
                    false,
                    $sendResult['meta_message_id'],
                    $claimLog->id,
                    'Meta Cloud API mengembalikan status accepted, namun pembaruan status log database gagal; klaim pending dipertahankan untuk mencegah pengiriman ulang dan memerlukan rekonsiliasi.',
                    $claimLog->transition_id
                );
            }

            Log::info(
                $isRetry
                    ? 'Retry notifikasi WhatsApp perubahan ruangan diterima antrean Meta Cloud API.'
                    : 'Notifikasi WhatsApp perubahan ruangan diterima antrean Meta Cloud API.',
                [
                    'event_id' => $claimLog->event_id,
                    'log_id' => $claimLog->id,
                    'transition_id' => $claimLog->transition_id,
                    'recipient_user_id' => $claimLog->recipient_user_id,
                    'recipient_phone' => $maskedPhone,
                    'http_status' => $sendResult['http_status'],
                    'meta_message_id' => $sendResult['meta_message_id'],
                ]
            );

            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_ACCEPTED,
                1,
                0,
                1,
                false,
                $sendResult['meta_message_id'],
                $claimLog->id,
                'Notifikasi dikirim ke Meta Cloud API dan diterima sistem antrean Meta (accepted; menunggu pengiriman ke perangkat penerima).',
                $claimLog->transition_id
            );
        }

        if ($sendStatus === WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT) {
            $updated = $this->finalizeClaimLog(
                $claimLog,
                [
                    'status' => WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT,
                    'meta_message_id' => null,
                    'http_status' => null,
                    'error_code' => $sendResult['error_code'] ?? 'TIMEOUT_AMBIGUOUS',
                    'error_message' => $sendResult['error_message'],
                    'last_attempt_at' => $attemptedAt,
                    'next_retry_at' => null,
                ],
                $normalizedPhone,
                $token
            );

            if (!$updated) {
                return $this->makeResult(
                    WhatsAppNotificationLog::STATUS_PENDING,
                    0,
                    1,
                    1,
                    false,
                    null,
                    $claimLog->id,
                    'Request Meta Cloud API mengalami timeout ambigu dan pembaruan status log gagal; klaim pending dipertahankan untuk rekonsiliasi tanpa retry otomatis.',
                    $claimLog->transition_id
                );
            }

            Log::warning('Pengiriman notifikasi WhatsApp mengalami timeout ambigu (status penerimaan Meta belum pasti).', [
                'event_id' => $claimLog->event_id,
                'log_id' => $claimLog->id,
                'transition_id' => $claimLog->transition_id,
                'recipient_user_id' => $claimLog->recipient_user_id,
                'recipient_phone' => $maskedPhone,
                'error_code' => $sendResult['error_code'] ?? 'TIMEOUT_AMBIGUOUS',
            ]);

            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT,
                0,
                1,
                1,
                false,
                null,
                $claimLog->id,
                'Request ke Meta Cloud API mengalami timeout; hasil penerimaan pesan belum pasti (ambiguous_timeout) dan tidak dikirim ulang otomatis.',
                $claimLog->transition_id
            );
        }

        $targetStatus = match ($sendStatus) {
            WhatsAppNotificationLog::STATUS_CONNECTION_FAILED => WhatsAppNotificationLog::STATUS_CONNECTION_FAILED,
            WhatsAppNotificationLog::STATUS_UNCONFIGURED => WhatsAppNotificationLog::STATUS_UNCONFIGURED,
            default => WhatsAppNotificationLog::STATUS_FAILED,
        };

        $maxAttempts = max(1, (int) ($claimLog->max_attempts ?: 3));
        $retryable = (bool) ($sendResult['retryable'] ?? false)
            && (int) $claimLog->attempts < $maxAttempts;
        $retryAfterSeconds = (int) ($sendResult['retry_after_seconds'] ?? 300);
        $nextRetryAt = $retryable ? $attemptedAt->copy()->addSeconds($retryAfterSeconds) : null;

        $updated = $this->finalizeClaimLog(
            $claimLog,
            [
                'status' => $targetStatus,
                'meta_message_id' => null,
                'http_status' => $sendResult['http_status'],
                'error_code' => $sendResult['error_code'],
                'error_message' => $sendResult['error_message'],
                'last_attempt_at' => $attemptedAt,
                'next_retry_at' => $nextRetryAt,
            ],
            $normalizedPhone,
            $token
        );

        if (!$updated) {
            return $this->makeResult(
                WhatsAppNotificationLog::STATUS_PENDING,
                0,
                1,
                1,
                false,
                null,
                $claimLog->id,
                'Pengiriman ke Meta Cloud API gagal dan pembaruan status log ke database gagal; klaim pending dipertahankan untuk rekonsiliasi.',
                $claimLog->transition_id
            );
        }

        Log::error('Pengiriman notifikasi WhatsApp perubahan ruangan ke Meta Cloud API gagal.', [
            'event_id' => $claimLog->event_id,
            'log_id' => $claimLog->id,
            'transition_id' => $claimLog->transition_id,
            'recipient_user_id' => $claimLog->recipient_user_id,
            'recipient_phone' => $maskedPhone,
            'status' => $targetStatus,
            'http_status' => $sendResult['http_status'],
            'error_code' => $sendResult['error_code'],
        ]);

        $message = $targetStatus === WhatsAppNotificationLog::STATUS_CONNECTION_FAILED
            ? 'Koneksi ke Meta Cloud API gagal sebelum request terkirim; perubahan ruangan agenda dan notifikasi internal tetap tersimpan.'
            : 'Gagal menghubungi Meta Cloud API; perubahan ruangan agenda dan notifikasi internal tetap tersimpan.';

        return $this->makeResult(
            $targetStatus,
            0,
            1,
            1,
            false,
            null,
            $claimLog->id,
            $message,
            $claimLog->transition_id
        );
    }

    private function validateExplicitIdempotencyKey(?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $trimmed = trim($idempotencyKey);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/[\r\n\t\x00-\x1F\x7F]/', $trimmed) === 1 || strlen($trimmed) > 255) {
            throw new \InvalidArgumentException('Explicit idempotency key tidak valid.');
        }

        return $trimmed;
    }

    private function normalizeTransitionReference(?string $transitionId, ?string $eventTransitionId): ?string
    {
        $candidate = trim((string) ($transitionId ?: ($eventTransitionId ?? '')));

        return $candidate !== '' ? substr($candidate, 0, 64) : null;
    }

    /**
     * Menghitung identitas transisi dan idempotency key deterministik:
     * 1. Explicit key jika disediakan.
     * 2. Atomic transition ID dari transaksi pemindahan ruangan Web/API (prioritas utama).
     * 3. Transition ID yang sudah tersimpan di kolom events.last_room_move_transition_id.
     * 4. Fallback terukur khusus panggilan service langsung tanpa metadata transisi controller.
     *
     * @return array{idempotency_key: string, transition_id: string, room_move_version: ?int}
     */
    private function resolveTransitionIdentity(
        Event $event,
        int $recipientUserId,
        ?int $oldRoomId,
        ?int $newRoomId,
        bool $isDryRun,
        ?string $validatedExplicitKey,
        ?string $transitionId
    ): array {
        $modeLabel = $isDryRun ? 'dry_run' : 'live';
        $eventMoveVersion = (int) ($event->room_move_version ?? 0);

        if ($validatedExplicitKey !== null) {
            return [
                'idempotency_key' => hash('sha256', 'explicit|' . $validatedExplicitKey),
                'transition_id' => substr($validatedExplicitKey, 0, 64),
                'room_move_version' => $eventMoveVersion > 0 ? $eventMoveVersion : null,
            ];
        }

        $explicitTransitionId = trim((string) ($transitionId ?? ''));
        if ($explicitTransitionId !== '') {
            $boundedTransition = substr($explicitTransitionId, 0, 64);

            return [
                'idempotency_key' => hash('sha256', sprintf(
                    'event:%d|mode:%s|transition:%s|recipient:%d',
                    (int) $event->id,
                    $modeLabel,
                    $boundedTransition,
                    $recipientUserId
                )),
                'transition_id' => $boundedTransition,
                'room_move_version' => $eventMoveVersion > 0 ? $eventMoveVersion : null,
            ];
        }

        $persistedTransitionId = trim((string) ($event->last_room_move_transition_id ?? ''));
        if ($persistedTransitionId !== '' && $eventMoveVersion > 0 && ($newRoomId === null || (int) $event->room_id === $newRoomId)) {
            $boundedPersisted = substr($persistedTransitionId, 0, 64);

            return [
                'idempotency_key' => hash('sha256', sprintf(
                    'event:%d|mode:%s|transition:%s|recipient:%d',
                    (int) $event->id,
                    $modeLabel,
                    $boundedPersisted,
                    $recipientUserId
                )),
                'transition_id' => $boundedPersisted,
                'room_move_version' => $eventMoveVersion,
            ];
        }

        // Fallback untuk panggilan service langsung di luar transaksi controller
        $canonicalLogs = WhatsAppNotificationLog::query()
            ->where('event_id', $event->id)
            ->where('is_dry_run', $isDryRun)
            ->whereIn('status', [
                WhatsAppNotificationLog::STATUS_PENDING,
                WhatsAppNotificationLog::STATUS_ACCEPTED,
                WhatsAppNotificationLog::STATUS_DELIVERED,
                WhatsAppNotificationLog::STATUS_READ,
                WhatsAppNotificationLog::STATUS_DRY_RUN,
                WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT,
                WhatsAppNotificationLog::STATUS_CONNECTION_FAILED,
                WhatsAppNotificationLog::STATUS_FAILED,
            ])
            ->orderBy('id')
            ->get(['id', 'old_room_id', 'new_room_id', 'recipient_user_id', 'transition_id', 'idempotency_key']);

        $count = $canonicalLogs->count();
        $lastCanonical = $canonicalLogs->last();

        $isSameLatestTransition = $lastCanonical !== null
            && (int) $lastCanonical->old_room_id === (int) $oldRoomId
            && (int) $lastCanonical->new_room_id === (int) $newRoomId
            && (int) $lastCanonical->recipient_user_id === $recipientUserId;

        if ($isSameLatestTransition) {
            return [
                'idempotency_key' => (string) $lastCanonical->idempotency_key,
                'transition_id' => (string) ($lastCanonical->transition_id ?: 'direct-seq-' . $count),
                'room_move_version' => $eventMoveVersion > 0 ? $eventMoveVersion : null,
            ];
        }

        $nextSequence = $count + 1;
        $fallbackTransitionId = 'direct-seq-' . $nextSequence;

        return [
            'idempotency_key' => hash('sha256', sprintf(
                'event:%d|mode:%s|old:%s|new:%s|recipient:%d|seq:%d',
                (int) $event->id,
                $modeLabel,
                $oldRoomId !== null ? (string) $oldRoomId : 'null',
                $newRoomId !== null ? (string) $newRoomId : 'null',
                $recipientUserId,
                $nextSequence
            )),
            'transition_id' => $fallbackTransitionId,
            'room_move_version' => $eventMoveVersion > 0 ? $eventMoveVersion : null,
        ];
    }

    /**
     * Klaim baris idempotency sebelum HTTP request menggunakan unique constraint database.
     * Memastikan hanya tepat satu eksekutor yang memperoleh klaim dan tidak menahan transaksi DB
     * saat request HTTP berjalan.
     *
     * @param array<string, mixed> $attributes
     * @return array{outcome: string, log: ?WhatsAppNotificationLog, existing_log: ?WhatsAppNotificationLog}
     */
    private function claimNotificationSlot(
        string $idempotencyKey,
        array $attributes,
        string $normalizedPhone = ''
    ): array {
        $payload = array_merge($attributes, [
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $created = WhatsAppNotificationLog::create($payload);

            return [
                'outcome' => 'claimed',
                'log' => $created,
                'existing_log' => null,
            ];
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $existing = WhatsAppNotificationLog::where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return [
                        'outcome' => 'duplicate',
                        'log' => null,
                        'existing_log' => $existing,
                    ];
                }
            }

            $sanitizedError = $this->resolvedDriver()->redactSensitiveData(
                $exception->getMessage(),
                $this->resolvedDriver()->accessToken(),
                $normalizedPhone
            );

            Log::critical('Gagal mengklaim slot log notifikasi WhatsApp sebelum panggilan Meta Cloud API.', [
                'event_id' => $attributes['event_id'] ?? null,
                'transition_id' => $attributes['transition_id'] ?? null,
                'recipient_user_id' => $attributes['recipient_user_id'] ?? null,
                'recipient_phone' => $attributes['recipient_phone_masked'] ?? null,
                'error' => $sanitizedError,
            ]);

            return [
                'outcome' => 'claim_error',
                'log' => null,
                'existing_log' => null,
            ];
        } catch (\Throwable $exception) {
            $sanitizedError = $this->resolvedDriver()->redactSensitiveData(
                $exception->getMessage(),
                $this->resolvedDriver()->accessToken(),
                $normalizedPhone
            );

            Log::critical('Terjadi exception tidak terduga saat mengklaim slot log notifikasi WhatsApp.', [
                'event_id' => $attributes['event_id'] ?? null,
                'transition_id' => $attributes['transition_id'] ?? null,
                'recipient_user_id' => $attributes['recipient_user_id'] ?? null,
                'recipient_phone' => $attributes['recipient_phone_masked'] ?? null,
                'error' => $sanitizedError,
            ]);

            return [
                'outcome' => 'claim_error',
                'log' => null,
                'existing_log' => null,
            ];
        }
    }

    /**
     * Memperbarui baris klaim `pending` yang sudah ada setelah respons Meta diterima.
     * Jika update gagal, klaim `pending` TIDAK boleh dihapus dan kegagalan dicatat via Log::critical()
     * dengan data yang disanitasi agar tidak terjadi pengiriman ganda.
     *
     * @param array<string, mixed> $attributes
     */
    private function finalizeClaimLog(
        WhatsAppNotificationLog $claimLog,
        array $attributes,
        string $normalizedPhone,
        string $token
    ): bool {
        try {
            $claimLog->forceFill($attributes)->save();

            return true;
        } catch (\Throwable $exception) {
            $sanitizedError = $this->resolvedDriver()->redactSensitiveData(
                $exception->getMessage(),
                $token,
                $normalizedPhone
            );

            Log::critical('Gagal memperbarui status log klaim notifikasi WhatsApp setelah respons Meta Cloud API.', [
                'log_id' => $claimLog->id,
                'event_id' => $claimLog->event_id,
                'transition_id' => $claimLog->transition_id,
                'recipient_user_id' => $claimLog->recipient_user_id,
                'recipient_phone' => $claimLog->recipient_phone_masked,
                'intended_status' => $attributes['status'] ?? null,
                'http_status' => $attributes['http_status'] ?? null,
                'meta_message_id' => $attributes['meta_message_id'] ?? null,
                'error' => $sanitizedError,
            ]);

            return false;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000'
            || $driverCode === 1062
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'integrity constraint violation');
    }

    private function buildAuditIdempotencyKey(
        Event $event,
        ?int $recipientUserId,
        ?int $oldRoomId,
        ?int $newRoomId,
        ?string $explicitKey,
        ?string $transitionId = null
    ): string {
        return hash('sha256', sprintf(
            'audit|event:%s|old:%s|new:%s|recipient:%s|transition:%s|explicit:%s|uuid:%s',
            (string) ($event->id ?? 'null'),
            (string) ($oldRoomId ?? 'null'),
            (string) ($newRoomId ?? 'null'),
            (string) ($recipientUserId ?? 'null'),
            trim((string) ($transitionId ?? '')),
            trim((string) ($explicitKey ?? '')),
            (string) Str::uuid()
        ));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function recordAuditLog(string $idempotencyKey, array $attributes): ?WhatsAppNotificationLog
    {
        try {
            return WhatsAppNotificationLog::create(array_merge($attributes, [
                'idempotency_key' => $idempotencyKey,
            ]));
        } catch (\Throwable $exception) {
            $sanitizedError = $this->resolvedDriver()->redactSensitiveData(
                $exception->getMessage(),
                $this->resolvedDriver()->accessToken()
            );

            Log::warning('Gagal menyimpan log audit notifikasi WhatsApp.', [
                'event_id' => $attributes['event_id'] ?? null,
                'status' => $attributes['status'] ?? null,
                'error' => $sanitizedError,
            ]);

            return null;
        }
    }

    /**
     * @return array{
     *     status: string,
     *     sent_count: int,
     *     failed_count: int,
     *     recipients_count: int,
     *     is_dry_run: bool,
     *     meta_message_id: ?string,
     *     log_id: ?int,
     *     transition_id: ?string,
     *     message: string
     * }
     */
    private function makeResult(
        string $status,
        int $sentCount,
        int $failedCount,
        int $recipientsCount,
        bool $isDryRun,
        ?string $metaMessageId,
        ?int $logId,
        string $message,
        ?string $transitionId = null
    ): array {
        return [
            'status' => $status,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'recipients_count' => $recipientsCount,
            'is_dry_run' => $isDryRun,
            'meta_message_id' => $metaMessageId,
            'log_id' => $logId,
            'transition_id' => $transitionId,
            'message' => $message,
        ];
    }

    private function resolvedDriver(): MetaCloudApiDriver
    {
        return $this->driver ?? app(MetaCloudApiDriver::class);
    }

    private function formatIndonesianDateRange(Event $event): string
    {
        $start = $this->parseEventDateInJakarta($event->getRawOriginal('date') ?? $event->date);
        $end = $this->parseEventDateInJakarta($event->getRawOriginal('end_date') ?? $event->end_date);

        if (!$start) {
            return '-';
        }

        $startFormatted = $this->formatSingleIndonesianDate($start);
        if ($end && $end->gt($start)) {
            return $startFormatted . ' s/d ' . $this->formatSingleIndonesianDate($end);
        }

        return $startFormatted;
    }

    private function parseEventDateInJakarta(mixed $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            if ($date instanceof \DateTimeInterface) {
                return Carbon::instance($date)->timezone(self::JAKARTA_TIMEZONE)->startOfDay();
            }

            return Carbon::parse((string) $date, self::JAKARTA_TIMEZONE)
                ->timezone(self::JAKARTA_TIMEZONE)
                ->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatSingleIndonesianDate(Carbon $date): string
    {
        $month = self::INDONESIAN_MONTHS[$date->month] ?? $date->format('m');

        return $date->day . ' ' . $month . ' ' . $date->year;
    }

    private function formatWibTimeRange(Event $event): string
    {
        $startFormatted = $this->formatSingleWibTime($event->start_time);
        $endFormatted = $this->formatSingleWibTime($event->end_time);

        if ($startFormatted === null && $endFormatted === null) {
            return '-';
        }

        if ($startFormatted !== null && $endFormatted !== null) {
            return $startFormatted . ' - ' . $endFormatted . ' WIB';
        }

        return ($startFormatted ?? $endFormatted) . ' WIB';
    }

    private function formatSingleWibTime(mixed $time): ?string
    {
        if ($time === null || trim((string) $time) === '') {
            return null;
        }

        try {
            if ($time instanceof \DateTimeInterface) {
                return Carbon::instance($time)->timezone(self::JAKARTA_TIMEZONE)->format('H:i');
            }

            return Carbon::parse((string) $time, self::JAKARTA_TIMEZONE)
                ->timezone(self::JAKARTA_TIMEZONE)
                ->format('H:i');
        } catch (\Throwable) {
            return substr(trim((string) $time), 0, 5) ?: null;
        }
    }

    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 4) . str_repeat('*', $length - 7) . substr($phone, -3);
    }
}
