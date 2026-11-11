<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomMoveWhatsAppService
{
    public const SESSION_KEY = 'room_move_whatsapp';

    private const MAX_TRANSITIONS = 10;

    /**
     * Simpan konteks pemindahan ruangan yang telah tervalidasi ke session Admin aktif.
     */
    public function rememberRoomMove(
        Request $request,
        int $eventId,
        ?int $oldRoomId,
        string $oldRoomName,
        int $newRoomId
    ): void {
        $adminId = Auth::id();
        $adminUser = Auth::user();
        if ($adminId === null || (int) $adminId <= 0 || !$adminUser || $adminUser->role !== 'admin') {
            $this->clear($request);

            return;
        }

        $trimmedOldRoom = trim($oldRoomName);
        if ($eventId <= 0 || $newRoomId <= 0 || $trimmedOldRoom === '' || ($oldRoomId !== null && $oldRoomId === $newRoomId)) {
            $this->clear($request, $eventId > 0 ? $eventId : null);

            return;
        }

        $state = $this->readOwnedSessionState($request, (int) $adminId);
        $transitions = $state['transitions'];
        unset($transitions[$eventId]);

        // Jangan pernah menyimpan nomor telepon atau isi pesan mentah ke session.
        $transitions[$eventId] = [
            'admin_id' => (int) $adminId,
            'event_id' => $eventId,
            'old_room_id' => $oldRoomId,
            'old_room_name' => $trimmedOldRoom,
            'new_room_id' => $newRoomId,
        ];

        if (count($transitions) > self::MAX_TRANSITIONS) {
            $transitions = array_slice($transitions, -self::MAX_TRANSITIONS, null, true);
        }

        $request->session()->put(self::SESSION_KEY, [
            'admin_id' => (int) $adminId,
            'latest_event_id' => $eventId,
            'transitions' => $transitions,
        ]);
    }

    /**
     * Hapus konteks notifikasi WhatsApp pemindahan ruangan dari session.
     * Jika $eventId diberikan, hanya konteks agenda tersebut yang dihapus agar notifikasi agenda lain tetap terjaga.
     */
    public function clear(Request $request, ?int $eventId = null): void
    {
        if ($eventId === null || $eventId <= 0) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        $state = $request->session()->get(self::SESSION_KEY);
        if (!is_array($state)) {
            return;
        }

        $currentAdminId = Auth::id();
        $sessionAdminId = isset($state['admin_id']) ? (int) $state['admin_id'] : 0;

        if ($currentAdminId === null || $sessionAdminId <= 0 || $sessionAdminId !== (int) $currentAdminId) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        $transitions = is_array($state['transitions'] ?? null) ? $state['transitions'] : [];
        unset($transitions[$eventId]);

        if ($transitions === []) {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        $latestEventId = isset($state['latest_event_id']) && is_numeric($state['latest_event_id'])
            ? (int) $state['latest_event_id']
            : null;

        if ($latestEventId === $eventId) {
            $latestEventId = null;
        }

        $request->session()->put(self::SESSION_KEY, [
            'admin_id' => $sessionAdminId,
            'latest_event_id' => $latestEventId,
            'transitions' => $transitions,
        ]);
    }

    /**
     * Susun data tampilan pemberitahuan WhatsApp dari database berdasarkan konteks pemindahan ruangan terakhir.
     *
     * @return array<string, mixed>|null
     */
    public function resolveForView(Request $request, ?Event $scopedEvent = null): ?array
    {
        $resolved = $this->resolveVerifiedTransition($request, $scopedEvent);
        if ($resolved === null) {
            return null;
        }

        return $resolved['view_data'];
    }

    /**
     * Susun tautan wa.me yang aman langsung dari database untuk aksi Admin membuka WhatsApp.
     *
     * @return array{ok: bool, error?: string, url?: string}
     */
    public function resolveRedirectUrl(Request $request, Event $event): array
    {
        $resolved = $this->resolveVerifiedTransition($request, $event);
        if ($resolved === null) {
            return [
                'ok' => false,
                'error' => 'Pemberitahuan WhatsApp hanya tersedia setelah pemindahan ruangan agenda berhasil disimpan.',
            ];
        }

        if (empty($resolved['view_data']['can_open_whatsapp']) || empty($resolved['whatsapp_url'])) {
            return [
                'ok' => false,
                'error' => $resolved['view_data']['phone_missing_message'],
            ];
        }

        return [
            'ok' => true,
            'url' => $resolved['whatsapp_url'],
        ];
    }

    /**
     * Susun isi pesan WhatsApp pemindahan ruangan menggunakan data aktual database.
     */
    public function buildMessage(
        string $recipientName,
        string $eventName,
        string $oldRoomName,
        string $newRoomName,
        string $dateRange,
        string $startTime,
        string $endTime
    ): string {
        return
            "Halo Bapak/Ibu {$recipientName}.\n\n" .
            "Kami informasikan bahwa ruangan agenda Anda telah mengalami perubahan.\n\n" .
            "Informasi Perubahan Ruangan:\n\n" .
            "Nama Agenda: {$eventName}\n" .
            "Ruangan Sebelumnya: {$oldRoomName}\n" .
            "Ruangan Baru: {$newRoomName}\n" .
            "Tanggal: {$dateRange}\n" .
            "Waktu: {$startTime} - {$endTime}\n\n" .
            "Mohon memperhatikan perubahan ruangan tersebut.\n\n" .
            "Terima kasih.\n" .
            "Admin RAPI-Sekolah.";
    }

    /**
     * Validasi konteks session terhadap Admin aktif dan data terbaru di database agar tahan reload tanpa data usang.
     *
     * @return array{view_data: array<string, mixed>, whatsapp_url: ?string}|null
     */
    private function resolveVerifiedTransition(Request $request, ?Event $scopedEvent = null): ?array
    {
        $currentAdminId = Auth::id();
        $currentUser = Auth::user();
        if ($currentAdminId === null || (int) $currentAdminId <= 0 || !$currentUser || $currentUser->role !== 'admin') {
            $this->clear($request);

            return null;
        }

        $state = $request->session()->get(self::SESSION_KEY);
        if (!is_array($state)) {
            return null;
        }

        $sessionAdminId = isset($state['admin_id']) ? (int) $state['admin_id'] : 0;
        if ($sessionAdminId <= 0 || $sessionAdminId !== (int) $currentAdminId) {
            $this->clear($request);

            return null;
        }

        $transitions = $state['transitions'] ?? null;
        if (!is_array($transitions) || $transitions === []) {
            $this->clear($request);

            return null;
        }

        if ($scopedEvent !== null) {
            $targetEventId = (int) $scopedEvent->id;
            if (!isset($transitions[$targetEventId]) || !is_array($transitions[$targetEventId])) {
                return null;
            }
        } else {
            $targetEventId = isset($state['latest_event_id']) && is_numeric($state['latest_event_id'])
                ? (int) $state['latest_event_id']
                : 0;
            if ($targetEventId <= 0 || !isset($transitions[$targetEventId]) || !is_array($transitions[$targetEventId])) {
                return null;
            }
        }

        $context = $transitions[$targetEventId];
        $contextAdminId = isset($context['admin_id']) ? (int) $context['admin_id'] : 0;
        if ($contextAdminId <= 0 || $contextAdminId !== (int) $currentAdminId) {
            $this->clear($request);

            return null;
        }

        $eventId = isset($context['event_id']) ? (int) $context['event_id'] : 0;
        $expectedNewRoomId = isset($context['new_room_id']) ? (int) $context['new_room_id'] : 0;
        $oldRoomId = isset($context['old_room_id']) && $context['old_room_id'] !== null
            ? (int) $context['old_room_id']
            : null;
        $storedOldRoomName = trim((string) ($context['old_room_name'] ?? ''));

        if ($eventId !== $targetEventId || $eventId <= 0 || $expectedNewRoomId <= 0 || ($oldRoomId !== null && $oldRoomId === $expectedNewRoomId)) {
            $this->clear($request, $targetEventId);

            return null;
        }

        $event = Event::query()
            ->with(['requester', 'room'])
            ->find($eventId);

        if (!$event || $event->status !== 'Disetujui' || (int) $event->room_id !== $expectedNewRoomId) {
            $this->clear($request, $eventId);

            return null;
        }

        $oldRoomRecord = $oldRoomId !== null ? Room::query()->find($oldRoomId) : null;
        $oldRoomName = trim((string) ($oldRoomRecord?->room_name ?? $storedOldRoomName));
        $newRoomName = trim((string) ($event->room?->room_name ?? ''));
        $eventName = trim((string) ($event->event_name ?? ''));

        if ($oldRoomName === '' || $newRoomName === '' || $eventName === '') {
            $this->clear($request, $eventId);

            return null;
        }

        $recipient = $event->requester;
        $recipientName = trim((string) ($recipient?->name ?? ''));
        if ($recipientName === '') {
            $recipientName = 'Guru';
        }

        $dateRange = $event->formatted_date_range;
        $startTime = $event->start_time ? substr(trim((string) $event->start_time), 0, 5) : '-';
        $endTime = $event->end_time ? substr(trim((string) $event->end_time), 0, 5) : '-';
        $timeRange = "{$startTime} - {$endTime}";

        $normalizedPhone = User::normalizeWhatsAppPhone($recipient?->phone);
        $whatsappUrl = null;

        if ($normalizedPhone !== null) {
            $message = $this->buildMessage(
                $recipientName,
                $eventName,
                $oldRoomName,
                $newRoomName,
                $dateRange,
                $startTime,
                $endTime
            );

            $whatsappUrl = 'https://wa.me/' . $normalizedPhone . '?text=' . rawurlencode($message);
        }

        return [
            'view_data' => [
                'available' => true,
                'event_id' => $event->id,
                'event_name' => $eventName,
                'recipient_name' => $recipientName,
                'old_room_name' => $oldRoomName,
                'new_room_name' => $newRoomName,
                'date_range' => $dateRange,
                'time_range' => $timeRange,
                'can_open_whatsapp' => $whatsappUrl !== null,
                'phone_missing_message' => 'WhatsApp tidak dapat dibuka karena nomor WhatsApp Guru pemilik agenda belum tersedia atau tidak valid.',
            ],
            'whatsapp_url' => $whatsappUrl,
        ];
    }

    /**
     * Ambil state session milik Admin aktif, atau kosongkan bila struktur usang atau milik Admin lain.
     *
     * @return array{admin_id: int, latest_event_id: ?int, transitions: array<int, array<string, mixed>>}
     */
    private function readOwnedSessionState(Request $request, int $adminId): array
    {
        $state = $request->session()->get(self::SESSION_KEY);
        if (!is_array($state) || (int) ($state['admin_id'] ?? 0) !== $adminId) {
            $this->clear($request);

            return [
                'admin_id' => $adminId,
                'latest_event_id' => null,
                'transitions' => [],
            ];
        }

        $transitions = [];
        $rawTransitions = is_array($state['transitions'] ?? null) ? $state['transitions'] : [];

        foreach ($rawTransitions as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemEventId = (int) ($item['event_id'] ?? $key);
            $itemAdminId = (int) ($item['admin_id'] ?? 0);
            if ($itemEventId > 0 && $itemAdminId === $adminId) {
                $transitions[$itemEventId] = $item;
            }
        }

        $latestEventId = isset($state['latest_event_id']) && is_numeric($state['latest_event_id'])
            ? (int) $state['latest_event_id']
            : null;

        if ($latestEventId !== null && !isset($transitions[$latestEventId])) {
            $latestEventId = null;
        }

        return [
            'admin_id' => $adminId,
            'latest_event_id' => $latestEventId,
            'transitions' => $transitions,
        ];
    }
}
