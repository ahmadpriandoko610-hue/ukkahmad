<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Models\User;
use App\Models\WhatsAppNotificationLog;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeacherWhatsAppOptInTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $picOld;
    private User $picNew;
    private User $guru;
    private User $siswa;
    private Room $oldRoom;
    private Room $newRoom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Sekolah',
            'email' => 'admin.optin@rapi.test',
        ]);

        $this->picOld = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Lama',
            'email' => 'pic.lama.optin@rapi.test',
            'phone' => '081111111111',
        ]);

        $this->picNew = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Baru',
            'email' => 'pic.baru.optin@rapi.test',
            'phone' => '082222222222',
        ]);

        $this->guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Opt-In',
            'email' => 'guru.optin@rapi.test',
            'phone' => '081298765432',
        ]);

        $this->siswa = User::factory()->create([
            'role' => 'siswa',
            'name' => 'Siswa Uji',
            'email' => 'siswa.optin@rapi.test',
        ]);

        $this->oldRoom = Room::create([
            'room_name' => 'Ruang Kelas 10A',
            'location' => 'Lantai 1',
            'capacity' => 36,
            'pic_id' => $this->picOld->id,
            'is_active' => true,
        ]);

        $this->newRoom = Room::create([
            'room_name' => 'Lab RPL 2',
            'location' => 'Lantai 2',
            'capacity' => 32,
            'pic_id' => $this->picNew->id,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Default persetujuan adalah false, skema kolom/tabel tersedia, dan data sensitif tersembunyi.
     */
    public function test_default_whatsapp_opt_in_is_false_and_schema_is_ready(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'whatsapp_opt_in',
            'whatsapp_opt_in_at',
            'whatsapp_opt_in_source',
        ]));

        $this->assertTrue(Schema::hasColumns('whatsapp_notification_logs', [
            'id',
            'idempotency_key',
            'event_id',
            'old_room_id',
            'new_room_id',
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
            'created_at',
            'updated_at',
        ]));

        $freshGuru = User::factory()->create([
            'role' => 'guru',
            'email' => 'guru.default@rapi.test',
            'phone' => '081299990000',
        ]);

        $this->assertFalse($freshGuru->whatsapp_opt_in);
        $this->assertNull($freshGuru->whatsapp_opt_in_at);
        $this->assertNull($freshGuru->whatsapp_opt_in_source);
        $this->assertFalse($freshGuru->hasWhatsAppConsent());

        // Nomor telepon tetap tersembunyi dari serialisasi JSON User
        $serialized = $freshGuru->toArray();
        $this->assertArrayNotHasKey('phone', $serialized);
        $this->assertArrayNotHasKey('email', $serialized);

        // Korelasi HMAC berbasis APP_KEY tidak sama dengan SHA-256 polos
        $hmac = WhatsAppNotificationLog::computePhoneHmac('6281299990000');
        $this->assertNotNull($hmac);
        $this->assertSame(64, strlen($hmac));
        $this->assertNotSame(hash('sha256', '6281299990000'), $hmac);

        $log = new WhatsAppNotificationLog([
            'idempotency_key' => hash('sha256', 'unit-check'),
            'driver' => 'meta_cloud_api',
            'recipient_phone_masked' => '6281******000',
            'recipient_phone_hmac' => $hmac,
            'is_dry_run' => true,
            'status' => 'dry_run',
        ]);
        $this->assertArrayNotHasKey('recipient_phone_hmac', $log->toArray());
    }

    /**
     * 2. Guru dapat memasukkan/memperbarui nomor, melihat format tersamar, memberikan dan mencabut persetujuan.
     */
    public function test_guru_can_grant_and_revoke_whatsapp_consent_explicitly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 11:05:00', 'Asia/Jakarta'));

        try {
            $page = $this->actingAs($this->guru)->get(route('guru.whatsapp.settings'));
            $page->assertOk();
            $page->assertSee('6281******432');
            $page->assertDontSee('6281298765432');
            $page->assertSee('Belum Menyetujui');
            $page->assertSee('hanya digunakan untuk mengirimkan notifikasi perubahan ruangan agenda sekolah');

            // Checkbox persetujuan TIDAK boleh aktif secara default dan form dilindungi token CSRF
            $html = $page->getContent();
            $this->assertMatchesRegularExpression('/<input[^>]*name="_token"[^>]*>/i', $html);
            $this->assertMatchesRegularExpression('/<input[^>]*name="whatsapp_opt_in"[^>]*>/i', $html);
            $this->assertDoesNotMatchRegularExpression('/<input[^>]*name="whatsapp_opt_in"[^>]*\bchecked\b/i', $html);

            // Aktifkan persetujuan secara eksplisit dengan nomor tersimpan saat ini
            $grantResponse = $this->actingAs($this->guru)->put(route('guru.whatsapp.update'), [
                'phone' => '',
                'whatsapp_opt_in' => '1',
            ]);

            $grantResponse->assertRedirect(route('guru.whatsapp.settings'));
            $grantResponse->assertSessionHas('success');

            $this->guru->refresh();
            $this->assertTrue($this->guru->whatsapp_opt_in);
            $this->assertNotNull($this->guru->whatsapp_opt_in_at);
            $this->assertSame('guru_self_service', $this->guru->whatsapp_opt_in_source);
            $this->assertTrue($this->guru->hasWhatsAppConsent());
            $this->assertSame('6281298765432', $this->guru->phone);

            // Cabut persetujuan melalui tombol Cabut Persetujuan
            $revokeResponse = $this->actingAs($this->guru)->delete(route('guru.whatsapp.consent.destroy'));
            $revokeResponse->assertRedirect(route('guru.whatsapp.settings'));

            $this->guru->refresh();
            $this->assertFalse($this->guru->whatsapp_opt_in);
            $this->assertNull($this->guru->whatsapp_opt_in_at);
            $this->assertNull($this->guru->whatsapp_opt_in_source);
            $this->assertFalse($this->guru->hasWhatsAppConsent());
            $this->assertSame('6281298765432', $this->guru->phone);

            // Validasi menolak opt-in jika Guru belum memiliki nomor WhatsApp sama sekali
            $guruTanpaNomor = User::factory()->create([
                'role' => 'guru',
                'email' => 'guru.tanpa.nomor@rapi.test',
                'phone' => null,
            ]);
            $noPhoneOptInResponse = $this->actingAs($guruTanpaNomor)
                ->from(route('guru.whatsapp.settings'))
                ->put(route('guru.whatsapp.update'), [
                    'phone' => '',
                    'whatsapp_opt_in' => '1',
                ]);
            $noPhoneOptInResponse->assertRedirect(route('guru.whatsapp.settings'));
            $noPhoneOptInResponse->assertSessionHasErrors('phone');
            $this->assertFalse($guruTanpaNomor->fresh()->hasWhatsAppConsent());

            // Validasi menolak nomor tidak valid
            $invalidResponse = $this->actingAs($this->guru)
                ->from(route('guru.whatsapp.settings'))
                ->put(route('guru.whatsapp.update'), [
                    'phone' => '021-bukan-wa',
                    'whatsapp_opt_in' => '1',
                ]);

            $invalidResponse->assertRedirect(route('guru.whatsapp.settings'));
            $invalidResponse->assertSessionHasErrors('phone');
            $this->assertFalse($this->guru->fresh()->hasWhatsAppConsent());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 3. Mengganti nomor WhatsApp ke nomor baru mencabut persetujuan lama sehingga wajib disetujui ulang.
     */
    public function test_new_phone_number_revokes_previous_consent_and_requires_reconsent(): void
    {
        $this->guru->grantWhatsAppConsent('guru_self_service')->save();
        $this->assertTrue($this->guru->fresh()->hasWhatsAppConsent());

        // A. Guru mengganti nomor via halaman pengaturan tanpa mencentang ulang persetujuan
        $response = $this->actingAs($this->guru)->put(route('guru.whatsapp.update'), [
            'phone' => '089876543210',
        ]);

        $response->assertRedirect(route('guru.whatsapp.settings'));
        $this->guru->refresh();
        $this->assertSame('6289876543210', $this->guru->phone);
        $this->assertFalse($this->guru->whatsapp_opt_in);
        $this->assertNull($this->guru->whatsapp_opt_in_at);
        $this->assertNull($this->guru->whatsapp_opt_in_source);
        $this->assertFalse($this->guru->hasWhatsAppConsent());

        // Guru menyetujui ulang untuk nomor baru
        $this->actingAs($this->guru)->put(route('guru.whatsapp.update'), [
            'phone' => '',
            'whatsapp_opt_in' => '1',
        ])->assertRedirect(route('guru.whatsapp.settings'));
        $this->assertTrue($this->guru->fresh()->hasWhatsAppConsent());

        // B. Update nomor langsung pada model juga mencabut persetujuan lama
        $this->guru->update(['phone' => '+6285678901234']);
        $this->guru->refresh();
        $this->assertSame('6285678901234', $this->guru->phone);
        $this->assertFalse($this->guru->whatsapp_opt_in);
        $this->assertNull($this->guru->whatsapp_opt_in_at);
        $this->assertNull($this->guru->whatsapp_opt_in_source);

        // C. Admin mengedit nama tanpa mengubah nomor tetap mempertahankan persetujuan Guru,
        //    sedangkan mengganti nomor via kelola user otomatis mencabut persetujuan lama
        $this->guru->grantWhatsAppConsent('guru_self_service')->save();
        $this->assertTrue($this->guru->fresh()->hasWhatsAppConsent());

        $this->actingAs($this->admin)->put(route('users.update', $this->guru), [
            'name' => 'Guru Opt-In Diperbarui',
            'email' => $this->guru->email,
            'role' => 'guru',
            'phone' => '6285678901234',
        ])->assertRedirect(route('users.index'));
        $this->assertTrue($this->guru->fresh()->hasWhatsAppConsent());

        $this->actingAs($this->admin)->put(route('users.update', $this->guru), [
            'name' => $this->guru->name,
            'email' => $this->guru->email,
            'role' => 'guru',
            'phone' => '081377776666',
        ])->assertRedirect(route('users.index'));

        $this->guru->refresh();
        $this->assertSame('6281377776666', $this->guru->phone);
        $this->assertFalse($this->guru->whatsapp_opt_in);
        $this->assertNull($this->guru->whatsapp_opt_in_at);
        $this->assertNull($this->guru->whatsapp_opt_in_source);

        // D. Guru mengganti nomor baru sekaligus mencentang persetujuan secara eksplisit
        Carbon::setTestNow(Carbon::parse('2026-11-11 11:20:00', 'Asia/Jakarta'));
        try {
            $this->actingAs($this->guru)->put(route('guru.whatsapp.update'), [
                'phone' => '081900001111',
                'whatsapp_opt_in' => '1',
            ])->assertRedirect(route('guru.whatsapp.settings'));

            $this->guru->refresh();
            $this->assertSame('6281900001111', $this->guru->phone);
            $this->assertTrue($this->guru->hasWhatsAppConsent());
            $this->assertSame('2026-11-11 11:20:00', $this->guru->whatsapp_opt_in_at?->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 4. Menghapus nomor WhatsApp otomatis mencabut persetujuan.
     */
    public function test_removing_phone_number_revokes_whatsapp_consent(): void
    {
        $this->guru->grantWhatsAppConsent('guru_self_service')->save();
        $this->assertTrue($this->guru->fresh()->hasWhatsAppConsent());

        $response = $this->actingAs($this->guru)->delete(route('guru.whatsapp.phone.destroy'));
        $response->assertRedirect(route('guru.whatsapp.settings'));

        $this->guru->refresh();
        $this->assertNull($this->guru->phone);
        $this->assertFalse($this->guru->whatsapp_opt_in);
        $this->assertNull($this->guru->whatsapp_opt_in_at);
        $this->assertNull($this->guru->whatsapp_opt_in_source);
        $this->assertFalse($this->guru->hasWhatsAppConsent());

        // Pengujian pada level model: mengosongkan nomor mencabut persetujuan
        $this->guru->grantWhatsAppConsent('guru_self_service', '081234567890')->save();
        $this->assertTrue($this->guru->fresh()->hasWhatsAppConsent());

        $this->guru->update(['phone' => null]);
        $this->guru->refresh();
        $this->assertNull($this->guru->phone);
        $this->assertFalse($this->guru->whatsapp_opt_in);
        $this->assertNull($this->guru->whatsapp_opt_in_at);
        $this->assertNull($this->guru->whatsapp_opt_in_source);
    }

    /**
     * 5. Perubahan role dari guru ke admin, siswa, atau pic mencabut persetujuan.
     */
    public function test_changing_role_from_guru_revokes_whatsapp_consent(): void
    {
        foreach (['admin', 'siswa', 'pic'] as $targetRole) {
            $guruUser = User::factory()->create([
                'role' => 'guru',
                'phone' => '081234567890',
                'whatsapp_opt_in' => true,
                'whatsapp_opt_in_at' => now(),
                'whatsapp_opt_in_source' => 'guru_self_service',
            ]);
            $this->assertTrue($guruUser->hasWhatsAppConsent());

            $guruUser->update(['role' => $targetRole]);
            $guruUser->refresh();

            $this->assertSame($targetRole, $guruUser->role);
            $this->assertFalse($guruUser->whatsapp_opt_in);
            $this->assertNull($guruUser->whatsapp_opt_in_at);
            $this->assertNull($guruUser->whatsapp_opt_in_source);
            $this->assertFalse($guruUser->hasWhatsAppConsent());

            if ($targetRole === 'pic') {
                $this->assertSame('6281234567890', $guruUser->phone);
            } else {
                $this->assertNull($guruUser->phone);
            }
        }
    }

    /**
     * 6. Admin, Siswa, dan PIC tidak dapat memberikan atau menyimpan persetujuan notifikasi pemindahan ruangan.
     */
    public function test_admin_siswa_and_pic_cannot_grant_or_hold_whatsapp_consent(): void
    {
        foreach ([$this->admin, $this->siswa, $this->picOld] as $nonGuru) {
            $nonGuru->grantWhatsAppConsent('bypass_attempt', '081299998888');
            $nonGuru->save();
            $nonGuru->refresh();

            $this->assertFalse($nonGuru->whatsapp_opt_in);
            $this->assertNull($nonGuru->whatsapp_opt_in_at);
            $this->assertNull($nonGuru->whatsapp_opt_in_source);
            $this->assertFalse($nonGuru->hasWhatsAppConsent());
        }

        // Membuat user Admin/Siswa/PIC langsung dengan whatsapp_opt_in=true tetap dipaksa false
        $picDirect = User::factory()->create([
            'role' => 'pic',
            'phone' => '081311112222',
            'whatsapp_opt_in' => true,
            'whatsapp_opt_in_at' => now(),
            'whatsapp_opt_in_source' => 'direct_model',
        ]);
        $this->assertSame('6281311112222', $picDirect->phone);
        $this->assertFalse($picDirect->whatsapp_opt_in);
        $this->assertNull($picDirect->whatsapp_opt_in_at);
        $this->assertNull($picDirect->whatsapp_opt_in_source);

        // Admin tidak dapat menyetujui atas nama Guru saat membuat atau memperbarui user
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Guru Bypass Uji',
            'email' => 'guru.bypass@rapi.test',
            'password' => 'secret123',
            'role' => 'guru',
            'phone' => '081255554444',
            'whatsapp_opt_in' => '1',
        ])->assertRedirect(route('users.index'));

        $createdGuru = User::where('email', 'guru.bypass@rapi.test')->firstOrFail();
        $this->assertSame('6281255554444', $createdGuru->phone);
        $this->assertFalse($createdGuru->whatsapp_opt_in);
        $this->assertNull($createdGuru->whatsapp_opt_in_at);

        $this->actingAs($this->admin)->put(route('users.update', $createdGuru), [
            'name' => $createdGuru->name,
            'email' => $createdGuru->email,
            'role' => 'guru',
            'phone' => '081255554444',
            'whatsapp_opt_in' => '1',
        ])->assertRedirect(route('users.index'));

        $this->assertFalse($createdGuru->fresh()->whatsapp_opt_in);
    }

    /**
     * 7. PIC tidak pernah menjadi penerima notifikasi pemindahan ruangan.
     */
    public function test_pic_is_not_recipient_of_room_move_whatsapp_notification(): void
    {
        $eventByGuru = Event::create([
            'event_name' => 'Rapat Koordinasi Kurikulum',
            'date' => '2026-11-15',
            'end_date' => '2026-11-15',
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->oldRoom->id,
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
        ]);

        $recipients = app(WhatsAppService::class)->resolveRecipientsForRoomMove(
            $eventByGuru,
            $this->oldRoom,
            $this->newRoom
        );

        $this->assertCount(1, $recipients);
        $this->assertSame($this->guru->id, $recipients[0]['user_id']);
        $this->assertSame('guru', $recipients[0]['role']);
        $this->assertNotContains('6281111111111', array_column($recipients, 'phone'));
        $this->assertNotContains('6282222222222', array_column($recipients, 'phone'));

        $eventByPic = Event::create([
            'event_name' => 'Agenda Internal PIC',
            'date' => '2026-11-16',
            'end_date' => '2026-11-16',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->picOld->id,
            'room_id' => $this->oldRoom->id,
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
        ]);

        $picRecipients = app(WhatsAppService::class)->resolveRecipientsForRoomMove(
            $eventByPic,
            $this->oldRoom,
            $this->newRoom
        );
        $this->assertSame([], $picRecipients);
    }

    /**
     * 8. Halaman admin/users hanya menampilkan nomor tersamar & status persetujuan tanpa opsi opt-in oleh Admin.
     */
    public function test_admin_users_displays_masked_phone_and_readonly_consent_status(): void
    {
        // Daftar user sebelum opt-in
        $indexBefore = $this->actingAs($this->admin)->get(route('users.index'));
        $indexBefore->assertOk();
        $indexBefore->assertSee('6281******432');
        $indexBefore->assertDontSee('6281298765432');
        $indexBefore->assertSee('6281******111');
        $indexBefore->assertDontSee('6281111111111');
        $indexBefore->assertSee('Belum Menyetujui');
        $indexBefore->assertSee('Khusus Pengaduan PIC');

        // Setelah Guru memberi persetujuan
        $this->guru->grantWhatsAppConsent('guru_self_service')->save();

        $indexAfter = $this->actingAs($this->admin)->get(route('users.index'));
        $indexAfter->assertOk();
        $indexAfter->assertSee('6281******432');
        $indexAfter->assertDontSee('6281298765432');
        $indexAfter->assertSee('Sudah Menyetujui');

        // Halaman edit user menampilkan panel status read-only dan tidak menyediakan checkbox opt-in Admin
        $editGuru = $this->actingAs($this->admin)->get(route('users.edit', $this->guru));
        $editGuru->assertOk();
        $editGuru->assertSee('Status Persetujuan Notifikasi WhatsApp Guru (Read-Only)');
        $editGuru->assertSee('6281******432');
        $editGuru->assertSee('Sudah Menyetujui');
        $editGuru->assertDontSee('name="whatsapp_opt_in"', false);

        $createUser = $this->actingAs($this->admin)->get(route('users.create'));
        $createUser->assertOk();
        $createUser->assertDontSee('name="whatsapp_opt_in"', false);
    }

    /**
     * 9. Proteksi akses halaman Pengaturan WhatsApp Guru: hanya role guru yang diizinkan.
     */
    public function test_guru_whatsapp_settings_routes_are_restricted_to_guru_role(): void
    {
        $this->get(route('guru.whatsapp.settings'))->assertRedirect(route('login'));
        $this->put(route('guru.whatsapp.update'), ['phone' => '081234567890'])->assertRedirect(route('login'));

        foreach ([$this->admin, $this->picOld, $this->siswa] as $unauthorized) {
            $this->actingAs($unauthorized)->get(route('guru.whatsapp.settings'))->assertForbidden();
            $this->actingAs($unauthorized)->put(route('guru.whatsapp.update'), [
                'phone' => '081234567890',
                'whatsapp_opt_in' => '1',
            ])->assertForbidden();
            $this->actingAs($unauthorized)->delete(route('guru.whatsapp.consent.destroy'))->assertForbidden();
            $this->actingAs($unauthorized)->delete(route('guru.whatsapp.phone.destroy'))->assertForbidden();
        }

        $guruPage = $this->actingAs($this->guru)->get(route('guru.whatsapp.settings'));
        $guruPage->assertOk();
        $guruPage->assertSeeInOrder([
            'href="' . route('guru.whatsapp.settings') . '"',
            'class="menu-item active"',
            'Notifikasi WhatsApp',
        ], false);
    }

    /**
     * 10. Fitur wa.me pengaduan PIC tetap berjalan normal meski Meta Cloud API belum aktif.
     */
    public function test_pic_complaint_whatsapp_wa_me_continues_working(): void
    {
        $complaint = Complaint::create([
            'room_id' => $this->oldRoom->id,
            'reporter_id' => $this->guru->id,
            'description' => 'Pendingin ruangan tidak dingin',
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('complaint.whatsapp', $complaint));

        $targetUrl = $response->headers->get('Location', '');
        $response->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/6281111111111?text=', $targetUrl);
    }

    /**
     * 11 & 12. Konfigurasi aman default, ScheduleNotification & pemindahan ruangan tetap berjalan,
     * serta tidak ada HTTP request eksternal yang dikirim ke Graph API atau gateway mana pun.
     */
    public function test_room_move_and_schedule_notification_work_with_no_external_http_requests(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 11:10:00', 'Asia/Jakarta'));

        try {
            // Verifikasi default konfigurasi aman
            $this->assertFalse(config('services.whatsapp.enabled'));
            $this->assertSame('meta_cloud_api', config('services.whatsapp.driver'));
            $this->assertTrue(config('services.whatsapp.dry_run'));
            $this->assertTrue(config('services.whatsapp.require_consent'));
            $this->assertTrue(config('services.whatsapp.test_allowlist_only'));
            $this->assertSame('https://graph.facebook.com', config('services.whatsapp.graph_base_url'));
            $this->assertSame(5, config('services.whatsapp.connect_timeout'));
            $this->assertSame(10, config('services.whatsapp.timeout'));
            $this->assertSame('notifikasi_pemindahan_ruangan', config('services.whatsapp.template_name'));
            $this->assertSame('id', config('services.whatsapp.template_language'));
            $this->assertFalse(app(WhatsAppService::class)->isConfigured());

            $this->guru->grantWhatsAppConsent('guru_self_service')->save();

            Http::preventStrayRequests();
            Http::fake();

            $event = Event::create([
                'event_name' => 'Praktik Jaringan Komputer',
                'date' => '2026-11-18',
                'end_date' => '2026-11-18',
                'start_time' => '08:15:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->oldRoom->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);

            // Pindah ruangan lewat Web
            $webMove = $this->actingAs($this->admin)->put(route('events.move', $event), [
                'room_id' => $this->newRoom->id,
                'move_reason' => 'Memerlukan switch dan kabel LAN',
            ]);
            $webMove->assertRedirect(route('agenda.index'));

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'room_id' => $this->newRoom->id,
                'move_reason' => 'Memerlukan switch dan kabel LAN',
            ]);
            $this->assertDatabaseHas('schedule_notifications', [
                'event_id' => $event->id,
                'user_id' => $this->guru->id,
                'title' => 'Ruangan agenda dipindahkan',
            ]);

            // Pindah ruangan lewat API
            $apiMove = $this->actingAsApiUser($this->admin)
                ->putJson("/api/agenda/{$event->id}/pindah-ruangan", [
                    'ruangan_id' => $this->oldRoom->id,
                    'alasan' => 'Kembali ke ruang teori',
                ]);
            $apiMove->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('whatsapp_notification.status', 'unconfigured');

            $this->assertSame(2, ScheduleNotification::count());
            Http::assertNothingSent();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function actingAsApiUser(User $user): static
    {
        $token = 'test_' . bin2hex(random_bytes(20));
        Cache::put('api_token_' . hash('sha256', $token), $user->id, now()->addHour());

        return $this->withToken($token);
    }
}
