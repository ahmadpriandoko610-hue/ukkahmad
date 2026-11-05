<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Room;
use App\Models\ScheduleNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
}
