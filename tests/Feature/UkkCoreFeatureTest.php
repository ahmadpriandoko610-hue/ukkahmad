<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UkkCoreFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_cannot_create_an_overlapping_booking(): void
    {
        $guru = User::factory()->create(['role' => 'guru']);
        $pic = User::factory()->create(['role' => 'pic']);
        $room = Room::create([
            'room_name' => 'Lab RPL',
            'location' => 'Lantai 2',
            'capacity' => 32,
            'pic_id' => $pic->id,
        ]);

        Event::create([
            'event_name' => 'Rapat Kurikulum',
            'date' => '2026-08-25',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'requester_id' => $guru->id,
            'room_id' => $room->id,
            'status' => 'Disetujui',
        ]);

        $this->actingAs($guru)
            ->post(route('guru.events.store'), [
                'judul' => 'Pelatihan',
                'tanggal' => '2026-08-25',
                'jam_mulai' => '10:00',
                'jam_selesai' => '12:00',
                'ruangan_id' => $room->id,
            ]);

        $this->assertDatabaseMissing('events', [
            'event_name' => 'Pelatihan',
            'room_id' => $room->id,
        ]);
    }

    public function test_pic_can_only_update_complaints_for_assigned_rooms(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);
        $otherPic = User::factory()->create(['role' => 'pic']);
        $guru = User::factory()->create(['role' => 'guru']);
        $room = Room::create([
            'room_name' => 'Aula',
            'location' => 'Gedung A',
            'capacity' => 100,
            'pic_id' => $otherPic->id,
        ]);
        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $guru->id,
            'description' => 'Proyektor rusak',
            'reported_at' => now(),
        ]);

        $this->actingAs($pic)
            ->put(route('pic.complaints.update', $complaint), ['status_repair' => 'Sudah Diperbaiki'])
            ->assertForbidden();
    }

    public function test_pic_calendar_shows_approved_agendas_for_all_rooms(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 12:00:00'));

        try {
            $pic = User::factory()->create(['role' => 'pic']);
            $otherPic = User::factory()->create(['role' => 'pic']);
            $guru = User::factory()->create(['role' => 'guru']);
            $otherPicRoom = Room::create([
                'room_name' => 'Aula Bawah',
                'location' => 'Gedung A',
                'capacity' => 100,
                'pic_id' => $otherPic->id,
            ]);

            Event::create([
                'event_name' => 'Agenda ruangan PIC lain',
                'date' => '2026-11-04',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'requester_id' => $guru->id,
                'room_id' => $otherPicRoom->id,
                'status' => 'Disetujui',
            ]);

            $this->actingAs($pic)
                ->get(route('pic.calendar'))
                ->assertOk()
                ->assertSee('Agenda ruangan PIC lain')
                ->assertSee('Aula Bawah')
                ->assertSee('semua ruangan');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_repaired_complaints_disappear_from_active_lists_after_one_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));

        try {
            $admin = User::factory()->create(['role' => 'admin']);
            $guru = User::factory()->create(['role' => 'guru']);
            $pic = User::factory()->create(['role' => 'pic']);
            $room = Room::create([
                'room_name' => 'Lab Komputer',
                'location' => 'Lantai 1',
                'capacity' => 30,
                'pic_id' => $pic->id,
            ]);

            Complaint::create([
                'room_id' => $room->id,
                'reporter_id' => $guru->id,
                'description' => 'Kerusakan yang belum diperbaiki',
                'status_repair' => 'Belum Diperbaiki',
                'reported_at' => now(),
            ]);
            Complaint::create([
                'room_id' => $room->id,
                'reporter_id' => $guru->id,
                'description' => 'Kerusakan yang baru diperbaiki',
                'status_repair' => 'Sudah Diperbaiki',
                'repaired_at' => now()->subHours(23),
                'reported_at' => now()->subDays(2),
            ]);
            $expiredComplaint = Complaint::create([
                'room_id' => $room->id,
                'reporter_id' => $guru->id,
                'description' => 'Kerusakan yang sudah lama diperbaiki',
                'status_repair' => 'Sudah Diperbaiki',
                'repaired_at' => now()->subDay()->subSecond(),
                'reported_at' => now()->subDays(3),
            ]);

            $this->actingAs($admin)
                ->get(route('admin.complaints'))
                ->assertOk()
                ->assertSee('Kerusakan yang belum diperbaiki')
                ->assertSee('Kerusakan yang baru diperbaiki')
                ->assertDontSee('Kerusakan yang sudah lama diperbaiki');

            $this->assertDatabaseHas('complaints', ['id' => $expiredComplaint->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_rejected_events_disappear_from_active_lists_after_30_days_without_being_deleted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));

        try {
            $guru = User::factory()->create(['role' => 'guru']);
            $pic = User::factory()->create(['role' => 'pic']);
            $room = Room::create([
                'room_name' => 'Aula',
                'location' => 'Gedung A',
                'capacity' => 100,
                'pic_id' => $pic->id,
            ]);

            $recentlyRejected = Event::create([
                'event_name' => 'Penolakan baru',
                'date' => '2026-12-01',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'requester_id' => $guru->id,
                'room_id' => $room->id,
                'status' => 'Ditolak',
                'rejected_at' => now()->subDays(29),
            ]);
            $olderRejection = Event::create([
                'event_name' => 'Penolakan lama',
                'date' => '2026-12-01',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'requester_id' => $guru->id,
                'room_id' => $room->id,
                'status' => 'Ditolak',
                'rejected_at' => now()->subDays(31),
            ]);

            $this->actingAs($guru)
                ->get(route('guru.agenda'))
                ->assertOk()
                ->assertSee('Penolakan baru')
                ->assertDontSee('Penolakan lama');

            $this->assertDatabaseHas('events', ['id' => $recentlyRejected->id]);
            $this->assertDatabaseHas('events', ['id' => $olderRejection->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pic_rejection_records_the_rejection_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));

        try {
            $guru = User::factory()->create(['role' => 'guru']);
            $pic = User::factory()->create(['role' => 'pic']);
            $room = Room::create([
                'room_name' => 'Aula',
                'location' => 'Gedung A',
                'capacity' => 100,
                'pic_id' => $pic->id,
            ]);
            $event = Event::create([
                'event_name' => 'Rapat',
                'date' => '2026-11-01',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'requester_id' => $guru->id,
                'room_id' => $room->id,
                'status' => 'Menunggu',
            ]);

            $this->actingAs($pic)
                ->post(route('events.reject', $event), [
                    'rejection_reason' => 'Ruangan sedang digunakan.',
                ])
                ->assertRedirect(route('pic.events'));

            $this->assertDatabaseHas('events', [
                'id' => $event->id,
                'status' => 'Ditolak',
                'rejected_at' => now()->toDateTimeString(),
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pic_status_update_records_the_repair_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00'));

        try {
            $pic = User::factory()->create(['role' => 'pic']);
            $guru = User::factory()->create(['role' => 'guru']);
            $room = Room::create([
                'room_name' => 'Aula',
                'location' => 'Gedung A',
                'capacity' => 100,
                'pic_id' => $pic->id,
            ]);
            $complaint = Complaint::create([
                'room_id' => $room->id,
                'reporter_id' => $guru->id,
                'description' => 'Speaker rusak',
                'reported_at' => now(),
            ]);

            $this->actingAs($pic)
                ->put(route('pic.complaints.update', $complaint), [
                    'status_repair' => 'Sudah Diperbaiki',
                ])
                ->assertRedirect(route('pic.complaints'));

            $this->assertDatabaseHas('complaints', [
                'id' => $complaint->id,
                'repaired_at' => now()->toDateTimeString(),
            ]);

            $this->actingAs($pic)
                ->put(route('pic.complaints.update', $complaint), [
                    'status_repair' => 'Menunggu Spare Part',
                ])
                ->assertRedirect(route('pic.complaints'));

            $this->assertDatabaseHas('complaints', [
                'id' => $complaint->id,
                'repaired_at' => null,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_move_creates_a_notification_for_the_requester(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $guru = User::factory()->create(['role' => 'guru']);
        $pic = User::factory()->create(['role' => 'pic']);
        $oldRoom = Room::create(['room_name' => 'Ruang 1', 'location' => 'A', 'capacity' => 30, 'pic_id' => $pic->id]);
        $newRoom = Room::create(['room_name' => 'Ruang 2', 'location' => 'B', 'capacity' => 30, 'pic_id' => $pic->id]);
        $event = Event::create([
            'event_name' => 'Sosialisasi',
            'date' => '2026-08-28',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'requester_id' => $guru->id,
            'room_id' => $oldRoom->id,
            'status' => 'Disetujui',
        ]);

        $this->actingAs($admin)
            ->put(route('events.move', $event), [
                'room_id' => $newRoom->id,
                'current_room_id' => $oldRoom->id,
                'move_reason' => 'Akan digunakan untuk rapat mendadak.',
            ])
            ->assertRedirect(route('agenda.index'));

        $this->assertDatabaseHas('events', ['id' => $event->id, 'room_id' => $newRoom->id]);
        $this->assertDatabaseHas('schedule_notifications', ['user_id' => $guru->id, 'event_id' => $event->id]);
        $this->assertSame(1, ScheduleNotification::count());
    }

    public function test_guru_complaint_from_approved_agenda_stores_correct_event_id_and_room_id(): void
    {
        Storage::fake('public');

        $guru = User::factory()->create(['role' => 'guru']);
        $pic = User::factory()->create(['role' => 'pic']);
        $agendaRoom = Room::create([
            'room_name' => 'Lab Multimedia',
            'location' => 'Gedung A',
            'capacity' => 30,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);
        $otherRoom = Room::create([
            'room_name' => 'Ruang Rapat',
            'location' => 'Gedung B',
            'capacity' => 20,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $agenda = Event::create([
            'event_name' => 'Praktik Editing Video',
            'date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'start_time' => '08:15:00',
            'end_time' => '10:30:00',
            'requester_id' => $guru->id,
            'room_id' => $agendaRoom->id,
            'status' => 'Disetujui',
        ]);

        $this->actingAs($guru)
            ->get(route('complaints.create', ['agenda_id' => $agenda->id]))
            ->assertOk()
            ->assertSee('name="agenda_id" value="' . $agenda->id . '"', false);

        // Form pengaduan dari agenda tidak mengirim input room_id (menggunakan room_id milik agenda)
        $response = $this->actingAs($guru)->post(route('complaints.store'), [
            'agenda_id' => $agenda->id,
            'description' => 'Proyektor LCD berkedip saat agenda berlangsung.',
            'photo' => UploadedFile::fake()->image('kerusakan-agenda.jpg'),
        ]);

        $response->assertRedirect(route('complaints.index'));
        $response->assertSessionHas('success', 'Pengaduan kerusakan berhasil dikirim.');

        $this->assertDatabaseHas('complaints', [
            'room_id' => $agendaRoom->id,
            'event_id' => $agenda->id,
            'reporter_id' => $guru->id,
            'description' => 'Proyektor LCD berkedip saat agenda berlangsung.',
            'status_repair' => 'Belum Diperbaiki',
        ]);

        // Meski room_id dimanipulasi dalam payload, controller tetap memakai room_id milik agenda
        $this->actingAs($guru)->post(route('complaints.store'), [
            'agenda_id' => $agenda->id,
            'room_id' => $otherRoom->id,
            'description' => 'Kabel HDMI longgar di meja presenter.',
            'photo' => UploadedFile::fake()->image('kabel-rusak.jpg'),
        ])->assertRedirect(route('complaints.index'));

        $this->assertDatabaseHas('complaints', [
            'room_id' => $agendaRoom->id,
            'event_id' => $agenda->id,
            'reporter_id' => $guru->id,
            'description' => 'Kabel HDMI longgar di meja presenter.',
        ]);

        $complaint = Complaint::firstOrFail();
        $this->assertSame($agenda->id, $complaint->event_id);
        $this->assertSame($agendaRoom->id, $complaint->room_id);
        $this->assertTrue($complaint->event->is($agenda));
        $this->assertSame(2, $agenda->complaints()->count());
        Storage::disk('public')->assertExists($complaint->photo_path);

        $this->actingAs($guru)
            ->get(route('complaints.index'))
            ->assertOk()
            ->assertSeeText('Terkait Agenda: Praktik Editing Video');
    }

    public function test_guru_complaint_without_agenda_succeeds_with_null_event_id(): void
    {
        Storage::fake('public');

        $guru = User::factory()->create(['role' => 'guru']);
        $pic = User::factory()->create(['role' => 'pic']);
        $room = Room::create([
            'room_name' => 'Aula Sekolah',
            'location' => 'Lantai 1',
            'capacity' => 120,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($guru)->post(route('complaints.store'), [
            'room_id' => $room->id,
            'description' => 'Kursi patah di sisi kanan aula.',
            'photo' => UploadedFile::fake()->image('kursi-rusak.png'),
        ]);

        $response->assertRedirect(route('complaints.index'));
        $response->assertSessionHas('success', 'Pengaduan kerusakan berhasil dikirim.');

        $this->assertDatabaseHas('complaints', [
            'room_id' => $room->id,
            'event_id' => null,
            'reporter_id' => $guru->id,
            'description' => 'Kursi patah di sisi kanan aula.',
            'status_repair' => 'Belum Diperbaiki',
        ]);

        $this->actingAs($guru)->post(route('complaints.store'), [
            'agenda_id' => '',
            'room_id' => $room->id,
            'description' => 'Pintu aula sulit dikunci.',
            'photo' => UploadedFile::fake()->image('pintu-rusak.png'),
        ])->assertRedirect(route('complaints.index'));

        $this->assertDatabaseHas('complaints', [
            'room_id' => $room->id,
            'event_id' => null,
            'reporter_id' => $guru->id,
            'description' => 'Pintu aula sulit dikunci.',
        ]);

        $complaint = Complaint::firstOrFail();
        $this->assertNull($complaint->event_id);
        $this->assertNull($complaint->event);
        Storage::disk('public')->assertExists($complaint->photo_path);
    }

    public function test_guru_cannot_link_complaint_to_unauthorized_or_unapproved_agenda(): void
    {
        Storage::fake('public');

        $guru = User::factory()->create(['role' => 'guru']);
        $otherGuru = User::factory()->create(['role' => 'guru']);
        $admin = User::factory()->create(['role' => 'admin']);
        $pic = User::factory()->create(['role' => 'pic']);
        $room = Room::create([
            'room_name' => 'Lab Kimia',
            'location' => 'Gedung Sains',
            'capacity' => 32,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $otherGuruApprovedEvent = Event::create([
            'event_name' => 'Praktikum Guru Lain',
            'date' => '2026-11-15',
            'start_time' => '08:15:00',
            'end_time' => '09:45:00',
            'requester_id' => $otherGuru->id,
            'room_id' => $room->id,
            'status' => 'Disetujui',
        ]);

        $adminApprovedEvent = Event::create([
            'event_name' => 'Rapat Dinas Sekolah',
            'date' => '2026-11-15',
            'start_time' => '10:00:00',
            'end_time' => '11:30:00',
            'requester_id' => $admin->id,
            'room_id' => $room->id,
            'status' => 'Disetujui',
        ]);

        $ownPendingEvent = Event::create([
            'event_name' => 'Agenda Menunggu Persetujuan',
            'date' => '2026-11-16',
            'start_time' => '08:15:00',
            'end_time' => '09:45:00',
            'requester_id' => $guru->id,
            'room_id' => $room->id,
            'status' => 'Menunggu',
        ]);

        $ownRejectedEvent = Event::create([
            'event_name' => 'Agenda Ditolak',
            'date' => '2026-11-17',
            'start_time' => '08:15:00',
            'end_time' => '09:45:00',
            'requester_id' => $guru->id,
            'room_id' => $room->id,
            'status' => 'Ditolak',
        ]);

        // Form create tidak boleh dibuka untuk agenda milik pengguna lain atau agenda belum disetujui
        $this->actingAs($guru)
            ->get(route('complaints.create', ['agenda_id' => $otherGuruApprovedEvent->id]))
            ->assertNotFound();
        $this->actingAs($guru)
            ->get(route('complaints.create', ['agenda_id' => $adminApprovedEvent->id]))
            ->assertNotFound();
        $this->actingAs($guru)
            ->get(route('complaints.create', ['agenda_id' => $ownPendingEvent->id]))
            ->assertNotFound();

        $invalidAgendaPayloads = [
            $otherGuruApprovedEvent->id,
            $adminApprovedEvent->id,
            $ownPendingEvent->id,
            $ownRejectedEvent->id,
            999999,
            0,
            'invalid-id',
            [(string) $otherGuruApprovedEvent->id],
        ];

        foreach ($invalidAgendaPayloads as $index => $invalidAgendaId) {
            $response = $this->actingAs($guru)
                ->from(route('complaints.create'))
                ->post(route('complaints.store'), [
                    'agenda_id' => $invalidAgendaId,
                    'room_id' => $room->id,
                    'description' => 'Percobaan pengaduan ilegal #' . $index,
                    'photo' => UploadedFile::fake()->image("bukti-{$index}.jpg"),
                ]);

            $response->assertRedirect(route('complaints.create'));
            $response->assertSessionHasErrors('agenda_id');
        }

        $this->assertDatabaseCount('complaints', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles('complaints'));
    }

    public function test_application_timezone_defaults_to_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    public function test_new_complaint_records_and_displays_waktu_lapor_in_asia_jakarta_without_double_conversion(): void
    {
        Storage::fake('public');
        Carbon::setTestNow(Carbon::create(2026, 11, 11, 8, 49, 0, 'Asia/Jakarta'));

        try {
            $guru = User::factory()->create(['role' => 'guru']);
            $pic = User::factory()->create(['role' => 'pic']);
            $admin = User::factory()->create(['role' => 'admin']);
            $room = Room::create([
                'room_name' => 'Lab Komputer 1',
                'location' => 'Gedung Utama',
                'capacity' => 36,
                'pic_id' => $pic->id,
                'is_active' => true,
            ]);

            $response = $this->actingAs($guru)->post(route('complaints.store'), [
                'room_id' => $room->id,
                'description' => 'AC ruang lab mengeluarkan suara bising.',
                'photo' => UploadedFile::fake()->image('ac-rusak.jpg'),
            ]);

            $response->assertRedirect(route('complaints.index'));
            $response->assertSessionHas('success', 'Pengaduan kerusakan berhasil dikirim.');

            $complaint = Complaint::firstOrFail();

            $this->assertSame('2026-11-11 08:49:00', $complaint->getRawOriginal('reported_at'));
            $this->assertSame('2026-11-11 08:49:00', $complaint->getRawOriginal('created_at'));
            $this->assertSame('Asia/Jakarta', $complaint->reported_at->timezoneName);
            $this->assertSame('11 Nov 2026, 08:49', $complaint->reported_at->format('d M Y, H:i'));

            $this->actingAs($guru)
                ->get(route('complaints.index'))
                ->assertOk()
                ->assertSeeText('Waktu Lapor: 11 Nov 2026, 08:49')
                ->assertDontSeeText('11 Nov 2026, 01:49')
                ->assertDontSeeText('11 Nov 2026, 15:49');

            $this->actingAs($admin)
                ->get(route('admin.complaints'))
                ->assertOk()
                ->assertSeeText('Dilaporkan: 11 Nov 2026, 08:49')
                ->assertDontSeeText('11 Nov 2026, 01:49')
                ->assertDontSeeText('11 Nov 2026, 15:49');

            $this->actingAs($pic)
                ->get(route('pic.complaints'))
                ->assertOk()
                ->assertSeeText('Dilaporkan: 11 Nov 2026, 08:49')
                ->assertDontSeeText('11 Nov 2026, 01:49')
                ->assertDontSeeText('11 Nov 2026, 15:49');

            $this->actingAs($pic)
                ->get(route('pic.complaints.edit', $complaint))
                ->assertOk()
                ->assertSeeText('Dilaporkan 11 Nov 2026, 08:49')
                ->assertDontSeeText('11 Nov 2026, 01:49')
                ->assertDontSeeText('11 Nov 2026, 15:49');

            $this->actingAs($pic)
                ->get(route('pic.complaints.report', ['periode' => 'harian', 'tanggal' => '2026-11-11']))
                ->assertOk()
                ->assertSeeText('11-11-2026 08:49')
                ->assertDontSeeText('11-11-2026 01:49')
                ->assertDontSeeText('11-11-2026 15:49');

            $this->actingAs($pic)
                ->put(route('pic.complaints.update', $complaint), [
                    'status_repair' => 'Sudah Diperbaiki',
                    'repair_note' => 'Unit AC sudah dibersihkan dan diperbaiki.',
                ])
                ->assertRedirect(route('pic.complaints'));

            $complaint->refresh();
            $this->assertSame('2026-11-11 08:49:00', $complaint->getRawOriginal('repaired_at'));

            $this->actingAs($guru)
                ->get(route('complaints.index'))
                ->assertOk()
                ->assertSeeText('Selesai pada: 11 Nov 2026, 08:49')
                ->assertDontSeeText('11 Nov 2026, 15:49');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_utc_test_clock_converts_once_to_wib_when_storing_complaint(): void
    {
        Storage::fake('public');
        // 01:49 UTC equals 08:49 Asia/Jakarta (WIB)
        Carbon::setTestNow(Carbon::create(2026, 11, 11, 1, 49, 0, 'UTC'));

        try {
            $guru = User::factory()->create(['role' => 'guru']);
            $pic = User::factory()->create(['role' => 'pic']);
            $room = Room::create([
                'room_name' => 'Ruang Studio',
                'location' => 'Lantai 2',
                'capacity' => 25,
                'pic_id' => $pic->id,
                'is_active' => true,
            ]);

            $this->actingAs($guru)
                ->post(route('complaints.store'), [
                    'room_id' => $room->id,
                    'description' => 'Lampu studio berkedip.',
                    'photo' => UploadedFile::fake()->image('lampu.png'),
                ])
                ->assertRedirect(route('complaints.index'));

            $complaint = Complaint::firstOrFail();

            $this->assertSame('2026-11-11 08:49:00', $complaint->getRawOriginal('reported_at'));

            $this->actingAs($guru)
                ->get(route('complaints.index'))
                ->assertOk()
                ->assertSeeText('Waktu Lapor: 11 Nov 2026, 08:49')
                ->assertDontSeeText('11 Nov 2026, 01:49')
                ->assertDontSeeText('11 Nov 2026, 15:49');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_existing_old_complaint_timestamp_is_preserved_without_unexpected_shift(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 11, 8, 49, 0, 'Asia/Jakarta'));

        try {
            $guru = User::factory()->create(['role' => 'guru']);
            $pic = User::factory()->create(['role' => 'pic']);
            $admin = User::factory()->create(['role' => 'admin']);
            $room = Room::create([
                'room_name' => 'Ruang Teori 3',
                'location' => 'Gedung B',
                'capacity' => 36,
                'pic_id' => $pic->id,
                'is_active' => true,
            ]);

            $oldTimestamp = '2026-11-02 07:42:58';
            $complaintId = DB::table('complaints')->insertGetId([
                'room_id' => $room->id,
                'reporter_id' => $guru->id,
                'description' => 'Pengaduan lama sebelum pembaruan timezone.',
                'status_repair' => 'Belum Diperbaiki',
                'reported_at' => $oldTimestamp,
                'created_at' => $oldTimestamp,
                'updated_at' => $oldTimestamp,
            ]);

            $complaint = Complaint::findOrFail($complaintId);

            // Timestamp mentah pada database tetap utuh dan tidak digeser +7 jam atau -7 jam
            $this->assertSame($oldTimestamp, $complaint->getRawOriginal('reported_at'));
            $this->assertSame('02 Nov 2026, 07:42', $complaint->reported_at->format('d M Y, H:i'));

            $this->actingAs($guru)
                ->get(route('complaints.index'))
                ->assertOk()
                ->assertSeeText('Waktu Lapor: 02 Nov 2026, 07:42')
                ->assertDontSeeText('02 Nov 2026, 14:42')
                ->assertDontSeeText('02 Nov 2026, 00:42');

            $this->actingAs($admin)
                ->get(route('admin.complaints'))
                ->assertOk()
                ->assertSeeText('Dilaporkan: 02 Nov 2026, 07:42')
                ->assertDontSeeText('02 Nov 2026, 14:42')
                ->assertDontSeeText('02 Nov 2026, 00:42');

            $this->actingAs($pic)
                ->get(route('pic.complaints'))
                ->assertOk()
                ->assertSeeText('Dilaporkan: 02 Nov 2026, 07:42')
                ->assertDontSeeText('02 Nov 2026, 14:42')
                ->assertDontSeeText('02 Nov 2026, 00:42');
        } finally {
            Carbon::setTestNow();
        }
    }
}
