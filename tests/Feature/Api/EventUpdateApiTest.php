<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\AuthController;
use App\Models\Event;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;
    private User $otherGuru;
    private User $pic;
    private Room $room;
    private Room $otherRoom;
    private Room $inactiveRoom;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00:00'));

        $this->guru = User::factory()->create(['role' => 'guru']);
        $this->otherGuru = User::factory()->create(['role' => 'guru']);
        $this->pic = User::factory()->create(['role' => 'pic']);

        $this->room = Room::create([
            'room_name' => 'Aula Utama',
            'location' => 'Gedung A',
            'capacity' => 120,
            'pic_id' => $this->pic->id,
            'is_active' => true,
        ]);

        $this->otherRoom = Room::create([
            'room_name' => 'Lab Multimedia',
            'location' => 'Gedung B',
            'capacity' => 40,
            'pic_id' => $this->pic->id,
            'is_active' => true,
        ]);

        $this->inactiveRoom = Room::create([
            'room_name' => 'Ruang Renovasi',
            'location' => 'Gedung C',
            'capacity' => 30,
            'pic_id' => $this->pic->id,
            'is_active' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function actingAsApi(User $user): static
    {
        return $this->withToken(AuthController::issueTokenForUser($user->id));
    }

    public function test_update_single_day_agenda_succeeds(): void
    {
        $event = Event::create([
            'event_name' => 'Rapat Awal',
            'date' => '2026-11-12',
            'end_date' => '2026-11-12',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);

        $response = $this->actingAsApi($this->guru)->putJson("/api/agenda/{$event->id}", [
            'judul' => 'Rapat Evaluasi Satu Hari',
            'tanggal' => '2026-11-14',
            'jam_mulai' => '09:00',
            'jam_selesai' => '11:00',
            'ruangan_id' => $this->otherRoom->id,
            'keterangan' => 'Dipindah ke hari Sabtu.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event_name', 'Rapat Evaluasi Satu Hari')
            ->assertJsonPath('data.room_id', $this->otherRoom->id);

        $fresh = $event->fresh();
        $this->assertSame('2026-11-14', $fresh->date->toDateString());
        $this->assertNull($fresh->end_date);
        $this->assertSame('09:00', substr((string) $fresh->start_time, 0, 5));
        $this->assertSame('11:00', substr((string) $fresh->end_time, 0, 5));
    }

    public function test_update_multiday_agenda_succeeds(): void
    {
        $event = Event::create([
            'event_name' => 'Workshop Awal',
            'date' => '2026-11-12',
            'end_date' => null,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);

        $response = $this->actingAsApi($this->guru)->putJson("/api/agenda/{$event->id}", [
            'judul' => 'Pelatihan Multi-Hari',
            'tanggal' => '2026-11-15',
            'tanggal_selesai' => '2026-11-17',
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'ruangan_id' => $this->room->id,
            'keterangan' => 'Diperpanjang menjadi 3 hari.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event_name', 'Pelatihan Multi-Hari');

        $fresh = $event->fresh();
        $this->assertSame('2026-11-15', $fresh->date->toDateString());
        $this->assertSame('2026-11-17', $fresh->end_date->toDateString());
    }

    public function test_update_detects_conflict_on_first_day_including_legacy_null_end_date(): void
    {
        $target = Event::create([
            'event_name' => 'Agenda Target',
            'date' => '2026-11-20',
            'end_date' => null,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->otherRoom->id,
            'status' => 'Menunggu',
        ]);

        // Bentrok di hari pertama (2026-11-15), legacy row dengan end_date = null
        Event::create([
            'event_name' => 'Agenda Hari Pertama',
            'date' => '2026-11-15',
            'end_date' => null,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'requester_id' => $this->otherGuru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);

        $response = $this->actingAsApi($this->guru)->putJson("/api/agenda/{$target->id}", [
            'judul' => 'Ubah Ke Rentang Bentrok Hari 1',
            'tanggal' => '2026-11-15',
            'tanggal_selesai' => '2026-11-17',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan_id' => $this->room->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Ruangan sudah terpakai pada waktu tersebut.');

        $this->assertSame('Agenda Target', $target->fresh()->event_name);
    }

    public function test_update_detects_conflict_on_second_day(): void
    {
        $target = Event::create([
            'event_name' => 'Agenda Target',
            'date' => '2026-11-20',
            'end_date' => null,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->otherRoom->id,
            'status' => 'Menunggu',
        ]);

        // Bentrok di hari kedua (2026-11-16) dalam rentang 15-17 Nov
        Event::create([
            'event_name' => 'Agenda Hari Kedua',
            'date' => '2026-11-16',
            'end_date' => null,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'requester_id' => $this->otherGuru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);

        $response = $this->actingAsApi($this->guru)->putJson("/api/agenda/{$target->id}", [
            'judul' => 'Ubah Ke Rentang Bentrok Hari 2',
            'tanggal' => '2026-11-15',
            'tanggal_selesai' => '2026-11-17',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan_id' => $this->room->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Ruangan sudah terpakai pada waktu tersebut.');

        $this->assertSame('Agenda Target', $target->fresh()->event_name);
    }

    public function test_update_detects_conflict_on_last_day(): void
    {
        $target = Event::create([
            'event_name' => 'Agenda Target',
            'date' => '2026-11-20',
            'end_date' => null,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->otherRoom->id,
            'status' => 'Menunggu',
        ]);

        // Bentrok di hari terakhir (2026-11-17) oleh agenda multi-hari lain
        Event::create([
            'event_name' => 'Agenda Hari Terakhir',
            'date' => '2026-11-17',
            'end_date' => '2026-11-19',
            'start_time' => '09:30:00',
            'end_time' => '11:30:00',
            'requester_id' => $this->otherGuru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);

        $response = $this->actingAsApi($this->guru)->putJson("/api/agenda/{$target->id}", [
            'judul' => 'Ubah Ke Rentang Bentrok Hari Terakhir',
            'tanggal' => '2026-11-15',
            'tanggal_selesai' => '2026-11-17',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan_id' => $this->room->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Ruangan sudah terpakai pada waktu tersebut.');

        $this->assertSame('Agenda Target', $target->fresh()->event_name);
    }

    public function test_update_allows_non_conflicting_multiday_and_adjacent_times(): void
    {
        $target = Event::create([
            'event_name' => 'Agenda Target',
            'date' => '2026-11-20',
            'end_date' => null,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->otherRoom->id,
            'status' => 'Menunggu',
        ]);

        // Tepat bersebelahan di hari yang sama (08:00-10:00 vs 10:00-12:00) -> TIDAK boleh bentrok
        Event::create([
            'event_name' => 'Agenda Pagi Bersebelahan',
            'date' => '2026-11-16',
            'end_date' => null,
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->otherGuru->id,
            'room_id' => $this->room->id,
            'status' => 'Disetujui',
        ]);

        // Agenda ditolak pada jam yang sama -> TIDAK dihitung bentrok
        Event::create([
            'event_name' => 'Agenda Ditolak',
            'date' => '2026-11-16',
            'end_date' => '2026-11-17',
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'requester_id' => $this->otherGuru->id,
            'room_id' => $this->room->id,
            'status' => 'Ditolak',
        ]);

        $response = $this->actingAsApi($this->guru)->putJson("/api/agenda/{$target->id}", [
            'judul' => 'Workshop Siang Bebas Bentrok',
            'tanggal' => '2026-11-15',
            'tanggal_selesai' => '2026-11-17',
            'jam_mulai' => '10:00',
            'jam_selesai' => '12:00',
            'ruangan_id' => $this->room->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event_name', 'Workshop Siang Bebas Bentrok');
    }

    public function test_update_without_changing_dates_succeeds(): void
    {
        $event = Event::create([
            'event_name' => 'Seminar Lama',
            'date' => '2026-11-15',
            'end_date' => '2026-11-17',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
            'description' => 'Catatan awal',
        ]);

        $response = $this->actingAsApi($this->guru)->putJson("/api/agenda/{$event->id}", [
            'judul' => 'Seminar Revisi Judul',
            'tanggal' => '2026-11-15',
            'tanggal_selesai' => '2026-11-17',
            'jam_mulai' => '13:00',
            'jam_selesai' => '15:00',
            'ruangan_id' => $this->otherRoom->id,
            'keterangan' => 'Catatan diperbarui',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event_name', 'Seminar Revisi Judul')
            ->assertJsonPath('data.description', 'Catatan diperbarui');

        $fresh = $event->fresh();
        $this->assertSame('2026-11-15', $fresh->date->toDateString());
        $this->assertSame('2026-11-17', $fresh->end_date->toDateString());
        $this->assertSame($this->otherRoom->id, $fresh->room_id);
    }

    public function test_update_same_agenda_does_not_conflict_with_itself(): void
    {
        $event = Event::create([
            'event_name' => 'Pelatihan 3 Hari',
            'date' => '2026-11-15',
            'end_date' => '2026-11-17',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);

        // Ruangan sama, tanggal tumpang tindih dengan dirinya sendiri (16-18 Nov), jam tumpang tindih (10:00-12:00)
        $response = $this->actingAsApi($this->guru)->putJson("/api/agenda/{$event->id}", [
            'judul' => 'Pelatihan 3 Hari Diperbarui',
            'tanggal' => '2026-11-16',
            'tanggal_selesai' => '2026-11-18',
            'jam_mulai' => '10:00',
            'jam_selesai' => '12:00',
            'ruangan_id' => $this->room->id,
            'keterangan' => 'Geser sehari dan ubah jam pada ruangan yang sama.',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event_name', 'Pelatihan 3 Hari Diperbarui');

        $fresh = $event->fresh();
        $this->assertSame('2026-11-16', $fresh->date->toDateString());
        $this->assertSame('2026-11-18', $fresh->end_date->toDateString());
    }

    public function test_update_preserves_active_room_status_authorization_and_date_validation(): void
    {
        $event = Event::create([
            'event_name' => 'Agenda Terproteksi',
            'date' => '2026-11-15',
            'end_date' => '2026-11-16',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'requester_id' => $this->guru->id,
            'room_id' => $this->room->id,
            'status' => 'Menunggu',
        ]);

        // 1. tanggal_selesai lebih awal dari tanggal -> 422
        $this->actingAsApi($this->guru)->putJson("/api/agenda/{$event->id}", [
            'judul' => 'Tanggal Terbalik',
            'tanggal' => '2026-11-16',
            'tanggal_selesai' => '2026-11-15',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan_id' => $this->room->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['tanggal_selesai']);

        // 2. Ruangan tidak aktif -> 422
        $this->actingAsApi($this->guru)->putJson("/api/agenda/{$event->id}", [
            'judul' => 'Ke Ruang Nonaktif',
            'tanggal' => '2026-11-15',
            'tanggal_selesai' => '2026-11-16',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan_id' => $this->inactiveRoom->id,
        ])->assertStatus(422)->assertJsonPath('success', false);

        // 3. Guru lain tidak boleh mengubah agenda -> 403
        $this->actingAsApi($this->otherGuru)->putJson("/api/agenda/{$event->id}", [
            'judul' => 'Dibajak Guru Lain',
            'tanggal' => '2026-11-15',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan_id' => $this->room->id,
        ])->assertForbidden();

        // 4. Agenda yang sudah Disetujui tidak dapat diubah melalui PUT /api/agenda/{id}
        $event->update(['status' => 'Disetujui']);
        $this->actingAsApi($this->guru)->putJson("/api/agenda/{$event->id}", [
            'judul' => 'Ubah Setelah Disetujui',
            'tanggal' => '2026-11-15',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
            'ruangan_id' => $this->room->id,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }
}

