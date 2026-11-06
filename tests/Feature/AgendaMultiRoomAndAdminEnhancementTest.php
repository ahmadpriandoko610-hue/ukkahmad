<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaMultiRoomAndAdminEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private User $pic;
    private Room $room1;
    private Room $room2;
    private Room $room3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Utama',
        ]);

        $this->guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Pengajar',
        ]);

        $this->pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Sarpras',
        ]);

        $this->room1 = Room::create([
            'room_name' => 'Lab Komputer 1',
            'location' => 'Lantai 2 Gedung A',
            'capacity' => 40,
            'pic_id' => $this->pic->id,
            'is_active' => true,
        ]);

        $this->room2 = Room::create([
            'room_name' => 'Lab Komputer 2',
            'location' => 'Lantai 2 Gedung A',
            'capacity' => 40,
            'pic_id' => $this->pic->id,
            'is_active' => true,
        ]);

        $this->room3 = Room::create([
            'room_name' => 'Aula Utama',
            'location' => 'Lantai 1 Gedung B',
            'capacity' => 200,
            'pic_id' => $this->pic->id,
            'is_active' => true,
        ]);
    }

    /**
     * Requirement 1: Guru dapat mengajukan agenda lebih dari 1 ruangan sekaligus.
     */
    public function test_guru_can_submit_agenda_for_multiple_rooms_atomically(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->guru)->post(route('guru.events.store'), [
            'judul' => 'Ujian Sertifikasi Nasional',
            'tanggal' => $tomorrow,
            'sesi_mulai' => 2,
            'sesi_selesai' => 4,
            'ruangan_id' => [$this->room1->id, $this->room2->id],
            'keterangan' => 'Membutuhkan 2 lab sekaligus untuk kapasitas peserta.',
        ]);

        $response->assertRedirect(route('guru.agenda'));
        $response->assertSessionHas('success', 'Agenda berhasil diajukan untuk 2 ruangan.');

        $this->assertDatabaseHas('events', [
            'event_name' => 'Ujian Sertifikasi Nasional',
            'room_id' => $this->room1->id,
            'requester_id' => $this->guru->id,
            'status' => 'Menunggu',
        ]);

        $this->assertDatabaseHas('events', [
            'event_name' => 'Ujian Sertifikasi Nasional',
            'room_id' => $this->room2->id,
            'requester_id' => $this->guru->id,
            'status' => 'Menunggu',
        ]);

        $this->assertEquals(2, Event::where('event_name', 'Ujian Sertifikasi Nasional')->count());
    }

    /**
     * Requirement 1: Jika salah satu ruangan bentrok, seluruh pengajuan multi-ruang dibatalkan.
     */
    public function test_multi_room_submission_fails_if_any_room_has_conflict(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        // Buat event yang sudah ada pada room2
        Event::create([
            'event_name' => 'Agenda Lain',
            'date' => $tomorrow,
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->admin->id,
            'room_id' => $this->room2->id,
            'status' => 'Disetujui',
        ]);

        $response = $this->actingAs($this->guru)->from(route('guru.events.create'))->post(route('guru.events.store'), [
            'judul' => 'Ujian Gabungan',
            'tanggal' => $tomorrow,
            'sesi_mulai' => 2,
            'sesi_selesai' => 4,
            'ruangan_id' => [$this->room1->id, $this->room2->id],
        ]);

        $response->assertRedirect(route('guru.events.create'));
        $response->assertSessionHasErrors('ruangan_id');

        // Pastikan room1 juga TIDAK tersimpan (rollback atomic)
        $this->assertDatabaseMissing('events', [
            'event_name' => 'Ujian Gabungan',
        ]);
    }

    /**
     * Requirement 2: Searchbar pada kalender publik dan PIC dihilangkan.
     */
    public function test_calendar_views_do_not_contain_search_bars(): void
    {
        // Kalender publik
        $publicResponse = $this->get(route('calendar.public'));
        $publicResponse->assertOk();
        $publicResponse->assertDontSee('id="searchAgenda"', false);
        $publicResponse->assertDontSee('class="calendar-search"', false);

        // Kalender PIC
        $picResponse = $this->actingAs($this->pic)->get(route('pic.calendar'));
        $picResponse->assertOk();
        $picResponse->assertDontSee('id="calendarSearch"', false);
        $picResponse->assertDontSee('class="calendar-search"', false);
    }

    /**
     * Requirement 3: Admin menolak agenda dengan label "Tolak" dan mengisi keterangan penolakan.
     */
    public function test_admin_can_reject_agenda_with_rejection_reason(): void
    {
        $event = Event::create([
            'event_name' => 'Rapat Ekstrakurikuler',
            'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room1->id,
            'status' => 'Menunggu',
        ]);

        // Verifikasi tampilan tabel admin memuat tombol "Tolak" dan modal keterangan penolakan
        $indexResponse = $this->actingAs($this->admin)->get(route('agenda.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Tolak');
        $indexResponse->assertSee('Keterangan Penolakan');

        // Admin submit penolakan dengan keterangan
        $rejectResponse = $this->actingAs($this->admin)->post(route('admin.agenda.reject', $event), [
            'rejection_reason' => 'Ruangan akan digunakan untuk pemeliharaan jaringan internet dinas.',
        ]);

        $rejectResponse->assertRedirect(route('agenda.index'));
        $rejectResponse->assertSessionHas('success', 'Agenda berhasil ditolak.');

        $event->refresh();
        $this->assertEquals('Ditolak', $event->status);
        $this->assertEquals($this->admin->id, $event->approved_by);
        $this->assertEquals('Ruangan akan digunakan untuk pemeliharaan jaringan internet dinas.', $event->rejection_reason);
        $this->assertNotNull($event->rejected_at);

        // Verifikasi pada tampilan admin keterangan penolakan ditampilkan
        $indexAfterResponse = $this->actingAs($this->admin)->get(route('agenda.index'));
        $indexAfterResponse->assertOk();
        $indexAfterResponse->assertSee('Ruangan akan digunakan untuk pemeliharaan jaringan internet dinas.');
    }

    /**
     * Requirement 3: Penolakan tanpa alasan ditolak oleh validasi.
     */
    public function test_admin_rejection_requires_rejection_reason(): void
    {
        $event = Event::create([
            'event_name' => 'Seminar Siswa',
            'date' => Carbon::tomorrow()->toDateString(),
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room1->id,
            'status' => 'Menunggu',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.agenda.reject', $event), [
            'rejection_reason' => '',
        ]);

        $response->assertSessionHasErrors('rejection_reason');
        $this->assertEquals('Menunggu', $event->fresh()->status);
    }

    /**
     * Requirement 4: Admin dapat mengajukan agenda pada hari ini (H-0) langsung tanpa harus H-1.
     */
    public function test_admin_can_schedule_agenda_for_today(): void
    {
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($this->admin)->post(route('events.store'), [
            'judul' => 'Rapat Darurat Kepala Sekolah',
            'tanggal' => $today,
            'sesi_mulai' => 3,
            'sesi_selesai' => 5,
            'ruangan_id' => $this->room3->id,
            'keterangan' => 'Rapat mendadak hari ini.',
        ]);

        $response->assertRedirect(route('agenda.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('events', [
            'event_name' => 'Rapat Darurat Kepala Sekolah',
            'date' => $today . ' 00:00:00',
            'room_id' => $this->room3->id,
            'requester_id' => $this->admin->id,
            'status' => 'Disetujui',
        ]);
    }

    /**
     * Requirement 4: Guru tetap dibatasi minimal H-1 dan tidak dapat mengajukan hari ini.
     */
    public function test_guru_cannot_schedule_agenda_for_today(): void
    {
        $today = Carbon::today()->toDateString();

        $response = $this->actingAs($this->guru)->post(route('guru.events.store'), [
            'judul' => 'Latihan Band Hari Ini',
            'tanggal' => $today,
            'sesi_mulai' => 3,
            'sesi_selesai' => 5,
            'ruangan_id' => [$this->room3->id],
        ]);

        $response->assertSessionHasErrors('tanggal');
        $this->assertDatabaseMissing('events', [
            'event_name' => 'Latihan Band Hari Ini',
        ]);
    }

    /**
     * Requirement: Kalender mempersingkat agenda banyak (+1 lainnya) dan menghilangkan tulisan guru/admin pada kartu.
     */
    public function test_calendar_compact_multiple_events_and_removes_role_text_tags(): void
    {
        // 1. Verifikasi views kalender memuat struktur modal #dayEventsModal dan class .calendar-more-btn
        $teacherCalResponse = $this->actingAs($this->guru)->get(route('guru.calendar'));
        $teacherCalResponse->assertOk();
        $teacherCalResponse->assertSee('id="dayEventsModal"', false);
        $teacherCalResponse->assertSee('calendar-more-btn', false);
        // Pastikan tidak ada tag teks role di kartu kalender
        $teacherCalResponse->assertDontSee('roleBadge', false);

        $picCalResponse = $this->actingAs($this->pic)->get(route('pic.calendar'));
        $picCalResponse->assertOk();
        $picCalResponse->assertSee('id="dayEventsModal"', false);
        $picCalResponse->assertSee('calendar-more-btn', false);

        $publicCalResponse = $this->get(route('calendar.public'));
        $publicCalResponse->assertOk();
        $publicCalResponse->assertSee('id="dayEventsModal"', false);
        $publicCalResponse->assertSee('calendar-more-btn', false);
    }
}

