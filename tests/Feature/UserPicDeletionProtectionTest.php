<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPicDeletionProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Sekolah',
            'email' => 'admin@rapi.test',
        ]);
    }

    public function test_pic_with_assigned_room_cannot_be_deleted(): void
    {
        $pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Budi PIC',
            'email' => 'budi.pic@rapi.test',
            'phone' => '081234567890',
        ]);

        $room = Room::create([
            'room_name' => 'Lab Komputer 1',
            'location' => 'Lantai 2',
            'capacity' => 36,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $pic));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas(
            'error',
            'PIC tidak dapat dihapus karena masih memiliki tanggung jawab ruangan (Lab Komputer 1). Silakan ubah atau lepaskan penanggung jawab ruangan terlebih dahulu.'
        );

        $this->assertDatabaseHas('users', [
            'id' => $pic->id,
            'role' => 'pic',
        ]);
        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'pic_id' => $pic->id,
        ]);

        $indexResponse = $this->actingAs($this->admin)->get(route('users.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSeeText('PJ Ruangan: Lab Komputer 1');
        $indexResponse->assertSee('href="' . route('users.edit', $pic->id) . '"', false);
        $indexResponse->assertDontSee('action="' . route('users.destroy', $pic->id) . '"', false);
    }

    public function test_users_index_hides_delete_button_only_for_pic_with_room_responsibility(): void
    {
        $picWithRoom = User::factory()->create([
            'role' => 'pic',
            'name' => 'Alpha PIC Ruangan',
            'email' => 'alpha.pic@rapi.test',
        ]);

        $picWithoutRoom = User::factory()->create([
            'role' => 'pic',
            'name' => 'Beta PIC Cadangan',
            'email' => 'beta.pic@rapi.test',
        ]);

        $guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Charlie Guru',
            'email' => 'charlie.guru@rapi.test',
        ]);

        $room = Room::create([
            'room_name' => 'Lab Jaringan',
            'location' => 'Lantai 3',
            'capacity' => 30,
            'pic_id' => $picWithRoom->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('users.index'));
        $response->assertOk();

        // PIC dengan tanggung jawab ruangan tidak memiliki tombol Hapus, namun Edit tetap ada
        $response->assertSee('href="' . route('users.edit', $picWithRoom->id) . '"', false);
        $response->assertDontSee('action="' . route('users.destroy', $picWithRoom->id) . '"', false);
        $response->assertSeeText('PJ Ruangan: Lab Jaringan');

        // PIC tanpa tanggung jawab ruangan & user lain tetap memiliki tombol Hapus
        $response->assertSee('action="' . route('users.destroy', $picWithoutRoom->id) . '"', false);
        $response->assertSee('action="' . route('users.destroy', $guru->id) . '"', false);

        // Setelah tanggung jawab ruangan dialihkan ke PIC lain, tombol Hapus berpindah mengikuti status tanggung jawab
        $this->actingAs($this->admin)->put(route('rooms.update', $room), [
            'room_name' => $room->room_name,
            'location' => $room->location,
            'capacity' => $room->capacity,
            'pic_id' => $picWithoutRoom->id,
            'description' => $room->description,
        ])->assertRedirect(route('rooms.index'));

        $afterReassign = $this->actingAs($this->admin)->get(route('users.index'));
        $afterReassign->assertOk();
        $afterReassign->assertSee('action="' . route('users.destroy', $picWithRoom->id) . '"', false);
        $afterReassign->assertDontSee('action="' . route('users.destroy', $picWithoutRoom->id) . '"', false);
    }

    public function test_pic_cannot_be_deleted_when_still_responsible_for_any_remaining_room(): void
    {
        $pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Siti PIC',
            'email' => 'siti.pic@rapi.test',
        ]);

        $otherPic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Andi PIC',
            'email' => 'andi.pic@rapi.test',
        ]);

        $roomA = Room::create([
            'room_name' => 'Aula Utama',
            'location' => 'Gedung A',
            'capacity' => 120,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $roomB = Room::create([
            'room_name' => 'Studio Multimedia',
            'location' => 'Gedung B',
            'capacity' => 30,
            'pic_id' => $pic->id,
            'is_active' => false,
        ]);

        // Baru satu ruangan yang dialihkan ke PIC lain; satu ruangan tersisa (meski nonaktif)
        $roomA->update(['pic_id' => $otherPic->id]);

        $response = $this->actingAs($this->admin)->delete(route('users.destroy', $pic));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas(
            'error',
            'PIC tidak dapat dihapus karena masih memiliki tanggung jawab ruangan (Studio Multimedia). Silakan ubah atau lepaskan penanggung jawab ruangan terlebih dahulu.'
        );
        $this->assertDatabaseHas('users', ['id' => $pic->id]);
        $this->assertDatabaseHas('rooms', ['id' => $roomB->id, 'pic_id' => $pic->id]);
    }

    public function test_pic_can_be_deleted_after_room_responsibility_is_reassigned_or_removed(): void
    {
        $pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Rudi PIC',
            'email' => 'rudi.pic@rapi.test',
        ]);

        $replacementPic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Dewi PIC',
            'email' => 'dewi.pic@rapi.test',
        ]);

        $room1 = Room::create([
            'room_name' => 'Lab Bahasa',
            'location' => 'Gedung C',
            'capacity' => 32,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $room2 = Room::create([
            'room_name' => 'Ruang Rapat',
            'location' => 'Gedung A',
            'capacity' => 20,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        // 1. Alihkan room1 ke PIC pengganti dan lepaskan PIC pada room2
        $this->actingAs($this->admin)->put(route('rooms.update', $room1), [
            'room_name' => $room1->room_name,
            'location' => $room1->location,
            'capacity' => $room1->capacity,
            'pic_id' => $replacementPic->id,
            'description' => $room1->description,
        ])->assertRedirect(route('rooms.index'));

        $this->actingAs($this->admin)->put(route('rooms.update', $room2), [
            'room_name' => $room2->room_name,
            'location' => $room2->location,
            'capacity' => $room2->capacity,
            'pic_id' => '',
            'description' => $room2->description,
        ])->assertRedirect(route('rooms.index'));

        // 2. Setelah tidak ada tanggung jawab ruangan, PIC dapat dihapus
        $deleteResponse = $this->actingAs($this->admin)->delete(route('users.destroy', $pic));

        $deleteResponse->assertRedirect(route('users.index'));
        $deleteResponse->assertSessionHas('success', 'User berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $pic->id]);
        $this->assertDatabaseHas('rooms', ['id' => $room1->id, 'pic_id' => $replacementPic->id]);
        $this->assertDatabaseHas('rooms', ['id' => $room2->id, 'pic_id' => null]);
    }

    public function test_pic_without_rooms_and_other_roles_can_still_be_deleted(): void
    {
        $picWithoutRoom = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Cadangan',
        ]);

        $guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Fisika',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $picWithoutRoom))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'User berhasil dihapus.');

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $guru))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'User berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $picWithoutRoom->id]);
        $this->assertDatabaseMissing('users', ['id' => $guru->id]);
    }

    public function test_pic_with_assigned_room_cannot_change_role_to_non_pic_until_unassigned(): void
    {
        $pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Hendra PIC',
            'email' => 'hendra.pic@rapi.test',
            'phone' => '081234567890',
        ]);

        $room = Room::create([
            'room_name' => 'Bengkel TKJ',
            'location' => 'Gedung D',
            'capacity' => 36,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        // Tetap bisa memperbarui nama/nomor telepon selama role tetap pic
        $this->actingAs($this->admin)
            ->put(route('users.update', $pic), [
                'name' => 'Hendra PIC Baru',
                'email' => 'hendra.pic@rapi.test',
                'role' => 'pic',
                'phone' => '081299990000',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertSame('Hendra PIC Baru', $pic->fresh()->name);
        $this->assertSame('081299990000', $pic->fresh()->phone);

        // Mengubah role menjadi guru saat masih memegang Bengkel TKJ harus ditolak
        $blockedRoleChange = $this->actingAs($this->admin)
            ->from(route('users.edit', $pic))
            ->put(route('users.update', $pic), [
                'name' => 'Hendra PIC Baru',
                'email' => 'hendra.pic@rapi.test',
                'role' => 'guru',
            ]);

        $blockedRoleChange->assertRedirect(route('users.edit', $pic));
        $blockedRoleChange->assertSessionHasErrors('role');

        $this->assertSame('pic', $pic->fresh()->role);
        $this->assertSame('081299990000', $pic->fresh()->phone);

        // Lepaskan PIC dari ruangan -> sekarang role boleh diubah
        $room->update(['pic_id' => null]);

        $this->actingAs($this->admin)
            ->put(route('users.update', $pic), [
                'name' => 'Hendra Guru',
                'email' => 'hendra.pic@rapi.test',
                'role' => 'guru',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertSame('guru', $pic->fresh()->role);
        $this->assertNull($pic->fresh()->phone);
    }

    public function test_users_index_displays_error_alert_when_deletion_is_blocked(): void
    {
        $pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'Tono PIC',
        ]);

        Room::create([
            'room_name' => 'Lab Kimia',
            'location' => 'Lantai 1',
            'capacity' => 30,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->followingRedirects()
            ->delete(route('users.destroy', $pic));

        $response->assertOk();
        $response->assertSee('alert-danger', false);
        $response->assertSeeText('PIC tidak dapat dihapus karena masih memiliki tanggung jawab ruangan (Lab Kimia).');
    }
}

