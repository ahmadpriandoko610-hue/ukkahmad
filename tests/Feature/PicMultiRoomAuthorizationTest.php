<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PicMultiRoomAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private User $pic1;
    private User $pic2;
    private Room $pic1RoomA;
    private Room $pic1RoomB;
    private Room $pic2RoomC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Administrator',
        ]);

        $this->guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Pengaju',
        ]);

        $this->pic1 = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Satu',
        ]);

        $this->pic2 = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Dua',
        ]);

        $this->pic1RoomA = Room::create([
            'room_name' => 'LAB 1',
            'location' => 'Gedung A',
            'capacity' => 36,
            'pic_id' => $this->pic1->id,
            'is_active' => true,
        ]);

        $this->pic1RoomB = Room::create([
            'room_name' => 'LAB 2',
            'location' => 'Gedung A',
            'capacity' => 36,
            'pic_id' => $this->pic1->id,
            'is_active' => true,
        ]);

        $this->pic2RoomC = Room::create([
            'room_name' => 'Aula Utama',
            'location' => 'Gedung B',
            'capacity' => 200,
            'pic_id' => $this->pic2->id,
            'is_active' => true,
        ]);
    }

    /**
     * Skenario 1: PIC 1 dapat menyetujui agenda ruangannya sendiri.
     */
    public function test_pic_1_can_approve_agenda_for_own_room(): void
    {
        $eventOne = $this->createPendingEvent($this->pic1RoomA, 'Rapat Jurusan RPL');
        $eventTwo = $this->createPendingEvent($this->pic1RoomB, 'Workshop Desain');

        $responseOne = $this->actingAs($this->pic1)->post(route('events.approve', $eventOne));
        $responseOne->assertRedirect(route('pic.events'));
        $responseOne->assertSessionHas('success', 'Agenda berhasil disetujui.');

        $this->assertDatabaseHas('events', [
            'id' => $eventOne->id,
            'status' => 'Disetujui',
            'approved_by' => $this->pic1->id,
        ]);

        $responseTwo = $this->actingAs($this->pic1)->post(route('agenda.setujui', $eventTwo));
        $responseTwo->assertRedirect(route('pic.agenda'));
        $responseTwo->assertSessionHas('success', 'Agenda berhasil disetujui.');

        $this->assertDatabaseHas('events', [
            'id' => $eventTwo->id,
            'status' => 'Disetujui',
            'approved_by' => $this->pic1->id,
        ]);
    }

    /**
     * Skenario 2: PIC 1 tidak dapat menyetujui agenda milik PIC 2.
     */
    public function test_pic_1_cannot_approve_agenda_owned_by_pic_2(): void
    {
        $pic2Event = $this->createPendingEvent($this->pic2RoomC, 'Seminar Aula');

        $this->actingAs($this->pic1)
            ->post(route('events.approve', $pic2Event))
            ->assertForbidden();

        $this->actingAs($this->pic1)
            ->post(route('agenda.setujui', $pic2Event))
            ->assertForbidden();

        // Ruangan tanpa PIC (pic_id = null) juga tidak boleh diproses PIC manapun
        $unassignedRoom = Room::create([
            'room_name' => 'Ruang Cadangan',
            'location' => 'Gedung C',
            'capacity' => 25,
            'pic_id' => null,
            'is_active' => true,
        ]);
        $unassignedEvent = $this->createPendingEvent($unassignedRoom, 'Agenda Ruang Tanpa PIC');

        $this->actingAs($this->pic1)
            ->post(route('events.approve', $unassignedEvent))
            ->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $pic2Event->id,
            'status' => 'Menunggu',
            'approved_by' => null,
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $unassignedEvent->id,
            'status' => 'Menunggu',
            'approved_by' => null,
        ]);
    }

    /**
     * Skenario 3: PIC 1 tidak dapat menolak agenda milik PIC 2.
     */
    public function test_pic_1_cannot_reject_agenda_owned_by_pic_2(): void
    {
        $pic2Event = $this->createPendingEvent($this->pic2RoomC, 'Pentas Seni Aula');

        $this->actingAs($this->pic1)
            ->get(route('events.reject.form', $pic2Event))
            ->assertForbidden();

        $this->actingAs($this->pic1)
            ->get(route('agenda.tolak.form', $pic2Event))
            ->assertForbidden();

        $this->actingAs($this->pic1)
            ->post(route('events.reject', $pic2Event), [
                'rejection_reason' => 'Percobaan penolakan oleh PIC yang salah.',
            ])
            ->assertForbidden();

        $this->actingAs($this->pic1)
            ->post(route('agenda.tolak', $pic2Event), [
                'rejection_reason' => 'Percobaan penolakan lewat route lama.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('events', [
            'id' => $pic2Event->id,
            'status' => 'Menunggu',
            'approved_by' => null,
            'rejection_reason' => null,
            'rejected_at' => null,
        ]);
    }

    /**
     * Skenario 4: PIC 1 tidak dapat mengubah status agenda ruangan PIC 2 melalui group_id yang sama.
     */
    public function test_pic_1_cannot_alter_pic_2_room_status_via_shared_multi_room_group_id(): void
    {
        $groupApprove = (string) Str::uuid();
        $approveRoomPic1 = $this->createPendingEvent($this->pic1RoomA, 'Ujian Kolaborasi A', $groupApprove);
        $approveRoomPic2 = $this->createPendingEvent($this->pic2RoomC, 'Ujian Kolaborasi A', $groupApprove);

        // PIC 1 menyetujui ruangan miliknya lewat route approve & setujui -> tidak boleh menyentuh ruangan PIC 2
        $this->actingAs($this->pic1)
            ->post(route('events.approve', $approveRoomPic1))
            ->assertRedirect(route('pic.events'))
            ->assertSessionHas('success', 'Agenda berhasil disetujui.');

        $this->assertDatabaseHas('events', [
            'id' => $approveRoomPic1->id,
            'status' => 'Disetujui',
            'approved_by' => $this->pic1->id,
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $approveRoomPic2->id,
            'status' => 'Menunggu',
            'approved_by' => null,
        ]);

        // PIC 1 mencoba menyetujui langsung event PIC 2 di grup yang sama -> 403
        $this->actingAs($this->pic1)
            ->post(route('events.approve', $approveRoomPic2))
            ->assertForbidden();
        $this->actingAs($this->pic1)
            ->post(route('agenda.setujui', $approveRoomPic2))
            ->assertForbidden();

        // Uji juga skenario penolakan lintas PIC pada group_id baru
        $groupReject = (string) Str::uuid();
        $rejectRoomPic1 = $this->createPendingEvent($this->pic1RoomA, 'Try Out Lintas Gedung', $groupReject, Carbon::now()->addDays(3)->toDateString());
        $rejectRoomPic2 = $this->createPendingEvent($this->pic2RoomC, 'Try Out Lintas Gedung', $groupReject, Carbon::now()->addDays(3)->toDateString());

        $this->actingAs($this->pic1)
            ->post(route('agenda.tolak', $rejectRoomPic1), [
                'rejection_reason' => 'LAB 1 sedang dalam pemeliharaan listrik.',
            ])
            ->assertRedirect(route('pic.agenda'))
            ->assertSessionHas('success', 'Agenda berhasil ditolak.');

        $this->assertDatabaseHas('events', [
            'id' => $rejectRoomPic1->id,
            'status' => 'Ditolak',
            'approved_by' => $this->pic1->id,
            'rejection_reason' => 'LAB 1 sedang dalam pemeliharaan listrik.',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $rejectRoomPic2->id,
            'status' => 'Menunggu',
            'approved_by' => null,
            'rejection_reason' => null,
            'rejected_at' => null,
        ]);

        // PIC 2 kemudian tetap dapat menyetujui bagian ruangannya secara independen
        $this->actingAs($this->pic2)
            ->post(route('agenda.setujui', $rejectRoomPic2))
            ->assertRedirect(route('pic.agenda'))
            ->assertSessionHas('success', 'Agenda berhasil disetujui.');

        $this->assertDatabaseHas('events', [
            'id' => $rejectRoomPic2->id,
            'status' => 'Disetujui',
            'approved_by' => $this->pic2->id,
        ]);
        // Bagian PIC 1 tetap Ditolak sesuai keputusan PIC 1
        $this->assertDatabaseHas('events', [
            'id' => $rejectRoomPic1->id,
            'status' => 'Ditolak',
            'approved_by' => $this->pic1->id,
        ]);

        // Uji end-to-end pengajuan Guru via guru.events.store untuk 2 ruangan lintas PIC
        $storeDate = Carbon::now()->addDays(7)->toDateString();
        $this->actingAs($this->guru)->post(route('guru.events.store'), [
            'judul' => 'Simulasi Gabungan Lintas PIC',
            'tanggal' => $storeDate,
            'tanggal_selesai' => $storeDate,
            'sesi_mulai' => 1,
            'sesi_selesai' => 2,
            'ruangan_id' => [$this->pic1RoomA->id, $this->pic2RoomC->id],
        ])->assertRedirect(route('guru.agenda'));

        $storedPic1 = Event::where('event_name', 'Simulasi Gabungan Lintas PIC')
            ->where('room_id', $this->pic1RoomA->id)
            ->firstOrFail();
        $storedPic2 = Event::where('event_name', 'Simulasi Gabungan Lintas PIC')
            ->where('room_id', $this->pic2RoomC->id)
            ->firstOrFail();

        $this->assertNotEmpty($storedPic1->group_id);
        $this->assertSame($storedPic1->group_id, $storedPic2->group_id);

        $this->actingAs($this->pic1)
            ->post(route('events.approve', $storedPic1))
            ->assertRedirect(route('pic.events'));

        $this->assertSame('Disetujui', $storedPic1->fresh()->status);
        $this->assertSame('Menunggu', $storedPic2->fresh()->status);
    }

    /**
     * Skenario 5: Admin tetap dapat menyetujui semua agenda multi-ruangan lintas PIC dalam satu grup.
     */
    public function test_admin_can_still_approve_all_multi_room_agendas_in_one_group(): void
    {
        $groupId = (string) Str::uuid();
        $eventPic1 = $this->createPendingEvent($this->pic1RoomA, 'Akreditasi Sekolah', $groupId);
        $eventPic1Second = $this->createPendingEvent($this->pic1RoomB, 'Akreditasi Sekolah', $groupId);
        $eventPic2 = $this->createPendingEvent($this->pic2RoomC, 'Akreditasi Sekolah', $groupId);

        $response = $this->actingAs($this->admin)->post(route('admin.agenda.approve', $eventPic1));
        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionHas('success', 'Agenda berhasil disetujui.');

        foreach ([$eventPic1, $eventPic1Second, $eventPic2] as $item) {
            $this->assertDatabaseHas('events', [
                'id' => $item->id,
                'status' => 'Disetujui',
                'approved_by' => $this->admin->id,
            ]);
        }
    }

    /**
     * Skenario 6: Admin tetap dapat menolak semua agenda multi-ruangan lintas PIC dalam satu grup.
     */
    public function test_admin_can_still_reject_all_multi_room_agendas_in_one_group(): void
    {
        $groupId = (string) Str::uuid();
        $eventPic1 = $this->createPendingEvent($this->pic1RoomA, 'Lomba Kompetensi', $groupId);
        $eventPic2 = $this->createPendingEvent($this->pic2RoomC, 'Lomba Kompetensi', $groupId);

        $response = $this->actingAs($this->admin)->post(route('admin.agenda.reject', $eventPic1), [
            'rejection_reason' => 'Bentrok dengan kegiatan dinas kota.',
        ]);

        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionHas('success', 'Agenda berhasil ditolak.');

        foreach ([$eventPic1, $eventPic2] as $item) {
            $this->assertDatabaseHas('events', [
                'id' => $item->id,
                'status' => 'Ditolak',
                'approved_by' => $this->admin->id,
                'rejection_reason' => 'Bentrok dengan kegiatan dinas kota.',
            ]);
            $this->assertNotNull($item->fresh()->rejected_at);
        }
    }

    /**
     * Skenario 7: Agenda yang sudah Disetujui tidak dapat diproses ulang oleh PIC maupun Admin.
     */
    public function test_already_approved_agenda_cannot_be_reprocessed(): void
    {
        $approvedEvent = Event::create([
            'event_name' => 'Agenda Sudah Disetujui',
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'end_date' => Carbon::now()->addDays(2)->toDateString(),
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->pic1RoomA->id,
            'group_id' => (string) Str::uuid(),
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
        ]);

        // PIC tidak bisa menyetujui ulang atau menolak lewat semua endpoint PIC
        $this->actingAs($this->pic1)
            ->post(route('events.approve', $approvedEvent))
            ->assertRedirect(route('pic.events'))
            ->assertSessionHas('error');

        $this->actingAs($this->pic1)
            ->post(route('agenda.setujui', $approvedEvent))
            ->assertRedirect(route('pic.agenda'))
            ->assertSessionHas('error');

        $this->actingAs($this->pic1)
            ->get(route('events.reject.form', $approvedEvent))
            ->assertRedirect(route('pic.events'))
            ->assertSessionHas('error');

        $this->actingAs($this->pic1)
            ->get(route('agenda.tolak.form', $approvedEvent))
            ->assertRedirect(route('pic.agenda'))
            ->assertSessionHas('error');

        $this->actingAs($this->pic1)
            ->post(route('events.reject', $approvedEvent), ['rejection_reason' => 'Coba tolak ulang'])
            ->assertRedirect(route('pic.events'))
            ->assertSessionHas('error');

        $this->actingAs($this->pic1)
            ->post(route('agenda.tolak', $approvedEvent), ['rejection_reason' => 'Coba tolak ulang'])
            ->assertRedirect(route('pic.agenda'))
            ->assertSessionHas('error');

        // Admin juga tidak bisa memproses ulang agenda yang sudah Disetujui
        $this->actingAs($this->admin)
            ->post(route('admin.agenda.reject', $approvedEvent), ['rejection_reason' => 'Admin coba tolak ulang'])
            ->assertRedirect(route('agenda.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', [
            'id' => $approvedEvent->id,
            'status' => 'Disetujui',
            'approved_by' => $this->admin->id,
            'rejection_reason' => null,
            'rejected_at' => null,
        ]);
    }

    /**
     * Skenario 8: Agenda yang sudah Ditolak tidak dapat diproses ulang oleh PIC maupun Admin.
     */
    public function test_already_rejected_agenda_cannot_be_reprocessed(): void
    {
        $rejectedAt = Carbon::parse('2026-11-09 09:00:00');
        $rejectedEvent = Event::create([
            'event_name' => 'Agenda Sudah Ditolak',
            'date' => Carbon::now()->addDays(2)->toDateString(),
            'end_date' => Carbon::now()->addDays(2)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '11:15:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->pic1RoomA->id,
            'group_id' => (string) Str::uuid(),
            'status' => 'Ditolak',
            'approved_by' => $this->pic1->id,
            'rejection_reason' => 'Alasan awal penolakan.',
            'rejected_at' => $rejectedAt,
        ]);

        // PIC tidak bisa menyetujui ataupun menolak ulang lewat semua endpoint PIC
        $this->actingAs($this->pic1)
            ->post(route('events.approve', $rejectedEvent))
            ->assertRedirect(route('pic.events'))
            ->assertSessionHas('error');

        $this->actingAs($this->pic1)
            ->post(route('agenda.setujui', $rejectedEvent))
            ->assertRedirect(route('pic.agenda'))
            ->assertSessionHas('error');

        $this->actingAs($this->pic1)
            ->post(route('events.reject', $rejectedEvent), ['rejection_reason' => 'Ubah alasan penolakan'])
            ->assertRedirect(route('pic.events'))
            ->assertSessionHas('error');

        $this->actingAs($this->pic1)
            ->post(route('agenda.tolak', $rejectedEvent), ['rejection_reason' => 'Ubah alasan penolakan'])
            ->assertRedirect(route('pic.agenda'))
            ->assertSessionHas('error');

        // Admin juga tidak bisa approve ulang agenda yang sudah Ditolak
        $this->actingAs($this->admin)
            ->post(route('admin.agenda.approve', $rejectedEvent))
            ->assertRedirect(route('agenda.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('events', [
            'id' => $rejectedEvent->id,
            'status' => 'Ditolak',
            'approved_by' => $this->pic1->id,
            'rejection_reason' => 'Alasan awal penolakan.',
            'rejected_at' => $rejectedAt->toDateTimeString(),
        ]);
    }

    /**
     * Skenario 9: API memberikan proteksi otorisasi, status Menunggu, dan multi-ruangan yang sama dengan website.
     */
    public function test_api_enforces_same_pic_multi_room_and_status_protections_as_website(): void
    {
        $groupId = (string) Str::uuid();
        $eventPic1 = $this->createPendingEvent($this->pic1RoomA, 'Simulasi API Multi PIC', $groupId);
        $eventPic2 = $this->createPendingEvent($this->pic2RoomC, 'Simulasi API Multi PIC', $groupId);

        // PIC 1 dilarang approve atau reject milik PIC 2 via API
        $this->actingAsApiUser($this->pic1)
            ->postJson("/api/agenda/{$eventPic2->id}/approve")
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Agenda ini berada di luar ruangan tanggung jawab Anda.',
            ]);

        $this->actingAsApiUser($this->pic1)
            ->postJson("/api/agenda/{$eventPic2->id}/reject", [
                'alasan' => 'Bukan ruangan PIC 1',
            ])
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Agenda ini berada di luar ruangan tanggung jawab Anda.',
            ]);

        // PIC 1 menyetujui event ruangannya via API -> hanya event PIC 1 yang berubah, PIC 2 tetap Menunggu
        $approveResponse = $this->actingAsApiUser($this->pic1)
            ->postJson("/api/agenda/{$eventPic1->id}/approve");

        $approveResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Agenda berhasil disetujui.')
            ->assertJsonPath('data.id', $eventPic1->id)
            ->assertJsonPath('data.status', 'Disetujui')
            ->assertJsonPath('data.approved_by', $this->pic1->id);

        $this->assertDatabaseHas('events', [
            'id' => $eventPic1->id,
            'status' => 'Disetujui',
            'approved_by' => $this->pic1->id,
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $eventPic2->id,
            'status' => 'Menunggu',
            'approved_by' => null,
        ]);

        // Agenda yang sudah Disetujui tidak boleh diproses ulang via API (422)
        $this->actingAsApiUser($this->pic1)
            ->postJson("/api/agenda/{$eventPic1->id}/reject", ['alasan' => 'Coba tolak setelah disetujui'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // PIC 2 menolak ruangannya pada grup yang sama via API -> hanya event PIC 2 yang Ditolak
        $rejectResponse = $this->actingAsApiUser($this->pic2)
            ->postJson("/api/agenda/{$eventPic2->id}/reject", [
                'alasan' => 'Aula digunakan untuk gladi bersih.',
            ]);

        $rejectResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Agenda berhasil ditolak.')
            ->assertJsonPath('data.id', $eventPic2->id)
            ->assertJsonPath('data.status', 'Ditolak')
            ->assertJsonPath('data.approved_by', $this->pic2->id)
            ->assertJsonPath('data.rejection_reason', 'Aula digunakan untuk gladi bersih.');

        // Agenda yang sudah Ditolak tidak boleh diproses ulang via API (422)
        $this->actingAsApiUser($this->pic2)
            ->postJson("/api/agenda/{$eventPic2->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        // Pastikan event PIC 1 tetap Disetujui dan event PIC 2 tetap Ditolak
        $this->assertDatabaseHas('events', [
            'id' => $eventPic1->id,
            'status' => 'Disetujui',
            'approved_by' => $this->pic1->id,
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $eventPic2->id,
            'status' => 'Ditolak',
            'approved_by' => $this->pic2->id,
            'rejection_reason' => 'Aula digunakan untuk gladi bersih.',
        ]);
    }

    /**
     * Skenario 10: Pengajuan multi-ruangan dengan PIC yang sama tetap memproses seluruh ruangan PIC tersebut (web & API).
     */
    public function test_multi_room_with_same_pic_approves_or_rejects_all_rooms_owned_by_that_pic_only(): void
    {
        // Uji Web: 2 ruangan PIC 1 + 1 ruangan PIC 2 dalam grup yang sama
        $webGroupId = (string) Str::uuid();
        $webPic1A = $this->createPendingEvent($this->pic1RoomA, 'Ujian Praktek RPL', $webGroupId);
        $webPic1B = $this->createPendingEvent($this->pic1RoomB, 'Ujian Praktek RPL', $webGroupId);
        $webPic2C = $this->createPendingEvent($this->pic2RoomC, 'Ujian Praktek RPL', $webGroupId);

        $this->actingAs($this->pic1)
            ->post(route('agenda.setujui', $webPic1A))
            ->assertRedirect(route('pic.agenda'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('events', ['id' => $webPic1A->id, 'status' => 'Disetujui', 'approved_by' => $this->pic1->id]);
        $this->assertDatabaseHas('events', ['id' => $webPic1B->id, 'status' => 'Disetujui', 'approved_by' => $this->pic1->id]);
        $this->assertDatabaseHas('events', ['id' => $webPic2C->id, 'status' => 'Menunggu', 'approved_by' => null]);

        // Uji Web Reject untuk 2 ruangan milik PIC 1 saja
        $rejectGroupId = (string) Str::uuid();
        $rejectDate = Carbon::now()->addDays(4)->toDateString();
        $rejPic1A = $this->createPendingEvent($this->pic1RoomA, 'Pelatihan Multimedia', $rejectGroupId, $rejectDate);
        $rejPic1B = $this->createPendingEvent($this->pic1RoomB, 'Pelatihan Multimedia', $rejectGroupId, $rejectDate);
        $rejPic2C = $this->createPendingEvent($this->pic2RoomC, 'Pelatihan Multimedia', $rejectGroupId, $rejectDate);

        $this->actingAs($this->pic1)
            ->post(route('events.reject', $rejPic1A), [
                'rejection_reason' => 'Kedua lab sedang digunakan ujian sertifikasi.',
            ])
            ->assertRedirect(route('pic.events'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('events', [
            'id' => $rejPic1A->id,
            'status' => 'Ditolak',
            'approved_by' => $this->pic1->id,
            'rejection_reason' => 'Kedua lab sedang digunakan ujian sertifikasi.',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $rejPic1B->id,
            'status' => 'Ditolak',
            'approved_by' => $this->pic1->id,
            'rejection_reason' => 'Kedua lab sedang digunakan ujian sertifikasi.',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $rejPic2C->id,
            'status' => 'Menunggu',
            'approved_by' => null,
            'rejection_reason' => null,
        ]);

        // Uji API: 2 ruangan PIC 1 + 1 ruangan PIC 2 dalam grup yang sama
        $apiGroupId = (string) Str::uuid();
        $apiDate = Carbon::now()->addDays(5)->toDateString();
        $apiPic1A = $this->createPendingEvent($this->pic1RoomA, 'Workshop Cloud', $apiGroupId, $apiDate);
        $apiPic1B = $this->createPendingEvent($this->pic1RoomB, 'Workshop Cloud', $apiGroupId, $apiDate);
        $apiPic2C = $this->createPendingEvent($this->pic2RoomC, 'Workshop Cloud', $apiGroupId, $apiDate);

        $this->actingAsApiUser($this->pic1)
            ->postJson("/api/agenda/{$apiPic1A->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'Disetujui');

        $this->assertDatabaseHas('events', ['id' => $apiPic1A->id, 'status' => 'Disetujui', 'approved_by' => $this->pic1->id]);
        $this->assertDatabaseHas('events', ['id' => $apiPic1B->id, 'status' => 'Disetujui', 'approved_by' => $this->pic1->id]);
        $this->assertDatabaseHas('events', ['id' => $apiPic2C->id, 'status' => 'Menunggu', 'approved_by' => null]);

        // Uji API Reject: 2 ruangan PIC 1 + 1 ruangan PIC 2 dalam grup yang sama
        $apiRejectGroupId = (string) Str::uuid();
        $apiRejectDate = Carbon::now()->addDays(6)->toDateString();
        $apiRejPic1A = $this->createPendingEvent($this->pic1RoomA, 'Bootcamp Jaringan', $apiRejectGroupId, $apiRejectDate);
        $apiRejPic1B = $this->createPendingEvent($this->pic1RoomB, 'Bootcamp Jaringan', $apiRejectGroupId, $apiRejectDate);
        $apiRejPic2C = $this->createPendingEvent($this->pic2RoomC, 'Bootcamp Jaringan', $apiRejectGroupId, $apiRejectDate);

        $this->actingAsApiUser($this->pic1)
            ->postJson("/api/agenda/{$apiRejPic1A->id}/reject", [
                'alasan' => 'Perangkat jaringan sedang diperbarui.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'Ditolak');

        $this->assertDatabaseHas('events', [
            'id' => $apiRejPic1A->id,
            'status' => 'Ditolak',
            'approved_by' => $this->pic1->id,
            'rejection_reason' => 'Perangkat jaringan sedang diperbarui.',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $apiRejPic1B->id,
            'status' => 'Ditolak',
            'approved_by' => $this->pic1->id,
            'rejection_reason' => 'Perangkat jaringan sedang diperbarui.',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $apiRejPic2C->id,
            'status' => 'Menunggu',
            'approved_by' => null,
            'rejection_reason' => null,
        ]);
    }

    private function createPendingEvent(
        Room $room,
        string $name,
        ?string $groupId = null,
        ?string $date = null
    ): Event {
        $targetDate = $date ?? Carbon::now()->addDays(2)->toDateString();

        return Event::create([
            'event_name' => $name,
            'date' => $targetDate,
            'end_date' => $targetDate,
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->guru->id,
            'room_id' => $room->id,
            'group_id' => $groupId,
            'status' => 'Menunggu',
        ]);
    }

    private function actingAsApiUser(User $user): static
    {
        $token = 'test_' . bin2hex(random_bytes(20));
        Cache::put('api_token_' . hash('sha256', $token), $user->id, now()->addHour());

        return $this->withToken($token);
    }
}
