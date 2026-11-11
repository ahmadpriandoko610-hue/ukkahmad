<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
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
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (!$digits || strlen($digits) < 8) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        return $digits;
    }

    /**
     * Kumpulkan nomor WhatsApp penerima yang relevan dari database tanpa duplikasi
     * dan tanpa membuat nomor telepon fiktif.
     *
     * @return array<int, array{user_id: int|null, name: string, role: string|null, phone: string}>
     */
    public function resolveRecipientsForRoomMove(Event $event, ?Room $oldRoom, ?Room $newRoom): array
    {
        $event->loadMissing(['requester', 'user', 'room.pic']);
        $oldRoom?->loadMissing('pic');
        $newRoom?->loadMissing('pic');

        $candidates = array_filter([
            $newRoom?->pic,
            $oldRoom?->pic,
            $event->requester ?? $event->user,
        ]);

        $uniqueByPhone = [];

        foreach ($candidates as $user) {
            $normalizedPhone = $this->normalizePhone($user->phone ?? null);
            if ($normalizedPhone === null) {
                continue;
            }

            if (isset($uniqueByPhone[$normalizedPhone])) {
                continue;
            }

            $uniqueByPhone[$normalizedPhone] = [
                'user_id' => $user->id ?? null,
                'name' => (string) ($user->name ?? 'Pengguna'),
                'role' => $user->role ?? null,
                'phone' => $normalizedPhone,
            ];
        }

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
     * Kirim notifikasi WhatsApp saat ruangan agenda benar-benar berubah.
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

        $oldRoomName = $oldRoom?->room_name ?: 'Ruangan sebelumnya';
        $newRoomName = $newRoom?->room_name ?: 'Ruangan baru';
        $recipients = $this->resolveRecipientsForRoomMove($event, $oldRoom, $newRoom);

        if (empty($recipients)) {
            Log::warning('Notifikasi WhatsApp perubahan ruangan tidak dikirim: nomor WhatsApp penerima belum tersedia di database.', [
                'event_id' => $event->id,
                'old_room_id' => $oldRoom?->id,
                'new_room_id' => $newRoom?->id,
            ]);

            return [
                'status' => 'no_recipients',
                'sent_count' => 0,
                'failed_count' => 0,
                'recipients_count' => 0,
                'message' => 'Nomor WhatsApp penerima yang berkepentingan belum tersedia di database.',
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
        $start = $event->date ? Carbon::parse($event->date) : null;
        $end = $event->end_date ? Carbon::parse($event->end_date) : null;

        if (!$start) {
            return '-';
        }

        $startFormatted = $this->formatSingleIndonesianDate($start);
        if ($end && $end->gt($start)) {
            return $startFormatted . ' s/d ' . $this->formatSingleIndonesianDate($end);
        }

        return $startFormatted;
    }

    private function formatSingleIndonesianDate(Carbon $date): string
    {
        $month = self::INDONESIAN_MONTHS[$date->month] ?? $date->format('m');

        return $date->day . ' ' . $month . ' ' . $date->year;
    }

    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($phone, 0, 4) . str_repeat('*', $length - 7) . substr($phone, -3);
    }
}
