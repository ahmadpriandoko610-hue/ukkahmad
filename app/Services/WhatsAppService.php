<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function isConfigured(): bool
    {
        $enabled = (bool) config('services.whatsapp.enabled', true);
        $endpoint = trim((string) config('services.whatsapp.endpoint', ''));
        $token = trim((string) config('services.whatsapp.token', ''));

        return $enabled && $endpoint !== '' && $token !== '';
    }

    public function normalizePhone(?string $phone): ?string
    {
        return User::normalizePhoneNumber($phone);
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

    public function formatRoomChangeMessage(
        Event $event,
        string $oldRoomName,
        string $newRoomName,
        ?string $reason = null
    ): string {
        $lines = [
            'Informasi Perubahan Ruangan RAPI-Sekolah',
            '',
            'Agenda: ' . $event->event_name,
            'Tanggal: ' . $this->formatIndonesianDateRange($event),
            'Waktu: ' . $this->formatWibTimeRange($event),
            'Ruangan sebelumnya: ' . $oldRoomName,
            'Ruangan baru: ' . $newRoomName,
        ];

        $trimmedReason = trim((string) $reason);
        if ($trimmedReason !== '') {
            $lines[] = 'Alasan: ' . $trimmedReason;
        }

        $lines[] = '';
        $lines[] = 'Mohon perhatikan perubahan lokasi agenda tersebut.';
        $lines[] = '';
        $lines[] = 'Terima kasih.';

        return implode("\n", $lines);
    }

    /**
     * Kirim notifikasi WhatsApp saat ruangan agenda benar-benar berubah oleh Admin.
     * Kegagalan layanan eksternal dicatat ke log dan tidak melempar exception.
     *
     * @return array{status: string, sent_count: int, failed_count: int, recipients_count: int, message: string}
     */
    public function notifyRoomChanged(
        Event $event,
        ?Room $oldRoom,
        ?Room $newRoom,
        ?string $reason = null
    ): array {
        if ($oldRoom && $newRoom && (int) $oldRoom->id === (int) $newRoom->id) {
            return [
                'status' => 'skipped_unchanged',
                'sent_count' => 0,
                'failed_count' => 0,
                'recipients_count' => 0,
                'message' => 'Ruangan agenda tidak berubah.',
            ];
        }

        $event->loadMissing('requester');
        $requester = $event->requester;

        if (!$requester || $requester->role !== 'guru') {
            Log::info('Notifikasi WhatsApp perubahan ruangan dilewati: pemilik agenda bukan role guru.', [
                'event_id' => $event->id,
                'old_room_id' => $oldRoom?->id,
                'new_room_id' => $newRoom?->id,
                'requester_role' => $requester?->role,
            ]);

            return [
                'status' => 'skipped_non_guru',
                'sent_count' => 0,
                'failed_count' => 0,
                'recipients_count' => 0,
                'message' => 'Notifikasi WhatsApp hanya dikirim untuk agenda yang diajukan oleh Guru / Organisasi.',
            ];
        }

        $oldRoomName = $oldRoom?->room_name ?: 'Ruangan sebelumnya';
        $newRoomName = $newRoom?->room_name ?: 'Ruangan baru';
        $recipients = $this->resolveRecipientsForRoomMove($event, $oldRoom, $newRoom);

        if (empty($recipients)) {
            Log::warning('Notifikasi WhatsApp perubahan ruangan tidak dikirim: Guru pemilik agenda belum memiliki nomor WhatsApp valid.', [
                'event_id' => $event->id,
                'requester_id' => $requester->id,
                'old_room_id' => $oldRoom?->id,
                'new_room_id' => $newRoom?->id,
            ]);

            return [
                'status' => 'no_recipients',
                'sent_count' => 0,
                'failed_count' => 0,
                'recipients_count' => 0,
                'message' => 'Nomor WhatsApp Guru / Organisasi pemilik agenda belum tersedia di database.',
            ];
        }

        if (!$this->isConfigured()) {
            Log::warning('Gateway WhatsApp belum dikonfigurasi. Notifikasi perubahan ruangan tidak dikirim.', [
                'event_id' => $event->id,
                'old_room_id' => $oldRoom?->id,
                'new_room_id' => $newRoom?->id,
                'recipients_count' => count($recipients),
            ]);

            return [
                'status' => 'unconfigured',
                'sent_count' => 0,
                'failed_count' => 0,
                'recipients_count' => count($recipients),
                'message' => 'Layanan WhatsApp Gateway belum dikonfigurasi (WHATSAPP_API_ENDPOINT / WHATSAPP_API_TOKEN).',
            ];
        }

        $endpoint = trim((string) config('services.whatsapp.endpoint'));
        $token = trim((string) config('services.whatsapp.token'));
        $authHeader = trim((string) config('services.whatsapp.auth_header', 'Authorization')) ?: 'Authorization';
        $tokenPrefix = trim((string) config('services.whatsapp.token_prefix', 'Bearer'));
        $sender = trim((string) config('services.whatsapp.sender', ''));
        $timeout = max(2, (int) config('services.whatsapp.timeout', 10));

        $headerValue = $tokenPrefix !== '' ? ($tokenPrefix . ' ' . $token) : $token;
        $message = $this->formatRoomChangeMessage($event, $oldRoomName, $newRoomName, $reason);

        $sentCount = 0;
        $failedCount = 0;

        foreach ($recipients as $recipient) {
            $payload = array_filter([
                'target' => $recipient['phone'],
                'phone' => $recipient['phone'],
                'message' => $message,
                'sender' => $sender !== '' ? $sender : null,
                'event_id' => $event->id,
                'event_name' => $event->event_name,
                'old_room' => $oldRoomName,
                'new_room' => $newRoomName,
            ], static fn ($value) => $value !== null);

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        $authHeader => $headerValue,
                        'Accept' => 'application/json',
                    ])
                    ->post($endpoint, $payload);

                if ($response->successful()) {
                    $sentCount++;
                    Log::info('Notifikasi WhatsApp perubahan ruangan berhasil dikirim.', [
                        'event_id' => $event->id,
                        'recipient_user_id' => $recipient['user_id'],
                        'recipient_phone' => $this->maskPhone($recipient['phone']),
                        'http_status' => $response->status(),
                    ]);
                } else {
                    $failedCount++;
                    Log::error('Notifikasi WhatsApp perubahan ruangan ditolak gateway.', [
                        'event_id' => $event->id,
                        'recipient_user_id' => $recipient['user_id'],
                        'recipient_phone' => $this->maskPhone($recipient['phone']),
                        'http_status' => $response->status(),
                    ]);
                }
            } catch (\Throwable $exception) {
                $failedCount++;
                Log::error('Kegagalan koneksi saat mengirim notifikasi WhatsApp perubahan ruangan.', [
                    'event_id' => $event->id,
                    'recipient_user_id' => $recipient['user_id'],
                    'recipient_phone' => $this->maskPhone($recipient['phone']),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $status = match (true) {
            $sentCount > 0 && $failedCount === 0 => 'sent',
            $sentCount > 0 && $failedCount > 0 => 'partial_failed',
            default => 'failed',
        };

        $statusMessage = match ($status) {
            'sent' => 'Notifikasi WhatsApp perubahan ruangan berhasil dikirim.',
            'partial_failed' => 'Sebagian notifikasi WhatsApp berhasil dikirim, sebagian mengalami kegagalan.',
            default => 'Pengiriman notifikasi WhatsApp gagal menghubungi layanan gateway.',
        };

        return [
            'status' => $status,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'recipients_count' => count($recipients),
            'message' => $statusMessage,
        ];
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
