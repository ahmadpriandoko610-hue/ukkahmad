<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MultiDayBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $pic;
    private User $admin;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guru = User::factory()->create(['role' => 'guru']);
        $this->pic = User::factory()->create(['role' => 'pic']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->room = Room::create([
            'room_name' => 'Aula Utama',
            'location' => 'Gedung A Lantai 1',
            'capacity' => 150,
            'pic_id' => $this->pic->id,
            'is_active' => true,
        ]);
    }

    public function test_guru_can_create_multiday_booking(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        try {
            $response = $this->actingAs($this->guru)
                ->post(route('guru.events.store'), [
                    'judul' => 'Kemah Kepramukaan',
                    'tanggal' => '2026-11-10',
                    'tanggal_selesai' => '2026-11-12',
                    'sesi_mulai' => 1,
                    'sesi_selesai' => 4,
                    'ruangan_id' => $this->room->id,
                    'keterangan' => 'Kegiatan kepramukaan sekolah 3 hari berturut-turut.',
                ]);

            $response->assertSessionHas('success');

            $this->assertDatabaseHas('events', [
                'event_name' => 'Kemah Kepramukaan',
                'room_id' => $this->room->id,
                'date' => '2026-11-10 00:00:00',
                'end_date' => '2026-11-12 00:00:00',
                'start_time' => '07:30:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guru->id,
            ]);

            $event = Event::where('event_name', 'Kemah Kepramukaan')->first();
            $this->assertEquals('10-11-2026 s/d 12-11-2026', $event->formatted_date_range);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_multiday_booking_defaults_end_date_to_start_date_when_empty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        try {
            $response = $this->actingAs($this->guru)
                ->post(route('guru.events.store'), [
                    'judul' => 'Workshop 1 Hari',
                    'tanggal' => '2026-11-15',
                    'tanggal_selesai' => null,
                    'sesi_mulai' => 2,
                    'sesi_selesai' => 3,
                    'ruangan_id' => $this->room->id,
                ]);

            $response->assertSessionHas('success');

            $this->assertDatabaseHas('events', [
                'event_name' => 'Workshop 1 Hari',
                'date' => '2026-11-15 00:00:00',
                'end_date' => '2026-11-15 00:00:00',
            ]);

            $event = Event::where('event_name', 'Workshop 1 Hari')->first();
            $this->assertEquals('15-11-2026', $event->formatted_date_range);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_multiday_booking_rejects_end_date_before_start_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        try {
            $response = $this->actingAs($this->guru)
                ->post(route('guru.events.store'), [
                    'judul' => 'Agenda Invalid Tanggal',
                    'tanggal' => '2026-11-15',
                    'tanggal_selesai' => '2026-11-14',
                    'sesi_mulai' => 1,
                    'sesi_selesai' => 2,
                    'ruangan_id' => $this->room->id,
                ]);

            $response->assertSessionHasErrors(['tanggal_selesai']);

            $this->assertDatabaseMissing('events', [
                'event_name' => 'Agenda Invalid Tanggal',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_conflict_detected_when_single_day_booking_overlaps_with_multiday_booking(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        try {
            // Multi-day booking from 10 to 14 Nov, sesi 1 to 4 (07:30 - 10:30)
            Event::create([
                'event_name' => 'Ujian Akhir Semester',
                'date' => '2026-11-10',
                'end_date' => '2026-11-14',
                'start_time' => '07:30:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->room->id,
                'status' => 'Disetujui',
            ]);

            // Attempt single-day booking inside range on 12 Nov, sesi 2 to 3 (08:15 - 09:45)
            $response = $this->actingAs($this->guru)
                ->post(route('guru.events.store'), [
                    'judul' => 'Seminar Kilat',
                    'tanggal' => '2026-11-12',
                    'tanggal_selesai' => '2026-11-12',
                    'sesi_mulai' => 2,
                    'sesi_selesai' => 3,
                    'ruangan_id' => $this->room->id,
                ]);

            $response->assertSessionHasErrors(['ruangan_id']);
            $this->assertDatabaseMissing('events', ['event_name' => 'Seminar Kilat']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_conflict_detected_when_multiday_booking_overlaps_with_existing_event(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        try {
            // Existing event on 12 Nov, sesi 3 to 5 (09:00 - 11:15)
            Event::create([
                'event_name' => 'Rapat Pleno',
                'date' => '2026-11-12',
                'end_date' => '2026-11-12',
                'start_time' => '09:00:00',
                'end_time' => '11:15:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->room->id,
                'status' => 'Disetujui',
            ]);

            // Attempt multi-day booking spanning 11 to 15 Nov, sesi 2 to 4 (08:15 - 10:30)
            $response = $this->actingAs($this->guru)
                ->post(route('guru.events.store'), [
                    'judul' => 'Pameran Karya',
                    'tanggal' => '2026-11-11',
                    'tanggal_selesai' => '2026-11-15',
                    'sesi_mulai' => 2,
                    'sesi_selesai' => 4,
                    'ruangan_id' => $this->room->id,
                ]);

            $response->assertSessionHasErrors(['ruangan_id']);
            $this->assertDatabaseMissing('events', ['event_name' => 'Pameran Karya']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_non_conflicting_booking_allowed_different_time_during_multiday_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        try {
            // Existing multi-day booking: 10 to 12 Nov, sesi 1 to 3 (07:30 - 09:45)
            Event::create([
                'event_name' => 'Sesi Pagi',
                'date' => '2026-11-10',
                'end_date' => '2026-11-12',
                'start_time' => '07:30:00',
                'end_time' => '09:45:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->room->id,
                'status' => 'Disetujui',
            ]);

            // Attempt booking in later session: 11 Nov, sesi 6 to 8 (11:15 - 13:30)
            $response = $this->actingAs($this->guru)
                ->post(route('guru.events.store'), [
                    'judul' => 'Sesi Siang',
                    'tanggal' => '2026-11-11',
                    'tanggal_selesai' => '2026-11-11',
                    'sesi_mulai' => 6,
                    'sesi_selesai' => 8,
                    'ruangan_id' => $this->room->id,
                ]);

            $response->assertSessionHas('success');
            $this->assertDatabaseHas('events', ['event_name' => 'Sesi Siang']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_can_update_event_with_multiday_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        try {
            $event = Event::create([
                'event_name' => 'Pelatihan Awal',
                'date' => '2026-11-20',
                'end_date' => '2026-11-20',
                'start_time' => '07:30:00',
                'end_time' => '09:00:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->room->id,
                'status' => 'Disetujui',
            ]);

            $response = $this->actingAs($this->admin)
                ->put(route('agenda.update', $event), [
                    'judul' => 'Pelatihan Lanjutan 3 Hari',
                    'tanggal' => '2026-11-20',
                    'tanggal_selesai' => '2026-11-22',
                    'sesi_mulai' => 1,
                    'sesi_selesai' => 2,
                    'ruangan_id' => $this->room->id,
                    'keterangan' => 'Diperpanjang 3 hari.',
                ]);

            $response->assertSessionHas('success');

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'event_name' => 'Pelatihan Lanjutan 3 Hari',
                'date' => '2026-11-20 00:00:00',
                'end_date' => '2026-11-22 00:00:00',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_calendar_views_contain_date_range_for_multiday_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00'));

        try {
            Event::create([
                'event_name' => 'Bazar Sekolah',
                'date' => '2026-11-05',
                'end_date' => '2026-11-07',
                'start_time' => '07:30:00',
                'end_time' => '10:30:00',
                'requester_id' => $this->guru->id,
                'room_id' => $this->room->id,
                'status' => 'Disetujui',
            ]);

            // Guru calendar
            $responseGuru = $this->actingAs($this->guru)->get(route('guru.calendar'));
            $responseGuru->assertOk();
            $responseGuru->assertSee('2026-11-07');
            $responseGuru->assertSee('Bazar Sekolah');

            // PIC calendar
            $responsePic = $this->actingAs($this->pic)->get(route('pic.calendar'));
            $responsePic->assertOk();
            $responsePic->assertSee('2026-11-07');
            $responsePic->assertSee('Bazar Sekolah');

            // Public calendar
            $responsePublic = $this->get(route('calendar.public'));
            $responsePublic->assertOk();
            $responsePublic->assertSee('2026-11-07');
            $responsePublic->assertSee('Bazar Sekolah');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_complaints_pagination_has_five_items_per_page_for_admin_guru_and_pic(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            Complaint::create([
                'room_id' => $this->room->id,
                'reporter_id' => $this->guru->id,
                'description' => 'Kerusakan pengujian nomor ' . $i,
                'reported_at' => now()->subMinutes(10 - $i),
                'status_repair' => 'Belum Diperbaiki',
            ]);
        }

        // Admin complaints page
        $responseAdmin = $this->actingAs($this->admin)->get(route('admin.complaints'));
        $responseAdmin->assertOk();
        $responseAdmin->assertViewHas('complaints', fn ($c) => $c->count() === 5 && $c->total() === 7);

        // Guru complaints page
        $responseGuru = $this->actingAs($this->guru)->get(route('complaints.index'));
        $responseGuru->assertOk();
        $responseGuru->assertViewHas('complaints', fn ($c) => $c->count() === 5 && $c->total() === 7);

        // PIC complaints page
        $responsePic = $this->actingAs($this->pic)->get(route('pic.complaints'));
        $responsePic->assertOk();
        $responsePic->assertViewHas('complaints', fn ($c) => $c->count() === 5 && $c->total() === 7);
    }

    public function test_event_status_pelaksanaan_computation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00'));

        // 1. Sedang Berlangsung: starts at 09:00, ends at 12:00 today
        $ongoing = Event::create([
            'event_name' => 'Sedang Berlangsung',
            'date' => '2026-11-04',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);
        $this->assertEquals('Sedang Berlangsung', $ongoing->status_pelaksanaan);

        // 2. Belum Terlaksana: starts tomorrow
        $upcoming = Event::create([
            'event_name' => 'Belum Terlaksana',
            'date' => '2026-11-05',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);
        $this->assertEquals('Belum Terlaksana', $upcoming->status_pelaksanaan);

        // 3. Sudah Terlaksana: ended yesterday
        $completed = Event::create([
            'event_name' => 'Sudah Terlaksana',
            'date' => '2026-11-03',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);
        $this->assertEquals('Sudah Terlaksana', $completed->status_pelaksanaan);

        // 4. Menunggu
        $waiting = Event::create([
            'event_name' => 'Masih Menunggu',
            'date' => '2026-11-05',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);
        $this->assertEquals('Menunggu', $waiting->status_pelaksanaan);

        // 5. Ditolak
        $rejected = Event::create([
            'event_name' => 'Telah Ditolak',
            'date' => '2026-11-05',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Ditolak',
        ]);
        $this->assertEquals('Ditolak', $rejected->status_pelaksanaan);

        Carbon::setTestNow();
    }

    public function test_jadwal_ordered_by_current_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00'));

        $eventTomorrow = Event::create([
            'event_name' => 'Agenda Besok',
            'date' => '2026-11-05',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);

        $eventToday = Event::create([
            'event_name' => 'Agenda Hari Ini',
            'date' => '2026-11-04',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);

        $eventYesterday = Event::create([
            'event_name' => 'Agenda Kemarin',
            'date' => '2026-11-03',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);

        $response = $this->actingAs($this->guru)->get(route('jadwal.index'));
        $response->assertOk();

        $agendaList = $response->viewData('agenda');
        $this->assertEquals($eventToday->id, $agendaList[0]->id);
        $this->assertEquals($eventTomorrow->id, $agendaList[1]->id);
        $this->assertEquals($eventYesterday->id, $agendaList[2]->id);

        Carbon::setTestNow();
    }

    public function test_pic_can_update_complaint_status_with_repair_note(): void
    {
        $complaint = Complaint::create([
            'room_id' => $this->room->id,
            'reporter_id' => $this->guru->id,
            'description' => 'Proyektor tidak menyala dan kabel putus.',
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        $response = $this->actingAs($this->pic)
            ->put(route('pic.complaints.update', $complaint->id), [
                'status_repair' => 'Sudah Diperbaiki',
                'repair_note' => 'Kabel proyektor diganti baru dan perangkat telah diuji coba berjalan normal.',
            ]);

        $response->assertRedirect(route('pic.complaints'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status_repair' => 'Sudah Diperbaiki',
            'repair_note' => 'Kabel proyektor diganti baru dan perangkat telah diuji coba berjalan normal.',
            'handled_by' => $this->pic->id,
        ]);

        // Verify notification contains repair note
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->guru->id,
            'complaint_id' => $complaint->id,
        ]);

        // Verify rendered in views
        $picViewResponse = $this->actingAs($this->pic)->get(route('pic.complaints'));
        $picViewResponse->assertOk();
        $picViewResponse->assertSee('Kabel proyektor diganti baru');

        $guruViewResponse = $this->actingAs($this->guru)->get(route('complaints.index'));
        $guruViewResponse->assertOk();
        $guruViewResponse->assertSee('Kabel proyektor diganti baru');

        $adminViewResponse = $this->actingAs($this->admin)->get(route('admin.complaints'));
        $adminViewResponse->assertOk();
        $adminViewResponse->assertSee('Kabel proyektor diganti baru');
    }
}