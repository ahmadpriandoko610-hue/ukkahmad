<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RoomMoveWhatsAppPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $picOld;
    private User $picNew;
    private User $guruOwner;
    private User $otherGuru;
    private Room $oldRoom;
    private Room $newRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Sekolah',
            'email' => 'admin.policy@rapi.test',
        ]);

        // Admin tidak boleh menyimpan phone bahkan jika di-insert langsung ke DB
        DB::table('users')
            ->where('id', $this->admin->id)
            ->update(['phone' => '6289999999999']);
        $this->admin->refresh();

        $this->picOld = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Ruangan Lama',
            'email' => 'pic.lama@rapi.test',
            'phone' => '081111111111',
        ]);

        $this->picNew = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Ruangan Baru',
            'email' => 'pic.baru@rapi.test',
            'phone' => '+6282222222222',
        ]);

        $this->guruOwner = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Pemilik Agenda',
            'email' => 'guru.pemilik@rapi.test',
            'phone' => '081234567890',
        ]);

        $this->otherGuru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Lain',
            'email' => 'guru.lain@rapi.test',
            'phone' => '+6285678901234',
        ]);

        $this->oldRoom = Room::create([
            'room_name' => 'Ruang Teori A',
            'location' => 'Lantai 1',
            'capacity' => 40,
            'pic_id' => $this->picOld->id,
            'is_active' => true,
        ]);

        $this->newRoom = Room::create([
            'room_name' => 'Lab Multimedia',
            'location' => 'Lantai 2',
            'capacity' => 36,
            'pic_id' => $this->picNew->id,
            'is_active' => true,
        ]);
    }

    /**
     * 7, 8, 9, 10, 15. Hanya Guru pemilik agenda yang menerima notifikasi WhatsApp;
     * Admin, PIC lama, PIC baru, dan Guru lain tidak menerima notifikasi;
     * notifikasi internal tetap berjalan.
     */
    public function test_only_guru_owner_receives_whatsapp_and_internal_notification_still_works(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'Asia/Jakarta'));

        try {
            $this->configureFakeWhatsAppGateway();

            Http::preventStrayRequests();
            Http::fake([
                'https://graph.facebook.com/v21.0/998877665544/messages' => Http::response([
                    'messaging_product' => 'whatsapp',
                    'contacts' => [
                        ['input' => '6281234567890', 'wa_id' => '6281234567890'],
                    ],
                    'messages' => [
                        ['id' => 'wamid.HBgMpolicy001'],
                    ],
                ], 200),
            ]);

            $event = Event::create([
                'event_name' => 'Workshop Desain UI',
                'date' => '2026-11-12',
                'end_date' => '2026-11-12',
                'start_time' => '09:00:00',
                'end_time' => '11:15:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->oldRoom->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // Uji resolver secara langsung untuk memastikan target penerima tunggal tanpa duplikasi
            $recipients = app(WhatsAppService::class)->resolveRecipientsForRoomMove(
                $event,
                $this->oldRoom,
                $this->newRoom
            );
            $this->assertCount(1, $recipients);
            $this->assertSame($this->guruOwner->id, $recipients[0]['user_id']);
            $this->assertSame('guru', $recipients[0]['role']);
            $this->assertSame('6281234567890', $recipients[0]['phone']);

            // Pindah ruangan oleh Admin
            $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
                'room_id' => $this->newRoom->id,
                'move_reason' => 'Membutuhkan perangkat komputer',
            ]);

            $response->assertRedirect(route('agenda.index'));
            $response->assertSessionHas(
                'success',
                'Ruangan agenda berhasil dipindahkan, notifikasi internal telah dibuat, dan notifikasi WhatsApp diterima antrean Meta Cloud API (status: accepted; menunggu pengiriman ke perangkat Guru).'
            );
            $response->assertSessionMissing('warning');

            // Hanya 1 request ke Meta Cloud API untuk Guru pemilik agenda
            Http::assertSentCount(1);
            Http::assertSent(function ($request) {
                $data = $request->data();
                $params = array_column((array) data_get($data, 'template.components.0.parameters', []), 'text');

                return $request->url() === 'https://graph.facebook.com/v21.0/998877665544/messages'
                    && $request->hasHeader('Authorization', 'Bearer secret-meta-test-token')
                    && ($data['messaging_product'] ?? '') === 'whatsapp'
                    && ($data['to'] ?? '') === '6281234567890'
                    && ($data['type'] ?? '') === 'template'
                    && data_get($data, 'template.name') === 'notifikasi_pemindahan_ruangan'
                    && data_get($data, 'template.language.code') === 'id'
                    && $params === [
                        'Workshop Desain UI',
                        '12 November 2026',
                        '09:00 - 11:15 WIB',
                        'Ruang Teori A',
                        'Lab Multimedia',
                        'Membutuhkan perangkat komputer',
                    ];
            });

            // Admin, PIC ruangan lama, PIC ruangan baru, dan Guru lain tidak menerima WhatsApp
            Http::assertNotSent(function ($request) {
                return in_array($request->data()['to'] ?? '', [
                    '6289999999999', // Admin (simulasi DB)
                    '6281111111111', // PIC lama
                    '6282222222222', // PIC baru
                    '6285678901234', // Guru lain
                ], true);
            });

            // Notifikasi internal tetap dibuat untuk pemilik agenda
            $this->assertDatabaseHas('schedule_notifications', [
                'user_id' => $this->guruOwner->id,
                'event_id' => $event->id,
                'title' => 'Ruangan agenda dipindahkan',
            ]);
            $this->assertSame(1, ScheduleNotification::count());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Admin mengubah ruangan melalui form Edit Agenda Web tetap mengirim WA hanya ke Guru pemilik agenda.
     */
    public function test_admin_web_agenda_update_room_change_notifies_only_guru_owner(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'Asia/Jakarta'));

        try {
            $this->configureFakeWhatsAppGateway();

            Http::preventStrayRequests();
            Http::fake([
                'https://graph.facebook.com/v21.0/998877665544/messages' => Http::response([
                    'messaging_product' => 'whatsapp',
                    'messages' => [
                        ['id' => 'wamid.HBgMpolicy002'],
                    ],
                ], 200),
            ]);

            $event = Event::create([
                'event_name' => 'Pembinaan LKS Web',
                'date' => '2026-11-14',
                'end_date' => '2026-11-15',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->oldRoom->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $response = $this->actingAs($this->admin)->put(route('agenda.update', $event), [
                'judul' => 'Pembinaan LKS Web',
                'tanggal' => '2026-11-14',
                'tanggal_selesai' => '2026-11-15',
                'sesi_mulai' => 2, // 08:15
                'sesi_selesai' => 4, // berakhir 10:30
                'ruangan_id' => $this->newRoom->id,
                'keterangan' => 'Dipindahkan saat edit oleh admin',
            ]);

            $response->assertRedirect(route('agenda.index'));
            $response->assertSessionHas(
                'success',
                'Agenda berhasil diperbarui, notifikasi internal telah dibuat, dan notifikasi WhatsApp diterima antrean Meta Cloud API (status: accepted; menunggu pengiriman ke perangkat Guru).'
            );
            $response->assertSessionMissing('warning');

            Http::assertSentCount(1);
            Http::assertSent(function ($request) {
                $data = $request->data();
                $params = array_column((array) data_get($data, 'template.components.0.parameters', []), 'text');

                return ($data['to'] ?? '') === '6281234567890'
                    && $params[0] === 'Pembinaan LKS Web'
                    && $params[1] === '14 November 2026 s/d 15 November 2026'
                    && $params[2] === '08:15 - 10:30 WIB'
                    && $params[3] === 'Ruang Teori A'
                    && $params[4] === 'Lab Multimedia'
                    && $params[5] !== '';
            });

            $this->assertDatabaseHas('schedule_notifications', [
                'user_id' => $this->guruOwner->id,
                'event_id' => $event->id,
                'title' => 'Ruangan agenda dipindahkan',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 11. Agenda milik Admin tidak mengirim notifikasi WhatsApp, sementara notifikasi internal tetap tercatat.
     */
    public function test_admin_owned_agenda_relocation_does_not_send_whatsapp(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'Asia/Jakarta'));

        try {
            $this->configureFakeWhatsAppGateway();

            Http::preventStrayRequests();
            Http::fake();

            $adminEvent = Event::create([
                'event_name' => 'Rapat Pleno Sekolah',
                'date' => '2026-11-13',
                'end_date' => '2026-11-13',
                'start_time' => '07:30:00',
                'end_time' => '09:00:00',
                'requester_id' => $this->admin->id,
                'room_id' => $this->oldRoom->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $response = $this->actingAs($this->admin)->put(route('events.move', $adminEvent), [
                'room_id' => $this->newRoom->id,
                'move_reason' => 'Penyesuaian kapasitas peserta',
            ]);

            $response->assertRedirect(route('agenda.index'));
            $response->assertSessionHas('success');
            $response->assertSessionMissing('warning');

            Http::assertNothingSent();

            $this->assertDatabaseHas('events', [
                'id' => $adminEvent->id,
                'room_id' => $this->newRoom->id,
            ]);
            $this->assertDatabaseHas('schedule_notifications', [
                'user_id' => $this->admin->id,
                'event_id' => $adminEvent->id,
                'title' => 'Ruangan agenda dipindahkan',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 12. Guru mengedit agendanya sendiri (termasuk mengganti ruangan pada status Menunggu)
     * tidak memicu notifikasi pemindahan maupun WhatsApp.
     */
    public function test_guru_editing_own_agenda_does_not_trigger_relocation_notification_or_whatsapp(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'Asia/Jakarta'));

        try {
            $this->configureFakeWhatsAppGateway();

            Http::preventStrayRequests();
            Http::fake();

            $event = Event::create([
                'event_name' => 'Latihan Teater',
                'date' => '2026-11-15',
                'end_date' => '2026-11-15',
                'start_time' => '08:15:00',
                'end_time' => '09:45:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->oldRoom->id,
                'status' => 'Menunggu',
            ]);

            $apiResponse = $this->actingAsApiUser($this->guruOwner)
                ->putJson("/api/agenda/{$event->id}", [
                    'judul' => 'Latihan Teater Pindah Ruang Mandiri',
                    'tanggal' => '2026-11-15',
                    'jam_mulai' => '08:15',
                    'jam_selesai' => '09:45',
                    'ruangan_id' => $this->newRoom->id,
                    'keterangan' => 'Guru mengubah pengajuan sebelum disetujui',
                ]);

            $apiResponse->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonMissingPath('whatsapp_notification');

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'room_id' => $this->newRoom->id,
                'event_name' => 'Latihan Teater Pindah Ruang Mandiri',
            ]);

            Http::assertNothingSent();
            $this->assertDatabaseCount('schedule_notifications', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 13. Tidak mengubah ruangan pada edit Admin tidak memicu notifikasi WhatsApp atau pemindahan.
     */
    public function test_admin_update_without_changing_room_does_not_trigger_whatsapp_or_move_notification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'Asia/Jakarta'));

        try {
            $this->configureFakeWhatsAppGateway();

            Http::preventStrayRequests();
            Http::fake();

            $event = Event::create([
                'event_name' => 'Koordinasi Ekskul',
                'date' => '2026-11-18',
                'end_date' => '2026-11-18',
                'start_time' => '09:00:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->oldRoom->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $response = $this->actingAs($this->admin)->put(route('agenda.update', $event), [
                'judul' => 'Koordinasi Ekskul Revisi',
                'tanggal' => '2026-11-18',
                'tanggal_selesai' => '2026-11-18',
                'sesi_mulai' => 3,
                'sesi_selesai' => 4,
                'ruangan_id' => $this->oldRoom->id,
                'keterangan' => 'Hanya revisi nama kegiatan',
            ]);

            $response->assertRedirect(route('agenda.index'));
            $response->assertSessionHas('success', 'Agenda berhasil diperbarui.');

            Http::assertNothingSent();
            $this->assertDatabaseCount('schedule_notifications', 0);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 14. Kegagalan atau exception pada WhatsAppService tidak membatalkan perubahan agenda
     * maupun pembuatan ScheduleNotification; sebaliknya rollback transaksi mencegah pengiriman WA.
     */
    public function test_whatsapp_service_exception_after_commit_does_not_rollback_agenda_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 08:00:00', 'Asia/Jakarta'));

        try {
            $event = Event::create([
                'event_name' => 'Seminar Karir',
                'date' => '2026-11-19',
                'end_date' => '2026-11-19',
                'start_time' => '08:15:00',
                'end_time' => '11:15:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->oldRoom->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $this->mock(WhatsAppService::class, function ($mock): void {
                $mock->shouldReceive('notifyRoomChanged')
                    ->once()
                    ->andThrow(new \RuntimeException('Simulasi gangguan internal WhatsApp service'));
            });

            $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
                'room_id' => $this->newRoom->id,
                'move_reason' => 'Perbaikan proyektor ruangan awal',
            ]);

            $response->assertRedirect(route('agenda.index'));
            $response->assertSessionHas('warning');

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'room_id' => $this->newRoom->id,
                'move_reason' => 'Perbaikan proyektor ruangan awal',
            ]);
            $this->assertDatabaseHas('schedule_notifications', [
                'event_id' => $event->id,
                'user_id' => $this->guruOwner->id,
                'title' => 'Ruangan agenda dipindahkan',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Pastikan kegagalan di dalam transaksi DB (sebelum commit) membatalkan pemindahan dan TIDAK memanggil WhatsApp.
     */
    public function test_rolled_back_transaction_never_triggers_whatsapp_after_commit(): void
    {
        $this->configureFakeWhatsAppGateway();

        Http::preventStrayRequests();
        Http::fake();

        $event = Event::create([
            'event_name' => 'Agenda Uji Rollback',
            'date' => '2026-11-20',
            'end_date' => '2026-11-20',
            'start_time' => '08:15:00',
            'end_time' => '09:45:00',
            'requester_id' => $this->guruOwner->id,
            'room_id' => $this->oldRoom->id,
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
        ]);

        ScheduleNotification::creating(function (): void {
            throw new \RuntimeException('Simulasi kegagalan penyimpanan notifikasi internal');
        });

        try {
            $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
                'room_id' => $this->newRoom->id,
                'move_reason' => 'Seharusnya ikut rollback',
            ]);

            $response->assertStatus(500);

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'room_id' => $this->oldRoom->id,
                'move_reason' => null,
            ]);
            $this->assertDatabaseCount('schedule_notifications', 0);
            Http::assertNothingSent();
        } finally {
            ScheduleNotification::flushEventListeners();
        }
    }

    /**
     * Log pengiriman WhatsApp menyamarkan nomor lengkap pengguna.
     */
    public function test_whatsapp_logs_mask_full_phone_number(): void
    {
        $this->configureFakeWhatsAppGateway();

        Http::preventStrayRequests();
        Http::fake([
            'https://graph.facebook.com/v21.0/998877665544/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [
                    ['id' => 'wamid.HBgMpolicylog1'],
                ],
            ], 200),
        ]);

        $loggedContexts = [];
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) use (&$loggedContexts): bool {
                $loggedContexts[] = $context;

                return str_contains($message, 'Notifikasi WhatsApp');
            });

        $event = Event::create([
            'event_name' => 'Rapat Evaluasi',
            'date' => '2026-11-21',
            'end_date' => '2026-11-21',
            'start_time' => '08:15:00',
            'end_time' => '09:45:00',
            'requester_id' => $this->guruOwner->id,
            'room_id' => $this->oldRoom->id,
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
        ]);

        $result = app(WhatsAppService::class)->notifyRoomChanged(
            $event,
            $this->oldRoom,
            $this->newRoom,
            'Pengujian log aman'
        );

        $this->assertSame('accepted', $result['status']);
        $this->assertCount(1, $loggedContexts);
        $this->assertSame('6281******890', $loggedContexts[0]['recipient_phone'] ?? null);
        $this->assertStringNotContainsString('6281234567890', json_encode($loggedContexts));
        $this->assertStringNotContainsString('secret-meta-test-token', json_encode($loggedContexts));
    }

    /**
     * Fitur pengaduan kerusakan PIC yang sudah memakai wa.me tetap berjalan dengan nomor PIC ternormalisasi.
     */
    public function test_pic_complaint_whatsapp_warning_continues_working_with_normalized_phone(): void
    {
        $complaint = Complaint::create([
            'room_id' => $this->oldRoom->id,
            'reporter_id' => $this->guruOwner->id,
            'description' => 'Proyektor tidak menyala',
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('complaint.whatsapp', $complaint));

        $targetUrl = $response->headers->get('Location', '');
        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/6281111111111?text=', $targetUrl);
    }

    private function configureFakeWhatsAppGateway(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'meta_cloud_api',
            'services.whatsapp.dry_run' => false,
            'services.whatsapp.require_consent' => true,
            'services.whatsapp.test_allowlist_only' => false,
            'services.whatsapp.graph_base_url' => 'https://graph.facebook.com',
            'services.whatsapp.graph_version' => 'v21.0',
            'services.whatsapp.phone_number_id' => '998877665544',
            'services.whatsapp.access_token' => 'secret-meta-test-token',
            'services.whatsapp.template_name' => 'notifikasi_pemindahan_ruangan',
            'services.whatsapp.template_language' => 'id',
        ]);
    }

    private function actingAsApiUser(User $user): static
    {
        $token = 'test_' . bin2hex(random_bytes(20));
        Cache::put('api_token_' . hash('sha256', $token), $user->id, now()->addHour());

        return $this->withToken($token);
    }
}

