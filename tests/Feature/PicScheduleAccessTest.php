<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PicScheduleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PIC harus menggunakan route /pic/schedule,
     * bukan route /schedule milik Guru.
     */
    public function test_pic_dashboard_uses_pic_schedule_route(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'PIC User',
            'email' => 'pic@example.com',
            'role' => 'pic',
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard/pic');

        $response->assertOk();

        // Pastikan link Jadwal mengarah ke route PIC
        $response->assertSee(
            'href="' . route('pic.schedule') . '"',
            false
        );

        // Pastikan tidak menggunakan route Jadwal Guru
        $response->assertDontSee(
            'href="' . route('schedule.index') . '"',
            false
        );
    }


    /**
     * Jadwal tidak boleh menampilkan nama agenda,
     * nama ruangan, atau lokasi secara duplikat.
     */
    public function test_schedule_page_does_not_duplicate_agenda_or_room_name(): void
    {
        $this->travelTo(Carbon::parse('2026-08-01 08:00:00'));

        $guru = User::factory()->create([
            'name' => 'Guru User',
            'email' => 'guru@example.com',
            'role' => 'guru',
        ]);

        $pic = User::factory()->create([
            'name' => 'PIC User 2',
            'email' => 'pic2@example.com',
            'role' => 'pic',
        ]);
        $room = Room::create([
            'room_name' => 'Ruang 1',
            'location' => 'Gedung A, Lantai 1',
            'capacity' => 30,
            'pic_id' => $pic->id,
            'description' => 'Ruang kelas',
            'is_active' => true,
        ]);

        Event::create([
            'event_name' => 'Rapat',
            'date' => now()->addDays(14)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $guru->id,
            'room_id' => $room->id,
            'status' => 'Disetujui',
            'description' => 'Rapat',
        ]);

        $otherPic = User::factory()->create(['role' => 'pic']);
        $otherRoom = Room::create([
            'room_name' => 'Ruang Lain',
            'location' => 'Gedung B',
            'capacity' => 20,
            'pic_id' => $otherPic->id,
            'is_active' => true,
        ]);
        Event::create([
            'event_name' => 'Agenda Ruang Lain',
            'date' => now()->addDays(14)->toDateString(),
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
            'requester_id' => $guru->id,
            'room_id' => $otherRoom->id,
            'status' => 'Disetujui',
        ]);

        $this->actingAs($pic);

        $response = $this->get('/pic/schedule');

        $response->assertOk();

        $content = $response->getContent();

        // Agenda di ruangan PIC tampil tepat satu kali.
        $this->assertSame(
            1,
            substr_count($content, 'Rapat')
        );
        $response->assertDontSee('Agenda Ruang Lain');

        // Nama ruangan hanya muncul satu kali
        $this->assertSame(
            1,
            substr_count($content, 'Ruang 1')
        );

        // Lokasi ruangan yang menjadi tanggung jawab PIC ditampilkan sekali.
        $this->assertSame(
            1,
            substr_count($content, 'Gedung A, Lantai 1')
        );
    }
}
