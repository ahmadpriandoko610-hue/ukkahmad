<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Room;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_calendar_and_room_list_remain_available_without_login(): void
    {
        $this->getJson('/api/kalender')->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/ruangan')->assertOk()->assertJsonPath('success', true);
    }

    public function test_private_routes_reject_missing_invalid_and_revoked_tokens(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/profile')->assertUnauthorized();

        $user = User::factory()->create(['role' => 'guru', 'password' => 'password']);
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $expiredToken = 'expired_' . bin2hex(random_bytes(16));
        Cache::put('api_token_' . hash('sha256', $expiredToken), $user->id, now()->subMinute());
        $this->withToken($expiredToken)->getJson('/api/profile')->assertUnauthorized();

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('data.token');
        $this->withToken($token)->getJson('/api/profile')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->withToken($token)->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_account_created_through_the_website_can_log_in_to_the_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Guru Website',
            'email' => 'guru.website@example.test',
            'password' => 'password-baru',
            'role' => 'guru',
        ])->assertRedirect(route('users.index'));

        $this->postJson('/api/login', [
            'email' => 'guru.website@example.test',
            'password' => 'password-baru',
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_agenda_creation_uses_authenticated_user_instead_of_request_ids(): void
    {
        $guru = User::factory()->create(['role' => 'guru']);
        $otherGuru = User::factory()->create(['role' => 'guru']);
        $pic = User::factory()->create(['role' => 'pic']);
        $room = Room::create([
            'room_name' => 'Lab API',
            'location' => 'Gedung A',
            'capacity' => 30,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $this->actingAsApiUser($guru)->postJson('/api/agenda', [
            'judul' => 'Agenda uji',
            'tanggal' => now()->addDays(2)->toDateString(),
            'jam_mulai' => '09:00',
            'jam_selesai' => '10:00',
            'ruangan_id' => $room->id,
            'requester_id' => $otherGuru->id,
            'user_id' => $otherGuru->id,
        ])->assertCreated();

        $this->assertDatabaseHas('events', [
            'event_name' => 'Agenda uji',
            'requester_id' => $guru->id,
        ]);
        $this->assertDatabaseMissing('events', [
            'event_name' => 'Agenda uji',
            'requester_id' => $otherGuru->id,
        ]);
    }

    public function test_siswa_cannot_submit_an_agenda(): void
    {
        $siswa = User::factory()->create(['role' => 'siswa']);

        $this->actingAsApiUser($siswa)->postJson('/api/agenda', [])->assertForbidden();
    }

    public function test_complaint_creation_uses_authenticated_reporter_and_pic_scope(): void
    {
        $guru = User::factory()->create(['role' => 'guru']);
        $otherGuru = User::factory()->create(['role' => 'guru']);
        $pic = User::factory()->create(['role' => 'pic']);
        $otherPic = User::factory()->create(['role' => 'pic']);
        $room = Room::create([
            'room_name' => 'Ruang API', 'location' => 'Gedung A', 'capacity' => 24,
            'pic_id' => $pic->id, 'is_active' => true,
        ]);
        $otherRoom = Room::create([
            'room_name' => 'Ruang Lain', 'location' => 'Gedung B', 'capacity' => 24,
            'pic_id' => $otherPic->id, 'is_active' => true,
        ]);
        $otherComplaint = Complaint::create([
            'room_id' => $otherRoom->id, 'reporter_id' => $otherGuru->id,
            'description' => 'Milik ruang lain', 'reported_at' => now(),
        ]);

        $this->actingAsApiUser($guru)->postJson('/api/pengaduan', [
            'room_id' => $room->id,
            'description' => 'Kursi rusak',
            'reporter_id' => $otherGuru->id,
            'user_id' => $otherGuru->id,
        ])->assertCreated();

        $this->assertDatabaseHas('complaints', [
            'room_id' => $room->id,
            'reporter_id' => $guru->id,
            'description' => 'Kursi rusak',
        ]);

        $this->actingAsApiUser($pic)->getJson('/api/pengaduan')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.room_id', $room->id)
            ->assertJsonPath('data.0.id', Complaint::where('description', 'Kursi rusak')->value('id'));
    }

    public function test_pic_cannot_change_complaint_for_another_room(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);
        $otherPic = User::factory()->create(['role' => 'pic']);
        $guru = User::factory()->create(['role' => 'guru']);
        $room = Room::create([
            'room_name' => 'Ruang PIC Lain', 'location' => 'Gedung B', 'capacity' => 20,
            'pic_id' => $otherPic->id, 'is_active' => true,
        ]);
        $complaint = Complaint::create([
            'room_id' => $room->id, 'reporter_id' => $guru->id,
            'description' => 'Kerusakan di ruang lain', 'reported_at' => now(),
        ]);

        $this->actingAsApiUser($pic)->putJson('/api/pengaduan/' . $complaint->id . '/status', [
            'status_repair' => 'Sudah Diperbaiki',
        ])->assertForbidden();

        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status_repair' => 'Belum Diperbaiki',
            'handled_by' => null,
        ]);
    }

    public function test_users_can_only_read_and_mark_their_own_notifications(): void
    {
        $user = User::factory()->create(['role' => 'guru']);
        $otherUser = User::factory()->create(['role' => 'guru']);
        $own = Notification::create([
            'user_id' => $user->id, 'title' => 'Milik saya', 'message' => 'Pesan', 'is_read' => false,
        ]);
        $other = Notification::create([
            'user_id' => $otherUser->id, 'title' => 'Milik lain', 'message' => 'Rahasia', 'is_read' => false,
        ]);

        $this->actingAsApiUser($user)->getJson('/api/notifikasi?user_id=' . $otherUser->id)
            ->assertOk()
            ->assertJsonPath('data.complaint_notifications.0.id', $own->id);
        $this->putJson('/api/notifikasi/' . $other->id . '/read')->assertNotFound();
        $this->putJson('/api/notifikasi/' . $own->id . '/read')->assertOk();
        $this->assertDatabaseHas('notifications', ['id' => $own->id, 'is_read' => true]);
        $this->assertDatabaseHas('notifications', ['id' => $other->id, 'is_read' => false]);
    }

    public function test_complaint_photo_requires_supported_image_type_and_size(): void
    {
        Storage::fake('public');
        $guru = User::factory()->create(['role' => 'guru']);
        $pic = User::factory()->create(['role' => 'pic']);
        $room = Room::create([
            'room_name' => 'Ruang Foto', 'location' => 'Gedung A', 'capacity' => 20,
            'pic_id' => $pic->id, 'is_active' => true,
        ]);
        $payload = ['room_id' => $room->id, 'description' => 'Kerusakan foto'];

        $this->actingAsApiUser($guru)->post('/api/pengaduan', $payload + [
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertCreated();

        $this->actingAsApiUser($guru)->post('/api/pengaduan', $payload + [
            'photo' => UploadedFile::fake()->create('bukti.txt', 10, 'text/plain'),
        ])->assertUnprocessable();

        $this->actingAsApiUser($guru)->post('/api/pengaduan', $payload + [
            'photo' => UploadedFile::fake()->image('besar.jpg')->size(2049),
        ])->assertUnprocessable();

        $this->assertDatabaseCount('complaints', 1);
        $this->assertSame(1, count(Storage::disk('public')->allFiles('complaints')));
    }

    private function actingAsApiUser(User $user): static
    {
        $token = 'test_' . bin2hex(random_bytes(20));
        Cache::put('api_token_' . hash('sha256', $token), $user->id, now()->addHour());

        return $this->withToken($token);
    }
}
