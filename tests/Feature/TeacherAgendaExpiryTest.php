<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeacherAgendaExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $pic;
    private User $admin;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Bapak Guru Pembina',
        ]);

        $this->pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Pak PIC Ruangan',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Administrator Sekolah',
        ]);

        $this->room = Room::create([
            'room_name' => 'Aula Serbaguna',
            'location' => 'Lantai 1',
            'capacity' => 150,
            'pic_id' => $this->pic->id,
            'is_active' => true,
        ]);
    }

    /**
     * Uji Kasus A: Agenda dengan status "Menunggu"
     * Tanggal Agenda: 05-11-2026
     * - Sekarang 04-11-2026: TAMPIL
     * - Sekarang 05-11-2026: TAMPIL
     * - Sekarang 06-11-2026: SEMBUNYI
     */
    public function test_menunggu_status_expiry_matrix(): void
    {
        $eventMenunggu = Event::create([
            'event_name' => 'Latihan Tari Menunggu',
            'date' => '2026-11-05',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);

        // 1. Sekarang 04-11-2026 -> Tampil
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertSee('Latihan Tari Menunggu');

        // 2. Sekarang 05-11-2026 (Hari Pelaksanaan) -> Tampil
        Carbon::setTestNow(Carbon::parse('2026-11-05 14:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertSee('Latihan Tari Menunggu');

        // 3. Sekarang 06-11-2026 (Lewat Hari Pelaksanaan) -> Sembunyi
        Carbon::setTestNow(Carbon::parse('2026-11-06 08:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertDontSee('Latihan Tari Menunggu');

        // Data tetap ada di database (TIDAK terhapus)
        $this->assertDatabaseHas('events', [
            'id' => $eventMenunggu->id,
            'status' => 'Menunggu',
            'date' => '2026-11-05 00:00:00',
        ]);

        Carbon::setTestNow();
    }

    /**
     * Uji Kasus B: Agenda dengan status "Sudah Terlaksana" (Disetujui di masa lalu)
     * Tanggal Pelaksanaan: 04-11-2026
     * - Sekarang 04-11-2026: TAMPIL
     * - Sekarang 05-11-2026 (H+1): TAMPIL
     * - Sekarang 06-11-2026 (H+2): SEMBUNYI
     */
    public function test_sudah_terlaksana_status_expiry_matrix(): void
    {
        $eventTerlaksana = Event::create([
            'event_name' => 'Seminar Pendidikan Selesai',
            'date' => '2026-11-04',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
            'approved_by' => $this->pic->id,
        ]);

        // 1. Sekarang 04-11-2026 -> Tampil
        Carbon::setTestNow(Carbon::parse('2026-11-04 15:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertSee('Seminar Pendidikan Selesai');

        // 2. Sekarang 05-11-2026 (H+1) -> Tampil
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertSee('Seminar Pendidikan Selesai');

        // 3. Sekarang 06-11-2026 (H+2, sudah lewat 1 hari) -> Sembunyi
        Carbon::setTestNow(Carbon::parse('2026-11-06 09:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertDontSee('Seminar Pendidikan Selesai');

        // Data tetap aman di database (TIDAK terhapus)
        $this->assertDatabaseHas('events', [
            'id' => $eventTerlaksana->id,
            'status' => 'Disetujui',
            'date' => '2026-11-04 00:00:00',
        ]);

        Carbon::setTestNow();
    }

    /**
     * Pastikan role Admin dan PIC tidak terdampak oleh filter Agenda Saya Guru.
     */
    public function test_admin_and_pic_are_not_affected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-06 10:00:00', 'Asia/Jakarta'));

        // Agenda menunggu kemarin (tersembunyi untuk guru pada 06-11-2026)
        $expiredWaiting = Event::create([
            'event_name' => 'Agenda Menunggu Terlewat',
            'date' => '2026-11-05',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);

        // Guru tidak melihat
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertDontSee('Agenda Menunggu Terlewat');

        // PIC tetap dapat memverifikasi / melihat agenda tersebut
        $this->actingAs($this->pic)
            ->get(route('pic.events'))
            ->assertOk()
            ->assertSee('Agenda Menunggu Terlewat');

        // Admin tetap dapat melihat agenda pada kelola agenda
        $this->actingAs($this->admin)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Agenda Menunggu Terlewat');

        Carbon::setTestNow();
    }

    /**
     * Uji agenda multi-hari (rentang tanggal date s/d end_date).
     */
    public function test_multi_day_event_uses_end_date_for_expiry(): void
    {
        // Berlangsung 03-11-2026 s/d 05-11-2026
        $multiDayMenunggu = Event::create([
            'event_name' => 'Pentas Seni Multi Hari',
            'date' => '2026-11-03',
            'end_date' => '2026-11-05',
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);

        // Pada tanggal 05-11-2026 (hari terakhir): TAMPIL
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertSee('Pentas Seni Multi Hari');

        // Pada tanggal 06-11-2026 (lewat hari terakhir): SEMBUNYI
        Carbon::setTestNow(Carbon::parse('2026-11-06 08:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->guru)
            ->get(route('guru.agenda'))
            ->assertOk()
            ->assertDontSee('Pentas Seni Multi Hari');

        Carbon::setTestNow();
    }
}

