<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Models\User;
use App\Models\WhatsAppNotificationLog;
use App\Services\WhatsApp\MetaCloudApiDriver;
use App\Services\WhatsAppService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MetaWhatsAppCloudApiTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_TOKEN = 'EAAG-TEST-SECRET-TOKEN-DO-NOT-LEAK';
    private const FAKE_PHONE_ID = '987654321012345';

    private User $admin;
    private User $picOld;
    private User $picNew;
    private User $guruOwner;
    private User $otherGuru;
    private Room $roomA;
    private Room $roomB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin RAPI',
            'email' => 'admin.meta@rapi.test',
        ]);

        $this->picOld = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Ruang A',
            'email' => 'pica.meta@rapi.test',
            'phone' => '081111111111',
        ]);

        $this->picNew = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Ruang B',
            'email' => 'picb.meta@rapi.test',
            'phone' => '082222222222',
        ]);

        $this->guruOwner = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Pemilik',
            'email' => 'guru.owner.meta@rapi.test',
            'phone' => '081234567890',
        ]);

        $this->otherGuru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Lain Opt-In',
            'email' => 'guru.other.meta@rapi.test',
            'phone' => '085678901234',
        ]);
        $this->otherGuru->grantWhatsAppConsent('guru_self_service')->save();

        $this->roomA = Room::create([
            'room_name' => 'Aula Utama',
            'location' => 'Gedung A',
            'capacity' => 120,
            'pic_id' => $this->picOld->id,
            'is_active' => true,
        ]);

        $this->roomB = Room::create([
            'room_name' => 'Lab RPL 1',
            'location' => 'Gedung B',
            'capacity' => 36,
            'pic_id' => $this->picNew->id,
            'is_active' => true,
        ]);
    }

    /**
     * 1 & 2. Default konfigurasi aman & WHATSAPP_ENABLED=false memblokir seluruh HTTP request.
     */
    public function test_default_config_is_safe_and_enabled_false_blocks_all_http(): void
    {
        $this->assertFalse(config('services.whatsapp.enabled'));
        $this->assertSame('meta_cloud_api', config('services.whatsapp.driver'));
        $this->assertTrue(config('services.whatsapp.dry_run'));
        $this->assertTrue(config('services.whatsapp.require_consent'));
        $this->assertTrue(config('services.whatsapp.test_allowlist_only'));
        $this->assertSame('notifikasi_pemindahan_ruangan', config('services.whatsapp.template_name'));
        $this->assertSame('id', config('services.whatsapp.template_language'));
        $this->assertFalse(app(WhatsAppService::class)->isConfigured());

        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        config([
            'services.whatsapp.enabled' => false,
            'services.whatsapp.dry_run' => false,
            'services.whatsapp.test_allowlist_only' => false,
            'services.whatsapp.phone_number_id' => self::FAKE_PHONE_ID,
            'services.whatsapp.access_token' => self::FAKE_TOKEN,
        ]);

        Http::preventStrayRequests();
        Http::fake();

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
            'room_id' => $this->roomB->id,
            'move_reason' => 'Uji kill switch nonaktif',
        ]);

        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionHas('warning', fn (string $msg) => str_contains($msg, 'unconfigured'));

        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'event_id' => $event->id,
            'status' => WhatsAppNotificationLog::STATUS_UNCONFIGURED,
            'error_code' => 'WHATSAPP_DISABLED',
            'attempts' => 0,
        ]);
    }

    /**
     * 3 & 20. Mode dry_run mencatat log simulasi tanpa HTTP request dan memberi feedback jujur.
     */
    public function test_dry_run_mode_makes_zero_http_requests_and_records_honest_simulation_feedback(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();

        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => 'meta_cloud_api',
            'services.whatsapp.dry_run' => true,
            'services.whatsapp.require_consent' => true,
            'services.whatsapp.test_allowlist_only' => true,
            'services.whatsapp.test_allowlist' => ['081234567890'],
            'services.whatsapp.template_name' => 'notifikasi_pemindahan_ruangan',
            'services.whatsapp.template_language' => 'id',
            'services.whatsapp.phone_number_id' => self::FAKE_PHONE_ID,
            'services.whatsapp.access_token' => self::FAKE_TOKEN,
        ]);

        Http::preventStrayRequests();
        Http::fake();

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
            'room_id' => $this->roomB->id,
            'move_reason' => 'Simulasi persiapan server',
        ]);

        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('warning', fn (string $msg) => str_contains($msg, 'dry-run') && str_contains($msg, 'tidak dikirim ke WhatsApp'));

        Http::assertNothingSent();

        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'event_id' => $event->id,
            'old_room_id' => $this->roomA->id,
            'new_room_id' => $this->roomB->id,
            'recipient_user_id' => $this->guruOwner->id,
            'driver' => 'meta_cloud_api',
            'template_name' => 'notifikasi_pemindahan_ruangan',
            'recipient_phone_masked' => '6281******890',
            'is_dry_run' => true,
            'status' => WhatsAppNotificationLog::STATUS_DRY_RUN,
            'meta_message_id' => null,
            'http_status' => null,
            'attempts' => 0,
        ]);

        // Detail Agenda juga menampilkan status simulasi secara jujur tanpa membocorkan nomor lengkap
        $detail = $this->actingAs($this->admin)->get(route('events.show', $event));
        $detail->assertOk();
        $detail->assertSeeText('Simulasi (Dry-Run)');
        $detail->assertSeeText('6281******890');
        $detail->assertDontSee('6281234567890');
        $detail->assertDontSee(self::FAKE_TOKEN);
    }

    /**
     * 4 & 7. Guru tanpa consent tidak dikirimi WhatsApp meskipun masuk allowlist uji.
     */
    public function test_unconsented_guru_is_blocked_even_when_phone_is_in_allowlist(): void
    {
        $this->assertFalse($this->guruOwner->hasWhatsAppConsent());
        $this->configureLiveMeta([
            'test_allowlist_only' => true,
            'test_allowlist' => ['081234567890'],
        ]);

        Http::preventStrayRequests();
        Http::fake();

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
            'room_id' => $this->roomB->id,
            'move_reason' => 'Belum ada opt-in',
        ]);

        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionHas('warning', fn (string $msg) => str_contains($msg, 'skipped_no_consent'));

        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'event_id' => $event->id,
            'status' => WhatsAppNotificationLog::STATUS_SKIPPED_NO_CONSENT,
            'error_code' => 'CONSENT_REQUIRED',
            'attempts' => 0,
        ]);
        $this->assertDatabaseHas('schedule_notifications', [
            'event_id' => $event->id,
            'user_id' => $this->guruOwner->id,
        ]);
    }

    /**
     * 5 & 6. Allowlist memblokir nomor di luar daftar & menerima format 08..., 628..., +628... ternormalisasi.
     */
    public function test_allowlist_blocks_unlisted_numbers_and_normalizes_indonesian_formats(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service', '081234567890')->save();

        // Nomor Guru TIDAK ada di allowlist -> diblokir dengan status skipped_not_allowlisted
        $this->configureLiveMeta([
            'test_allowlist_only' => true,
            'test_allowlist' => ['+628999888777'],
        ]);

        Http::preventStrayRequests();

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        $blockedResponse = $this->actingAs($this->admin)->put(route('events.move', $event), [
            'room_id' => $this->roomB->id,
            'move_reason' => 'Nomor belum masuk allowlist',
        ]);

        $blockedResponse->assertRedirect(route('agenda.index'));
        $blockedResponse->assertSessionHas('warning', fn (string $msg) => str_contains($msg, 'skipped_not_allowlisted'));

        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'event_id' => $event->id,
            'status' => WhatsAppNotificationLog::STATUS_SKIPPED_NOT_ALLOWLISTED,
            'attempts' => 0,
        ]);

        // Allowlist mendukung variasi format 08..., 628..., +628... serta string dipisahkan koma
        config([
            'services.whatsapp.test_allowlist' => '+62899-9888-777, +6281234567890, 081111111111',
        ]);
        $this->assertContains('6281234567890', app(WhatsAppService::class)->normalizedAllowlist());

        Http::fake([
            $this->graphMessagesEndpoint() => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.allowlist.ok']],
            ], 200),
        ]);

        $allowedResponse = $this->actingAs($this->admin)->put(route('events.move', $event->fresh()), [
            'room_id' => $this->roomA->id,
            'move_reason' => 'Nomor sudah masuk allowlist via +628',
        ]);

        $allowedResponse->assertRedirect(route('agenda.index'));
        $allowedResponse->assertSessionMissing('warning');
        Http::assertSentCount(1);
        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'event_id' => $event->id,
            'status' => WhatsAppNotificationLog::STATUS_ACCEPTED,
            'meta_message_id' => 'wamid.allowlist.ok',
            'http_status' => 200,
        ]);
    }

    /**
     * 8, 9, 13, 20. Pengiriman Meta Cloud API hanya ke Guru pemilik yang opt-in,
     * PIC/Admin/Guru lain dikecualikan, 6 parameter terurut & bebas karakter ilegal,
     * serta status UI jujur membedakan accepted vs delivered.
     */
    public function test_meta_cloud_api_sends_sanitized_6_param_template_only_to_consented_owner_and_distinguishes_accepted_from_delivered(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 08:30:00', 'Asia/Jakarta'));

        try {
            $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
            $this->configureLiveMeta([
                'test_allowlist_only' => true,
                'test_allowlist' => ['081234567890', '081111111111', '082222222222', '085678901234'],
            ]);

            Http::preventStrayRequests();
            Http::fake([
                $this->graphMessagesEndpoint() => Http::response([
                    'messaging_product' => 'whatsapp',
                    'contacts' => [['input' => '6281234567890', 'wa_id' => '6281234567890']],
                    'messages' => [['id' => 'wamid.HBgMNjI4MTIzNDU2Nzg5MA==']],
                ], 200),
            ]);

            $event = Event::create([
                'event_name' => "Workshop   LKS\r\n\tNasional   2026",
                'date' => '2026-11-20',
                'end_date' => '2026-11-21',
                'start_time' => '08:15:00',
                'end_time' => '11:15:00',
                'requester_id' => $this->guruOwner->id,
                'room_id' => $this->roomA->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
                'room_id' => $this->roomB->id,
                'move_reason' => "Perbaikan\t\tlistrik\r\ndan     AC  ruang utama",
            ]);

            $response->assertRedirect(route('agenda.index'));
            $response->assertSessionHas(
                'success',
                fn (string $msg) => str_contains($msg, 'accepted') && str_contains($msg, 'menunggu pengiriman')
            );
            $response->assertSessionMissing('warning');

            Http::assertSentCount(1);
            Http::assertSent(function ($request) {
                $data = $request->data();
                $params = (array) data_get($data, 'template.components.0.parameters', []);
                $texts = array_column($params, 'text');

                foreach ($texts as $paramText) {
                    if (preg_match('/[\r\n\t]/', $paramText) || str_contains($paramText, '     ')) {
                        return false;
                    }
                }

                return $request->url() === $this->graphMessagesEndpoint()
                    && $request->hasHeader('Authorization', 'Bearer ' . self::FAKE_TOKEN)
                    && ($data['messaging_product'] ?? '') === 'whatsapp'
                    && ($data['type'] ?? '') === 'template'
                    && ($data['to'] ?? '') === '6281234567890'
                    && data_get($data, 'template.name') === 'notifikasi_pemindahan_ruangan'
                    && data_get($data, 'template.language.code') === 'id'
                    && count($params) === 6
                    && $texts === [
                        'Workshop LKS Nasional 2026',
                        '20 November 2026 s/d 21 November 2026',
                        '08:15 - 11:15 WIB',
                        'Aula Utama',
                        'Lab RPL 1',
                        'Perbaikan listrik dan AC ruang utama',
                    ];
            });

            Http::assertNotSent(fn ($request) => in_array(
                $request->data()['to'] ?? '',
                ['6281111111111', '6282222222222', '6285678901234'],
                true
            ));

            // Verifikasi halaman Detail Agenda menampilkan accepted secara jujur (bukan delivered)
            $detail = $this->actingAs($this->admin)->get(route('events.show', $event));
            $detail->assertOk();
            $detail->assertSeeText('Diterima Meta (Menunggu Pengiriman)');
            $detail->assertDontSeeText('Terkirim ke Perangkat');

            // Uji fallback alasan saat pindah ruangan melalui form Edit Agenda (tanpa input alasan)
            $editMove = $this->actingAs($this->admin)->put(route('agenda.update', $event), [
                'judul' => 'Workshop LKS',
                'tanggal' => '2026-11-20',
                'tanggal_selesai' => '2026-11-21',
                'sesi_mulai' => 2,
                'sesi_selesai' => 5,
                'ruangan_id' => $this->roomA->id,
                'keterangan' => 'Edit oleh admin',
            ]);
            $editMove->assertRedirect(route('agenda.index'));

            Http::assertSentCount(2);
            Http::assertSent(function ($request) {
                $params = array_column((array) data_get($request->data(), 'template.components.0.parameters', []), 'text');

                return count($params) === 6
                    && ($params[5] ?? '') === 'Penyesuaian jadwal dan penggunaan ruangan oleh Admin.';
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 10 & 11. Agenda Admin & edit mandiri Guru tidak mengirim WhatsApp.
     */
    public function test_admin_event_and_guru_self_edit_never_trigger_meta_cloud_api(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        $this->configureLiveMeta(['test_allowlist_only' => false]);

        Http::preventStrayRequests();
        Http::fake();

        $adminEvent = $this->createApprovedEvent($this->admin, $this->roomA);
        $this->actingAs($this->admin)->put(route('events.move', $adminEvent), [
            'room_id' => $this->roomB->id,
            'move_reason' => 'Agenda internal admin',
        ])->assertRedirect(route('agenda.index'));

        $pendingGuruEvent = Event::create([
            'event_name' => 'Latihan Paduan Suara',
            'date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'start_time' => '08:15:00',
            'end_time' => '09:45:00',
            'requester_id' => $this->guruOwner->id,
            'room_id' => $this->roomA->id,
            'status' => 'Menunggu',
        ]);

        $this->actingAsApiUser($this->guruOwner)
            ->putJson("/api/agenda/{$pendingGuruEvent->id}", [
                'judul' => 'Latihan Paduan Suara Revisi',
                'tanggal' => now()->addDays(5)->toDateString(),
                'jam_mulai' => '08:15',
                'jam_selesai' => '09:45',
                'ruangan_id' => $this->roomB->id,
                'keterangan' => 'Ubah ruangan mandiri sebelum disetujui',
            ])
            ->assertOk();

        Http::assertNothingSent();
        $this->assertDatabaseCount('whatsapp_notification_logs', 0);
    }

    /**
     * 12. Mengganti nomor HP mencabut consent lama sehingga wajib re-consent sebelum pengiriman berikutnya.
     */
    public function test_changing_phone_revokes_consent_and_blocks_next_room_move_until_reconsented(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service', '081234567890')->save();
        $this->configureLiveMeta(['test_allowlist_only' => false]);

        $this->guruOwner->update(['phone' => '089876543210']);
        $this->assertFalse($this->guruOwner->fresh()->hasWhatsAppConsent());

        Http::preventStrayRequests();
        Http::fake();

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
            'room_id' => $this->roomB->id,
            'move_reason' => 'Nomor baru belum opt-in ulang',
        ]);

        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionHas('warning', fn (string $msg) => str_contains($msg, 'skipped_no_consent'));

        Http::assertNothingSent();
        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'event_id' => $event->id,
            'status' => WhatsAppNotificationLog::STATUS_SKIPPED_NO_CONSENT,
            'recipient_phone_masked' => '6289******210',
        ]);
    }

    /**
     * 14. Konfigurasi tidak lengkap (template/credentials/endpoint) ditangani sebagai unconfigured tanpa HTTP.
     */
    public function test_incomplete_meta_and_template_configurations_are_safely_marked_unconfigured(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        Http::preventStrayRequests();
        Http::fake();

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        $cases = [
            ['phone_number_id' => '', 'dry_run' => false],
            ['access_token' => '', 'dry_run' => false],
            ['template_name' => '', 'dry_run' => true],
            ['template_language' => '', 'dry_run' => true],
            ['graph_base_url' => 'http://insecure.example.test', 'dry_run' => false],
        ];

        $service = app(WhatsAppService::class);
        foreach ($cases as $index => $override) {
            $this->configureLiveMeta(array_merge(['test_allowlist_only' => false], $override));

            $result = $service->notifyRoomChanged(
                $event,
                $this->roomA,
                $this->roomB,
                'Uji config #' . $index
            );

            $this->assertSame(WhatsAppNotificationLog::STATUS_UNCONFIGURED, $result['status']);
            $this->assertSame(0, $result['sent_count']);
        }

        Http::assertNothingSent();
        $this->assertSame(count($cases), WhatsAppNotificationLog::where('status', 'unconfigured')->count());
    }

    /**
     * 15 & 17. Klasifikasi error HTTP 400/401/429/500 & timeout ConnectionException dicatat jujur tanpa rollback
     * dan tanpa membocorkan token maupun nomor HP penuh di DB, logs, UI, atau response.
     */
    public function test_meta_api_errors_and_timeouts_fail_safely_without_rollback_or_secret_leaks(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        $this->configureLiveMeta(['test_allowlist_only' => false]);
        Http::preventStrayRequests();

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        $errorResponses = [
            400 => ['error' => ['message' => 'Bad request for phone 6281234567890 token ' . self::FAKE_TOKEN, 'code' => 132018]],
            401 => ['error' => ['message' => 'Invalid OAuth access token: Bearer ' . self::FAKE_TOKEN, 'code' => 190]],
            429 => ['error' => ['message' => 'Rate limit hit', 'code' => 130429]],
            500 => ['error' => ['message' => 'Internal Meta failure', 'code' => 2]],
        ];

        $logsByCode = [];
        foreach ($errorResponses as $httpCode => $payload) {
            $this->resetHttpFake([
                $this->graphMessagesEndpoint() => Http::response($payload, $httpCode),
            ]);

            $result = app(WhatsAppService::class)->notifyRoomChanged(
                $event,
                $this->roomA,
                $this->roomB,
                'Uji error ' . $httpCode,
                'err-key-' . $httpCode
            );

            $this->assertSame(WhatsAppNotificationLog::STATUS_FAILED, $result['status']);
            $this->assertSame(0, $result['sent_count']);
            $this->assertSame(1, $result['failed_count']);
            $logsByCode[$httpCode] = WhatsAppNotificationLog::findOrFail($result['log_id']);
        }

        // Kebijakan retry terklasifikasi: 400, 401, dan 500 tidak boleh di-retry otomatis (next_retry_at null); hanya 429 yang terjadwal retry terkontrol.
        $this->assertNull($logsByCode[400]->next_retry_at);
        $this->assertNull($logsByCode[401]->next_retry_at);
        $this->assertNotNull($logsByCode[429]->next_retry_at);
        $this->assertNull($logsByCode[500]->next_retry_at);
        $this->assertStringStartsWith('PERMANENT_HTTP_400', (string) $logsByCode[400]->error_code);
        $this->assertStringStartsWith('PERMANENT_HTTP_401', (string) $logsByCode[401]->error_code);
        $this->assertStringStartsWith('RATE_LIMITED', (string) $logsByCode[429]->error_code);
        $this->assertStringStartsWith('HTTP_5XX_AMBIGUOUS', (string) $logsByCode[500]->error_code);

        // Timeout melalui endpoint API dan UI Web dicatat ambiguous_timeout tanpa membatalkan pemindahan maupun ScheduleNotification
        $this->resetHttpFake([
            $this->graphMessagesEndpoint() => fn () => throw new ConnectionException(
                'cURL error 28: timed out sending to 6281234567890 with Bearer ' . self::FAKE_TOKEN
            ),
        ]);

        $webMove = $this->actingAs($this->admin)->put(route('events.move', $event), [
            'room_id' => $this->roomB->id,
            'move_reason' => 'Timeout Meta Web',
        ]);

        $webMove->assertRedirect(route('agenda.index'));
        $webMove->assertSessionHas('warning', fn (string $msg) => str_contains($msg, 'ambiguous_timeout'));

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'room_id' => $this->roomB->id,
            'move_reason' => 'Timeout Meta Web',
        ]);
        $this->assertDatabaseHas('schedule_notifications', [
            'event_id' => $event->id,
            'user_id' => $this->guruOwner->id,
        ]);

        $apiMove = $this->actingAsApiUser($this->admin)
            ->putJson("/api/agenda/{$event->id}/pindah-ruangan", [
                'ruangan_id' => $this->roomA->id,
                'alasan' => 'Timeout Meta API',
            ]);

        $apiMove->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('whatsapp_notification.status', 'ambiguous_timeout');

        $apiJson = $apiMove->getContent() ?: '';
        $this->assertStringNotContainsString(self::FAKE_TOKEN, $apiJson);
        $this->assertStringNotContainsString('6281234567890', $apiJson);

        // Audit DB logs tidak boleh menyimpan token atau nomor telepon lengkap
        $allLogs = WhatsAppNotificationLog::all();
        $this->assertCount(6, $allLogs);

        foreach ($allLogs as $log) {
            $rawError = (string) $log->error_message;
            $this->assertStringNotContainsString(self::FAKE_TOKEN, $rawError);
            $this->assertStringNotContainsString('6281234567890', $rawError);
            $this->assertSame('6281******890', $log->recipient_phone_masked);
            if ($log->status === WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT) {
                $this->assertNull($log->next_retry_at);
            }
        }

        // Detail Agenda UI juga bebas token & nomor penuh
        $detailHtml = $this->actingAs($this->admin)->get(route('events.show', $event))->getContent() ?: '';
        $this->assertStringNotContainsString(self::FAKE_TOKEN, $detailHtml);
        $this->assertStringNotContainsString('6281234567890', $detailHtml);
    }

    /**
     * 16. Idempotency mencegah duplikasi pindah ruangan yang sama, namun A->B, B->A, A->B tetap diproses.
     */
    public function test_idempotency_blocks_duplicate_same_move_but_allows_sequential_room_move_cycles(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 09:00:00', 'Asia/Jakarta'));

        try {
            $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
            $this->configureLiveMeta(['test_allowlist_only' => false]);

            Http::preventStrayRequests();
            Http::fake([
                $this->graphMessagesEndpoint() => Http::sequence()
                    ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.cycle.1']]], 200)
                    ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.cycle.2']]], 200)
                    ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.cycle.3']]], 200)
                    ->push(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.explicit.1']]], 200),
            ]);

            $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);
            $service = app(WhatsAppService::class);

            // 1. A -> B berhasil
            $first = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Pindah ke Lab');
            $this->assertSame('accepted', $first['status']);

            // 2. Duplikat panggilan langsung A -> B diblokir idempotency
            $dup = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Pindah ke Lab');
            $this->assertSame('skipped_duplicate', $dup['status']);

            // 3. Perpindahan sah berikutnya B -> A tetap mengirim notifikasi baru
            $second = $service->notifyRoomChanged($event, $this->roomB, $this->roomA, 'Kembali ke Aula');
            $this->assertSame('accepted', $second['status']);

            // 4. Perpindahan sah kembali ke A -> B lagi di detik yang sama tetap mengirim notifikasi baru
            $third = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Dipindah lagi ke Lab');
            $this->assertSame('accepted', $third['status']);

            // 5. Explicit idempotency key juga mencegah pengiriman ulang
            $explicitFirst = $service->notifyRoomChanged($event, $this->roomB, $this->roomA, 'Uji key', 'move-key-xyz');
            $explicitSecond = $service->notifyRoomChanged($event, $this->roomB, $this->roomA, 'Uji key', 'move-key-xyz');
            $this->assertSame('accepted', $explicitFirst['status']);
            $this->assertSame('skipped_duplicate', $explicitSecond['status']);

            Http::assertSentCount(4);
            $this->assertSame(4, WhatsAppNotificationLog::where('status', 'accepted')->count());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 17 (Logging). Pastikan Laravel Log tidak menerima token atau nomor penuh bahkan saat error.
     */
    public function test_laravel_logs_never_receive_access_token_or_full_phone_number(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        $this->configureLiveMeta(['test_allowlist_only' => false]);

        Http::preventStrayRequests();
        Http::fake([
            $this->graphMessagesEndpoint() => Http::response([
                'error' => [
                    'message' => 'Error for 6281234567890 Bearer ' . self::FAKE_TOKEN,
                    'code' => 400,
                ],
            ], 400),
        ]);

        $capturedLogs = [];
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use (&$capturedLogs): bool {
                $capturedLogs[] = ['message' => $message, 'context' => $context];

                return true;
            });

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);
        app(WhatsAppService::class)->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Cek log aman');

        $serialized = json_encode($capturedLogs) ?: '';
        $this->assertStringNotContainsString(self::FAKE_TOKEN, $serialized);
        $this->assertStringNotContainsString('6281234567890', $serialized);
        $this->assertStringContainsString('6281******890', $serialized);
    }

    /**
     * 19. Fitur pengaduan kerusakan PIC menggunakan wa.me tetap berfungsi normal.
     */
    public function test_pic_complaint_wa_me_workflow_remains_intact(): void
    {
        $complaint = Complaint::create([
            'room_id' => $this->roomA->id,
            'reporter_id' => $this->guruOwner->id,
            'description' => 'Audio tidak keluar',
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('complaint.whatsapp', $complaint));

        $response->assertRedirect();
        $this->assertStringStartsWith(
            'https://wa.me/6281111111111?text=',
            (string) $response->headers->get('Location', '')
        );
    }

    /**
     * 20b. Respons 2xx tanpa messages.0.id ditolak sebagai failed, label status dibedakan jujur,
     * dan halaman Pindah Ruangan Admin menampilkan status kesiapan tanpa membocorkan nomor penuh.
     */
    public function test_2xx_without_message_id_fails_and_admin_move_form_displays_safe_readiness_status(): void
    {
        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        // Sebelum opt-in -> form pindah ruangan menginformasikan belum ada consent tanpa nomor penuh
        $formBefore = $this->actingAs($this->admin)->get(route('events.move.form', $event));
        $formBefore->assertOk();
        $formBefore->assertSeeText('belum menyetujui (opt-in)');
        $formBefore->assertDontSee('6281234567890');

        // Setelah opt-in + mode dry_run aktif -> form menampilkan nomor tersamar dan mode simulasi
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.dry_run' => true,
        ]);

        $formReady = $this->actingAs($this->admin)->get(route('events.move.form', $event));
        $formReady->assertOk();
        $formReady->assertSeeText('6281******890');
        $formReady->assertSeeText('simulasi (dry-run)');
        $formReady->assertDontSee('6281234567890');

        // Respons HTTP 200 yang tidak menyertakan messages.0.id wajib dianggap failed (bukan accepted)
        $this->configureLiveMeta(['test_allowlist_only' => false]);
        Http::preventStrayRequests();
        Http::fake([
            $this->graphMessagesEndpoint() => Http::response(['messaging_product' => 'whatsapp'], 200),
        ]);

        $result = app(WhatsAppService::class)->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Respons 2xx cacat');
        $this->assertSame(WhatsAppNotificationLog::STATUS_FAILED, $result['status']);
        $this->assertDatabaseHas('whatsapp_notification_logs', [
            'event_id' => $event->id,
            'status' => WhatsAppNotificationLog::STATUS_FAILED,
            'error_code' => 'INVALID_META_RESPONSE',
            'http_status' => 200,
        ]);

        // Verifikasi seluruh status_label berbeda dan jujur
        $expectedLabels = [
            WhatsAppNotificationLog::STATUS_PENDING => 'Sedang Diproses (Pending)',
            WhatsAppNotificationLog::STATUS_DRY_RUN => 'Simulasi (Dry-Run)',
            WhatsAppNotificationLog::STATUS_SKIPPED_NO_CONSENT => 'Dilewati (Belum Opt-In)',
            WhatsAppNotificationLog::STATUS_SKIPPED_NOT_ALLOWLISTED => 'Dilewati (Di Luar Allowlist)',
            WhatsAppNotificationLog::STATUS_UNCONFIGURED => 'Konfigurasi Belum Lengkap',
            WhatsAppNotificationLog::STATUS_ACCEPTED => 'Diterima Meta (Menunggu Pengiriman)',
            WhatsAppNotificationLog::STATUS_DELIVERED => 'Terkirim ke Perangkat',
            WhatsAppNotificationLog::STATUS_READ => 'Dibaca Penerima',
            WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT => 'Timeout Ambigu (Perlu Rekonsiliasi)',
            WhatsAppNotificationLog::STATUS_CONNECTION_FAILED => 'Gagal Koneksi (Belum Terkirim)',
            WhatsAppNotificationLog::STATUS_FAILED => 'Gagal Dikirim',
        ];

        foreach ($expectedLabels as $status => $label) {
            $log = new WhatsAppNotificationLog(['status' => $status]);
            $this->assertSame($label, $log->status_label);
        }

        // Preview payload bebas token & sesuai struktur Meta
        $preview = app(WhatsAppService::class)->previewTemplatePayload(
            $event,
            '6281234567890',
            'Aula Utama',
            'Lab RPL 1',
            "Pengecekan\npreview"
        );
        $this->assertSame('whatsapp', $preview['messaging_product']);
        $this->assertSame('template', $preview['type']);
        $this->assertSame('notifikasi_pemindahan_ruangan', data_get($preview, 'template.name'));
        $this->assertSame('id', data_get($preview, 'template.language.code'));
        $this->assertSame('Pengecekan preview', data_get($preview, 'template.components.0.parameters.5.text'));
    }

    /**
     * H1–H4 & Bagian C–D:
     * Pre-HTTP claim `pending` tercipta sebelum request Meta berjalan,
     * siklus A -> B -> A -> B melalui controller menghasilkan 3 transition_id & versi atomik berbeda,
     * dan pemanggilan ulang callback pada transisi yang sama diblokir tanpa HTTP kedua.
     */
    public function test_pre_http_pending_claim_and_controller_room_move_cycle_a_b_a_b(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'Asia/Jakarta'));

        try {
            $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
            $this->configureLiveMeta(['test_allowlist_only' => false]);

            $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);
            $pendingSeenInsideHttp = [];

            Http::preventStrayRequests();
            $attemptCounter = 0;
            Http::fake([
                $this->graphMessagesEndpoint() => function () use ($event, &$pendingSeenInsideHttp, &$attemptCounter) {
                    $attemptCounter++;
                    $freshEvent = $event->fresh();
                    $pendingRow = WhatsAppNotificationLog::query()
                        ->where('event_id', $event->id)
                        ->where('transition_id', $freshEvent?->last_room_move_transition_id)
                        ->first();

                    $pendingSeenInsideHttp[] = [
                        'exists_before_http_returns' => $pendingRow !== null,
                        'status_before_http_returns' => $pendingRow?->status,
                        'attempts_before_http_returns' => $pendingRow?->attempts,
                    ];

                    return Http::response([
                        'messaging_product' => 'whatsapp',
                        'messages' => [['id' => 'wamid.cycle.ctrl.' . $attemptCounter]],
                    ], 200);
                },
            ]);

            // 1. A -> B lewat Web move()
            $this->actingAs($this->admin)->put(route('events.move', $event), [
                'room_id' => $this->roomB->id,
                'current_room_id' => $this->roomA->id,
                'move_reason' => 'Transisi pertama A ke B',
            ])->assertRedirect(route('agenda.index'));

            $eventAfter1 = $event->fresh();
            $this->assertSame(1, (int) $eventAfter1->room_move_version);
            $transition1 = (string) $eventAfter1->last_room_move_transition_id;
            $this->assertNotSame('', $transition1);

            // Replay callback untuk transisi 1 tidak boleh mengirim HTTP kedua
            $replay1 = app(WhatsAppService::class)->notifyRoomChanged(
                $eventAfter1,
                $this->roomA,
                $this->roomB,
                'Transisi pertama A ke B',
                null,
                $transition1
            );
            $this->assertSame('skipped_duplicate', $replay1['status']);
            Http::assertSentCount(1);

            // 2. B -> A lewat API moveRoom()
            $this->actingAsApiUser($this->admin)
                ->putJson("/api/agenda/{$event->id}/pindah-ruangan", [
                    'ruangan_id' => $this->roomA->id,
                    'current_room_id' => $this->roomB->id,
                    'alasan' => 'Transisi kedua B ke A',
                ])
                ->assertOk()
                ->assertJsonPath('whatsapp_notification.status', 'accepted');

            $eventAfter2 = $event->fresh();
            $this->assertSame(2, (int) $eventAfter2->room_move_version);
            $transition2 = (string) $eventAfter2->last_room_move_transition_id;
            $this->assertNotSame($transition1, $transition2);

            // 3. A -> B lagi lewat Web move() -> transisi sah baru ke-3
            $this->actingAs($this->admin)->put(route('events.move', $event), [
                'room_id' => $this->roomB->id,
                'current_room_id' => $this->roomA->id,
                'move_reason' => 'Transisi ketiga A ke B lagi',
            ])->assertRedirect(route('agenda.index'));

            $eventAfter3 = $event->fresh();
            $this->assertSame(3, (int) $eventAfter3->room_move_version);
            $transition3 = (string) $eventAfter3->last_room_move_transition_id;
            $this->assertCount(3, array_unique([$transition1, $transition2, $transition3]));

            Http::assertSentCount(3);
            $this->assertCount(3, $pendingSeenInsideHttp);
            foreach ($pendingSeenInsideHttp as $snapshot) {
                $this->assertTrue($snapshot['exists_before_http_returns']);
                $this->assertSame(WhatsAppNotificationLog::STATUS_PENDING, $snapshot['status_before_http_returns']);
                $this->assertSame(1, $snapshot['attempts_before_http_returns']);
            }

            $logs = WhatsAppNotificationLog::where('event_id', $event->id)->orderBy('id')->get();
            $this->assertCount(3, $logs);
            $this->assertSame([1, 2, 3], $logs->pluck('room_move_version')->map(fn ($v) => (int) $v)->all());
            $this->assertSame([$transition1, $transition2, $transition3], $logs->pluck('transition_id')->all());
            $this->assertSame(['accepted', 'accepted', 'accepted'], $logs->pluck('status')->all());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * H9–H10 & Bagian E:
     * Timeout -> ambiguous_timeout tanpa retry otomatis & replay diblokir;
     * connection_failed & HTTP 429 dapat di-retry terkontrol memakai klaim & transition_id yang sama;
     * HTTP 400/401/500 tidak boleh di-retry otomatis dan 500 masuk antrean rekonsiliasi.
     */
    public function test_timeout_ambiguity_vs_provable_connection_failure_rate_limit_and_no_auto_retry_policies(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        $this->configureLiveMeta(['test_allowlist_only' => false]);
        Http::preventStrayRequests();

        $service = app(WhatsAppService::class);
        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        // 1. Timeout ambigu -> status ambiguous_timeout, replay dan retry ditolak, panggilan HTTP tetap 1
        $timeoutHttpAttempts = 0;
        $this->resetHttpFake([
            $this->graphMessagesEndpoint() => function () use (&$timeoutHttpAttempts) {
                $timeoutHttpAttempts++;
                throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds');
            },
        ]);

        $timeoutResult = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Uji timeout', null, 'tr-timeout-01');
        $this->assertSame(WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT, $timeoutResult['status']);
        $this->assertSame(1, $timeoutHttpAttempts);

        $timeoutLog = WhatsAppNotificationLog::findOrFail($timeoutResult['log_id']);
        $this->assertSame('TIMEOUT_AMBIGUOUS', $timeoutLog->error_code);
        $this->assertNull($timeoutLog->next_retry_at);

        $replayAfterTimeout = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Uji timeout', null, 'tr-timeout-01');
        $this->assertSame('skipped_duplicate', $replayAfterTimeout['status']);

        $retryAfterTimeout = $service->retryFailedNotification($timeoutLog);
        $this->assertSame('skipped_not_retryable', $retryAfterTimeout['status']);
        $this->assertSame(1, $timeoutHttpAttempts);

        // 2. Koneksi gagal pra-kirim yang dapat dibuktikan -> connection_failed -> retry terkontrol berhasil pada baris klaim yang sama
        $this->resetHttpFake([
            $this->graphMessagesEndpoint() => fn () => throw new ConnectionException('cURL error 7: Failed to connect to graph.facebook.com port 443: Connection refused'),
        ]);

        $connResult = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Uji DNS/Refused', null, 'tr-conn-02');
        $this->assertSame(WhatsAppNotificationLog::STATUS_CONNECTION_FAILED, $connResult['status']);

        $connLog = WhatsAppNotificationLog::findOrFail($connResult['log_id']);
        $this->assertSame('CONNECTION_FAILED', $connLog->error_code);
        $this->assertNotNull($connLog->next_retry_at);
        $this->assertSame(1, (int) $connLog->attempts);

        $this->resetHttpFake([
            $this->graphMessagesEndpoint() => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.conn.retry.ok']],
            ], 200),
        ]);

        $connRetry = $service->retryFailedNotification($connLog);
        $this->assertSame(WhatsAppNotificationLog::STATUS_ACCEPTED, $connRetry['status']);
        $this->assertSame($connLog->id, $connRetry['log_id']);
        $this->assertSame('tr-conn-02', $connRetry['transition_id']);
        $this->assertSame(2, (int) $connLog->fresh()->attempts);

        // 3. HTTP 429 -> RATE_LIMITED + next_retry_at mengikuti Retry-After -> retry terkontrol pada baris yang sama
        $this->resetHttpFake([
            $this->graphMessagesEndpoint() => Http::response([
                'error' => ['message' => 'Too many calls', 'code' => 130429],
            ], 429, ['Retry-After' => '120']),
        ]);

        $rateResult = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Uji 429', null, 'tr-429-03');
        $this->assertSame(WhatsAppNotificationLog::STATUS_FAILED, $rateResult['status']);
        $rateLog = WhatsAppNotificationLog::findOrFail($rateResult['log_id']);
        $this->assertSame('RATE_LIMITED:130429', $rateLog->error_code);
        $this->assertNotNull($rateLog->next_retry_at);
        $this->assertTrue($rateLog->isRetryableFailure());

        $this->resetHttpFake([
            $this->graphMessagesEndpoint() => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.429.retry.ok']],
            ], 200),
        ]);

        $rateRetry = $service->retryFailedNotification($rateLog);
        $this->assertSame(WhatsAppNotificationLog::STATUS_ACCEPTED, $rateRetry['status']);
        $this->assertSame($rateLog->id, $rateRetry['log_id']);
        $this->assertSame(2, (int) $rateLog->fresh()->attempts);

        // 4. HTTP 400, 401, dan 500 -> tidak pernah di-retry otomatis; 500 masuk antrean rekonsiliasi
        foreach ([400 => 'tr-400', 401 => 'tr-401', 500 => 'tr-500'] as $code => $trId) {
            $this->resetHttpFake([
                $this->graphMessagesEndpoint() => Http::response([
                    'error' => ['message' => 'Error ' . $code, 'code' => $code],
                ], $code),
            ]);

            $res = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Error ' . $code, null, $trId);
            $log = WhatsAppNotificationLog::findOrFail($res['log_id']);
            $this->assertNull($log->next_retry_at);
            $this->assertFalse($log->isRetryableFailure());
            $this->assertSame('skipped_not_retryable', $service->retryFailedNotification($log)['status']);
        }

        $reconcileIds = $service->findLogsNeedingReconciliation()->pluck('transition_id')->all();
        $this->assertContains('tr-timeout-01', $reconcileIds);
        $this->assertContains('tr-500', $reconcileIds);
        $this->assertNotContains('tr-400', $reconcileIds);
        $this->assertNotContains('tr-401', $reconcileIds);
    }

    /**
     * H11 & Bagian F:
     * HTTP 200 + wamid sukses diikuti kegagalan update log DB tetap mempertahankan klaim pending,
     * mencatat Log::critical tersanitasi, tidak membatalkan pindah ruangan, mencegah kirim ulang,
     * dan dapat diselesaikan melalui prosedur rekonsiliasi.
     */
    public function test_http_200_followed_by_db_log_update_failure_preserves_pending_claim_and_reconciles(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        $this->configureLiveMeta(['test_allowlist_only' => false]);

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);

        Http::preventStrayRequests();
        Http::fake([
            $this->graphMessagesEndpoint() => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.lost200.ok']],
            ], 200),
        ]);

        Log::spy();

        WhatsAppNotificationLog::updating(function (): void {
            throw new \RuntimeException(
                'Simulated DB failure with Bearer ' . self::FAKE_TOKEN . ' phone 6281234567890'
            );
        });

        try {
            $response = $this->actingAs($this->admin)->put(route('events.move', $event), [
                'room_id' => $this->roomB->id,
                'current_room_id' => $this->roomA->id,
                'move_reason' => 'Uji gagal update log pasca 200',
            ]);

            $response->assertRedirect(route('agenda.index'));
            $response->assertSessionHas('success');
            $response->assertSessionHas('warning', fn (string $msg) => str_contains($msg, 'pending'));
        } finally {
            WhatsAppNotificationLog::flushEventListeners();
        }

        // Pemindahan ruangan & notifikasi internal tetap terkomit
        $freshEvent = $event->fresh();
        $this->assertSame($this->roomB->id, (int) $freshEvent->room_id);
        $this->assertSame(1, (int) $freshEvent->room_move_version);
        $this->assertDatabaseHas('schedule_notifications', [
            'event_id' => $event->id,
            'user_id' => $this->guruOwner->id,
        ]);

        // Klaim pending TIDAK dihapus dan dipertahankan
        $stuckLog = WhatsAppNotificationLog::where('event_id', $event->id)->firstOrFail();
        $this->assertSame(WhatsAppNotificationLog::STATUS_PENDING, $stuckLog->status);
        $this->assertSame($freshEvent->last_room_move_transition_id, $stuckLog->transition_id);

        // Log::critical terpanggil dan bebas token maupun nomor HP lengkap
        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $serialized = json_encode(['message' => $message, 'context' => $context]) ?: '';

                return str_contains($message, 'Gagal memperbarui status log klaim')
                    && ($context['intended_status'] ?? null) === 'accepted'
                    && ($context['meta_message_id'] ?? null) === 'wamid.lost200.ok'
                    && !str_contains($serialized, self::FAKE_TOKEN)
                    && !str_contains($serialized, '6281234567890');
            });

        // Replay callback tidak mengirim HTTP kedua
        $replay = app(WhatsAppService::class)->notifyRoomChanged(
            $freshEvent,
            $this->roomA,
            $this->roomB,
            'Uji gagal update log pasca 200',
            null,
            $freshEvent->last_room_move_transition_id
        );
        $this->assertSame('skipped_duplicate', $replay['status']);
        Http::assertSentCount(1);

        // Rekonsiliasi CLI `whatsapp:reconcile` mendeteksi & menyelesaikan baris pending tertinggal
        $this->artisan('whatsapp:reconcile', ['--pending-minutes' => 0])
            ->assertExitCode(0)
            ->expectsOutputToContain($stuckLog->transition_id);

        $this->artisan('whatsapp:reconcile', [
            '--log_id' => $stuckLog->id,
            '--status' => 'accepted',
            '--message_id' => 'wamid.lost200.ok',
            '--note' => 'Terverifikasi diterima di Meta Manager',
        ])->assertExitCode(0);

        $reconciledLog = $stuckLog->fresh();
        $this->assertSame(WhatsAppNotificationLog::STATUS_ACCEPTED, $reconciledLog->status);
        $this->assertSame('wamid.lost200.ok', $reconciledLog->meta_message_id);
        $this->assertNotNull($reconciledLog->reconciled_at);
        $this->assertSame('Terverifikasi diterima di Meta Manager', $reconciledLog->reconciliation_note);
    }

    /**
     * H12–H14 & H16:
     * Constraint UNIQUE(idempotency_key) menolak duplikat,
     * status pending / accepted / delivered / read / ambiguous_timeout / dry_run memblokir pengiriman kedua,
     * serta pemilik agenda non-Guru (Siswa/Admin/PIC) tidak menerima notifikasi WhatsApp.
     */
    public function test_unique_constraint_blocking_statuses_and_non_guru_siswa_exclusion(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        $this->configureLiveMeta(['test_allowlist_only' => false]);
        Http::preventStrayRequests();
        Http::fake();

        $event = $this->createApprovedEvent($this->guruOwner, $this->roomA);
        $service = app(WhatsAppService::class);

        // 1. Uji database UNIQUE(idempotency_key) menolak insert duplikat
        $manualKey = hash('sha256', 'unique-test-key');
        WhatsAppNotificationLog::create([
            'idempotency_key' => $manualKey,
            'event_id' => $event->id,
            'recipient_user_id' => $this->guruOwner->id,
            'driver' => MetaCloudApiDriver::DRIVER_NAME,
            'status' => WhatsAppNotificationLog::STATUS_PENDING,
        ]);

        $duplicateExceptionThrown = false;
        try {
            WhatsAppNotificationLog::create([
                'idempotency_key' => $manualKey,
                'event_id' => $event->id,
                'recipient_user_id' => $this->guruOwner->id,
                'driver' => MetaCloudApiDriver::DRIVER_NAME,
                'status' => WhatsAppNotificationLog::STATUS_PENDING,
            ]);
        } catch (QueryException) {
            $duplicateExceptionThrown = true;
        }
        $this->assertTrue($duplicateExceptionThrown, 'UNIQUE(idempotency_key) wajib melempar QueryException.');

        // 2. Semua status pemblokir mencegah pengiriman kedua dengan 0 HTTP
        foreach ([
            WhatsAppNotificationLog::STATUS_PENDING,
            WhatsAppNotificationLog::STATUS_ACCEPTED,
            WhatsAppNotificationLog::STATUS_DELIVERED,
            WhatsAppNotificationLog::STATUS_READ,
            WhatsAppNotificationLog::STATUS_AMBIGUOUS_TIMEOUT,
            WhatsAppNotificationLog::STATUS_DRY_RUN,
        ] as $blockingStatus) {
            $explicitKey = 'block-status-' . $blockingStatus;
            WhatsAppNotificationLog::create([
                'idempotency_key' => hash('sha256', 'explicit|' . $explicitKey),
                'event_id' => $event->id,
                'recipient_user_id' => $this->guruOwner->id,
                'driver' => MetaCloudApiDriver::DRIVER_NAME,
                'status' => $blockingStatus,
                'is_dry_run' => $blockingStatus === WhatsAppNotificationLog::STATUS_DRY_RUN,
            ]);

            if ($blockingStatus === WhatsAppNotificationLog::STATUS_DRY_RUN) {
                config(['services.whatsapp.dry_run' => true]);
            } else {
                config(['services.whatsapp.dry_run' => false]);
            }

            $res = $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Blokir ' . $blockingStatus, $explicitKey);
            $this->assertSame('skipped_duplicate', $res['status']);
        }

        // 3. Explicit key dengan karakter kontrol harus ditolak
        $this->expectException(\InvalidArgumentException::class);
        $service->notifyRoomChanged($event, $this->roomA, $this->roomB, 'Invalid key', "bad\nkey");
    }

    /**
     * H16 (Siswa), G, H7, H8:
     * Agenda Siswa/PIC tidak pernah memicu WhatsApp,
     * penyelarasan kebijakan Disetujui-only di Web (moveForm/move/update/show) dan API (moveRoom),
     * penolakan stale current_room_id dan bentrok jadwal, serta pembaruan move_reason saat edit Admin.
     */
    public function test_policy_alignment_stale_check_schedule_conflict_and_siswa_exclusion(): void
    {
        $this->guruOwner->grantWhatsAppConsent('guru_self_service')->save();
        $this->configureLiveMeta(['test_allowlist_only' => false]);
        Http::preventStrayRequests();

        $siswa = User::factory()->create(['role' => 'siswa', 'name' => 'Siswa Uji']);
        $roomC = Room::create([
            'room_name' => 'Lab Kimia',
            'location' => 'Gedung C',
            'capacity' => 32,
            'pic_id' => $this->picNew->id,
            'is_active' => true,
        ]);

        // 1. Agenda Siswa (simulasi historis) -> ditolak guard non-guru tanpa HTTP
        $siswaEvent = $this->createApprovedEvent($siswa, $this->roomA);
        Http::fake();
        $siswaMove = app(WhatsAppService::class)->notifyRoomChanged($siswaEvent, $this->roomA, $this->roomB, 'Uji non-guru siswa');
        $this->assertSame('skipped_non_guru', $siswaMove['status']);
        Http::assertNothingSent();

        // 2. Agenda berstatus Menunggu tidak dapat dipindahkan via UI tombol, Web moveForm/move, Web update ganti ruang, maupun API moveRoom
        $pendingEvent = Event::create([
            'event_name' => 'Agenda Menunggu Persetujuan',
            'date' => now()->addDays(4)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->guruOwner->id,
            'room_id' => $this->roomA->id,
            'status' => 'Menunggu',
        ]);

        $showPending = $this->actingAs($this->admin)->get(route('events.show', $pendingEvent));
        $showPending->assertOk();
        $showPending->assertSee(route('agenda.edit', $pendingEvent->id));
        $showPending->assertDontSee(route('events.move.form', $pendingEvent->id));

        $this->actingAs($this->admin)->get(route('events.move.form', $pendingEvent))
            ->assertRedirect(route('agenda.index'))
            ->assertSessionHas('error');

        $this->actingAs($this->admin)->put(route('events.move', $pendingEvent), [
            'room_id' => $this->roomB->id,
            'move_reason' => 'Bypass URL move Menunggu',
        ])->assertRedirect(route('agenda.index'))->assertSessionHas('error');

        $this->actingAs($this->admin)->put(route('agenda.update', $pendingEvent), [
            'judul' => 'Agenda Menunggu Persetujuan',
            'tanggal' => now()->addDays(4)->toDateString(),
            'sesi_mulai' => 2,
            'sesi_selesai' => 4,
            'ruangan_id' => $this->roomB->id,
            'keterangan' => 'Coba ganti ruang lewat edit',
        ])->assertSessionHasErrors('ruangan_id');

        // Edit detail tanpa mengubah ruangan pada status Menunggu tetap diperbolehkan
        $this->actingAs($this->admin)->put(route('agenda.update', $pendingEvent), [
            'judul' => 'Agenda Menunggu Judul Baru',
            'tanggal' => now()->addDays(4)->toDateString(),
            'sesi_mulai' => 2,
            'sesi_selesai' => 4,
            'ruangan_id' => $this->roomA->id,
            'keterangan' => 'Revisi detail tanpa pindah ruangan',
        ])->assertRedirect(route('agenda.index'))->assertSessionHas('success');

        $this->actingAsApiUser($this->admin)
            ->putJson("/api/agenda/{$pendingEvent->id}/pindah-ruangan", [
                'ruangan_id' => $this->roomB->id,
                'alasan' => 'Bypass API Menunggu',
            ])
            ->assertStatus(422);

        $this->assertSame($this->roomA->id, (int) $pendingEvent->fresh()->room_id);

        // 3. Agenda disetujui: stale current_room_id & bentrok jadwal ditolak di Web move(), Web update(), dan API moveRoom()
        $approvedEvent = Event::create([
            'event_name' => 'Rapat Koordinasi Disetujui',
            'date' => now()->addDays(6)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->guruOwner->id,
            'room_id' => $this->roomB->id, // Kondisi terbaru sudah pindah ke roomB
            'move_reason' => 'Alasan pemindahan lama',
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
        ]);

        // Bentrok multi-hari pada roomC (hari ke-2 bentrok!)
        Event::create([
            'event_name' => 'Agenda Penghalang di Lab Kimia',
            'date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:15:00',
            'requester_id' => $this->otherGuru->id,
            'room_id' => $roomC->id,
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
        ]);

        $notifBefore = ScheduleNotification::count();

        // Stale current_room_id (mengaku roomA padahal DB sudah roomB) ditolak di Web move, Web update, dan API moveRoom
        $this->actingAs($this->admin)->put(route('events.move', $approvedEvent), [
            'room_id' => $this->roomA->id,
            'current_room_id' => $this->roomA->id,
            'move_reason' => 'Stale tab Web move',
        ])->assertSessionHasErrors('room_id');

        $this->actingAs($this->admin)->put(route('agenda.update', $approvedEvent), [
            'judul' => $approvedEvent->event_name,
            'tanggal' => now()->addDays(6)->toDateString(),
            'tanggal_selesai' => now()->addDays(7)->toDateString(),
            'sesi_mulai' => 2,
            'sesi_selesai' => 4,
            'ruangan_id' => $this->roomA->id,
            'current_room_id' => $this->roomA->id,
            'keterangan' => 'Stale tab Web update',
        ])->assertSessionHasErrors('ruangan_id');

        $this->actingAsApiUser($this->admin)
            ->putJson("/api/agenda/{$approvedEvent->id}/pindah-ruangan", [
                'ruangan_id' => $this->roomA->id,
                'current_room_id' => $this->roomA->id,
                'alasan' => 'Stale client API',
            ])
            ->assertStatus(409);

        // Bentrok jadwal pada roomC ditolak di Web move() dan API moveRoom() (termasuk multi-day overlap)
        $this->actingAs($this->admin)->put(route('events.move', $approvedEvent), [
            'room_id' => $roomC->id,
            'current_room_id' => $this->roomB->id,
            'move_reason' => 'Coba pindah ke ruangan bentrok',
        ])->assertSessionHasErrors('room_id');

        $this->actingAsApiUser($this->admin)
            ->putJson("/api/agenda/{$approvedEvent->id}/pindah-ruangan", [
                'ruangan_id' => $roomC->id,
                'current_room_id' => $this->roomB->id,
                'alasan' => 'Coba API ke ruangan bentrok',
            ])
            ->assertStatus(422);

        $this->assertSame($notifBefore, ScheduleNotification::count());
        Http::assertNothingSent();

        // 4. Mengubah ruangan lewat EventController::update() memperbarui events.move_reason (tidak menyisakan alasan lama)
        $this->resetHttpFake([
            $this->graphMessagesEndpoint() => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.edit.reason.updated']],
            ], 200),
        ]);

        $this->actingAs($this->admin)->put(route('agenda.update', $approvedEvent), [
            'judul' => $approvedEvent->event_name,
            'tanggal' => now()->addDays(6)->toDateString(),
            'tanggal_selesai' => now()->addDays(7)->toDateString(),
            'sesi_mulai' => 2,
            'sesi_selesai' => 4,
            'ruangan_id' => $this->roomA->id,
            'current_room_id' => $this->roomB->id,
            'keterangan' => 'Dipindah kembali ke Aula via edit',
        ])->assertRedirect(route('agenda.index'));

        $this->assertSame(
            'Penyesuaian jadwal dan penggunaan ruangan oleh Admin.',
            $approvedEvent->fresh()->move_reason
        );
        Http::assertSentCount(1);
    }

    /**
     * @param array<string, mixed> $stubs
     */
    private function resetHttpFake(array $stubs = []): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::preventStrayRequests();
        Http::fake($stubs);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function configureLiveMeta(array $overrides = []): void
    {
        $normalizedOverrides = [];
        foreach ($overrides as $key => $value) {
            $configKey = str_starts_with($key, 'services.whatsapp.')
                ? $key
                : 'services.whatsapp.' . $key;
            $normalizedOverrides[$configKey] = $value;
        }

        config(array_merge([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.driver' => MetaCloudApiDriver::DRIVER_NAME,
            'services.whatsapp.dry_run' => false,
            'services.whatsapp.require_consent' => true,
            'services.whatsapp.test_allowlist_only' => true,
            'services.whatsapp.test_allowlist' => [],
            'services.whatsapp.graph_base_url' => 'https://graph.facebook.com',
            'services.whatsapp.graph_version' => 'v21.0',
            'services.whatsapp.phone_number_id' => self::FAKE_PHONE_ID,
            'services.whatsapp.access_token' => self::FAKE_TOKEN,
            'services.whatsapp.template_name' => MetaCloudApiDriver::DEFAULT_TEMPLATE_NAME,
            'services.whatsapp.template_language' => MetaCloudApiDriver::DEFAULT_TEMPLATE_LANGUAGE,
            'services.whatsapp.connect_timeout' => 5,
            'services.whatsapp.timeout' => 10,
        ], $normalizedOverrides));
    }

    private function graphMessagesEndpoint(): string
    {
        return 'https://graph.facebook.com/v21.0/' . self::FAKE_PHONE_ID . '/messages';
    }

    private function createApprovedEvent(User $requester, Room $room): Event
    {
        return Event::create([
            'event_name' => 'Kegiatan Uji Meta Cloud API',
            'date' => '2026-11-22',
            'end_date' => '2026-11-22',
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $requester->id,
            'room_id' => $room->id,
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
        ]);
    }

    private function actingAsApiUser(User $user): static
    {
        $token = 'test_' . bin2hex(random_bytes(20));
        Cache::put('api_token_' . hash('sha256', $token), $user->id, now()->addHour());

        return $this->withToken($token);
    }
}
