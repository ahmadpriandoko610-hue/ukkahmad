<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserWhatsAppRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Utama',
            'email' => 'admin.utama@rapi.test',
        ]);
    }

    /**
     * 1. Role PIC dapat mengisi dan menyimpan nomor WhatsApp untuk pengaduan kerusakan via wa.me.
     */
    public function test_pic_role_can_save_and_update_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Budi PIC Sarpras',
            'email' => 'budi.pic@rapi.test',
            'password' => 'password123',
            'role' => 'pic',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'budi.pic@rapi.test',
            'role' => 'pic',
            'phone' => '6281234567890',
        ]);

        $pic = User::where('email', 'budi.pic@rapi.test')->firstOrFail();

        $updateResponse = $this->actingAs($this->admin)->put(route('users.update', $pic), [
            'name' => 'Budi PIC Diperbarui',
            'email' => 'budi.pic@rapi.test',
            'role' => 'pic',
            'phone' => '089876543210',
        ]);

        $updateResponse->assertRedirect(route('users.index'));
        $this->assertSame('6289876543210', $pic->fresh()->phone);
    }

    /**
     * 2. Role Admin tidak dapat menyimpan nomor WhatsApp.
     */
    public function test_admin_role_cannot_save_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Admin Tambahan',
            'email' => 'admin.tambahan@rapi.test',
            'password' => 'password123',
            'role' => 'admin',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'admin.tambahan@rapi.test',
            'role' => 'admin',
            'phone' => null,
        ]);

        $adminUser = User::where('email', 'admin.tambahan@rapi.test')->firstOrFail();
        $this->assertNull($adminUser->phone);

        $this->actingAs($this->admin)->put(route('users.update', $adminUser), [
            'name' => 'Admin Tambahan Edit',
            'email' => 'admin.tambahan@rapi.test',
            'role' => 'admin',
            'phone' => '081299999999',
        ]);

        $this->assertNull($adminUser->fresh()->phone);
    }

    /**
     * 3. Role Guru / Organisasi dapat menyimpan dan memperbarui nomor WhatsApp serta menolak format tidak valid.
     */
    public function test_guru_role_can_save_and_update_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Guru Matematika',
            'email' => 'guru.mtk@rapi.test',
            'password' => 'password123',
            'role' => 'guru',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'guru.mtk@rapi.test',
            'role' => 'guru',
            'phone' => '6281234567890',
        ]);

        $guru = User::where('email', 'guru.mtk@rapi.test')->firstOrFail();
        $this->assertSame('6281234567890', $guru->phone);

        $updateResponse = $this->actingAs($this->admin)->put(route('users.update', $guru), [
            'name' => 'Guru Matematika Edit',
            'email' => 'guru.mtk@rapi.test',
            'role' => 'guru',
            'phone' => '+62 812-8888-8888',
        ]);

        $updateResponse->assertRedirect(route('users.index'));
        $this->assertSame('6281288888888', $guru->fresh()->phone);
        $this->assertSame('6281288888888', User::normalizeWhatsAppPhone($guru->fresh()->phone));

        $invalidResponse = $this->actingAs($this->admin)
            ->from(route('users.edit', $guru))
            ->put(route('users.update', $guru), [
                'name' => 'Guru Matematika Edit',
                'email' => 'guru.mtk@rapi.test',
                'role' => 'guru',
                'phone' => 'bukan-nomor-08',
            ]);

        $invalidResponse->assertRedirect(route('users.edit', $guru));
        $invalidResponse->assertSessionHasErrors('phone');
        $this->assertSame('6281288888888', $guru->fresh()->phone);
    }

    /**
     * 4. Role Siswa tidak dapat menyimpan nomor WhatsApp.
     */
    public function test_siswa_role_cannot_save_whatsapp_number(): void
    {
        $response = $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Siswa RPL 1',
            'email' => 'siswa.rpl@rapi.test',
            'password' => 'password123',
            'role' => 'siswa',
            'phone' => '081234567890',
        ]);

        $response->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'siswa.rpl@rapi.test',
            'role' => 'siswa',
            'phone' => null,
        ]);

        $siswa = User::where('email', 'siswa.rpl@rapi.test')->firstOrFail();
        $this->assertNull($siswa->phone);

        $this->actingAs($this->admin)->put(route('users.update', $siswa), [
            'name' => 'Siswa RPL 1 Edit',
            'email' => 'siswa.rpl@rapi.test',
            'role' => 'siswa',
            'phone' => '081277777777',
        ]);

        $this->assertNull($siswa->fresh()->phone);
    }

    /**
     * 5. Mengubah role PIC/Guru menjadi Admin atau Siswa mengosongkan nomor WhatsApp.
     */
    public function test_changing_pic_or_guru_role_to_admin_or_siswa_clears_whatsapp_number(): void
    {
        $picToGuru = User::factory()->create([
            'role' => 'pic',
            'phone' => '081234567890',
        ]);
        $this->assertSame('081234567890', $picToGuru->phone);

        $this->actingAs($this->admin)->put(route('users.update', $picToGuru), [
            'name' => $picToGuru->name,
            'email' => $picToGuru->email,
            'role' => 'guru',
            'phone' => '081234567890',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('guru', $picToGuru->fresh()->role);
        $this->assertSame('6281234567890', $picToGuru->fresh()->phone);

        $picToAdmin = User::factory()->create([
            'role' => 'pic',
            'phone' => '081233333333',
        ]);
        $this->actingAs($this->admin)->put(route('users.update', $picToAdmin), [
            'name' => $picToAdmin->name,
            'email' => $picToAdmin->email,
            'role' => 'admin',
            'phone' => '081233333333',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('admin', $picToAdmin->fresh()->role);
        $this->assertNull($picToAdmin->fresh()->phone);

        $guruToSiswa = User::factory()->create([
            'role' => 'guru',
            'phone' => '081244444444',
        ]);
        $this->actingAs($this->admin)->put(route('users.update', $guruToSiswa), [
            'name' => $guruToSiswa->name,
            'email' => $guruToSiswa->email,
            'role' => 'siswa',
            'phone' => '081244444444',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('siswa', $guruToSiswa->fresh()->role);
        $this->assertNull($guruToSiswa->fresh()->phone);
    }

    /**
     * 6. Halaman Tambah User dan Edit User menampilkan kondisi input khusus Guru dan PIC tanpa fitur opt-in lama.
     */
    public function test_create_and_edit_user_page_displays_correct_input_condition(): void
    {
        $createResponse = $this->actingAs($this->admin)->get(route('users.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('placeholder="Khusus untuk Guru / Organisasi atau PIC"', false);
        $createResponse->assertSee('disabled', false);
        $createResponse->assertDontSeeText('persetujuan penerimaan notifikasi pemindahan ruangan');

        $picUser = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Khusus',
            'email' => 'pic.khusus@rapi.test',
            'phone' => '081234567890',
        ]);
        $editPicResponse = $this->actingAs($this->admin)->get(route('users.edit', $picUser));
        $editPicResponse->assertOk();
        $editPicResponse->assertSee('value="081234567890"', false);
        $editPicResponse->assertSee('placeholder="Contoh: 081234567890"', false);

        $guruUser = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru Biologi',
            'email' => 'guru.biologi@rapi.test',
            'phone' => '081345678901',
        ]);
        $editGuruResponse = $this->actingAs($this->admin)->get(route('users.edit', $guruUser));
        $editGuruResponse->assertOk();
        $editGuruResponse->assertSee('value="081345678901"', false);
        $editGuruResponse->assertSee('placeholder="Contoh: 081234567890"', false);
        $editGuruResponse->assertDontSeeText('Status Persetujuan Notifikasi WhatsApp Guru');

        $editAdminResponse = $this->actingAs($this->admin)->get(route('users.edit', $this->admin));
        $editAdminResponse->assertOk();
        $editAdminResponse->assertSee('placeholder="Khusus untuk Guru / Organisasi atau PIC"', false);
        $editAdminResponse->assertSee('disabled', false);

        $usersIndex = $this->actingAs($this->admin)->get(route('users.index'));
        $usersIndex->assertOk();
        $usersIndex->assertDontSeeText('Persetujuan Notifikasi WA');
        $usersIndex->assertDontSeeText('WhatsApp (Tersamar)');
    }

    /**
     * 7. Request langsung ke backend tidak dapat menyimpan phone untuk role Admin atau Siswa.
     */
    public function test_direct_http_request_cannot_bypass_whatsapp_rule_for_admin_or_siswa(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Direct Request Siswa',
            'email' => 'direct.siswa@rapi.test',
            'password' => 'secret123',
            'role' => 'siswa',
            'phone' => '081234567890',
        ]);

        $created = User::where('email', 'direct.siswa@rapi.test')->firstOrFail();
        $this->assertSame('siswa', $created->role);
        $this->assertNull($created->phone);

        $this->actingAs($this->admin)->put(route('users.update', $created), [
            'name' => 'Direct Request Siswa Updated',
            'email' => 'direct.siswa@rapi.test',
            'role' => 'siswa',
            'phone' => '081999999999',
        ]);

        $this->assertNull($created->fresh()->phone);

        $directAdmin = User::factory()->create([
            'role' => 'admin',
            'phone' => '081299998888',
        ]);
        $this->assertNull($directAdmin->phone);
    }

    /**
     * 8. Fitur peringatan WhatsApp pengaduan kerusakan ke PIC via wa.me tetap berfungsi normal.
     */
    public function test_pic_complaint_whatsapp_warning_redirects_to_wa_me_link(): void
    {
        $pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Bengkel',
            'phone' => '081234567890',
        ]);

        $guru = User::factory()->create(['role' => 'guru']);
        $room = Room::create([
            'room_name' => 'Bengkel RPL',
            'location' => 'Gedung D',
            'capacity' => 36,
            'pic_id' => $pic->id,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $guru->id,
            'description' => 'Stop kontak meja komputer rusak.',
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        $indexResponse = $this->actingAs($this->admin)->get(route('admin.complaints'));
        $indexResponse->assertOk();
        $indexResponse->assertSee(route('complaint.whatsapp', $complaint->id));
        $indexResponse->assertSeeText('Kirim WhatsApp ke PIC');

        $waResponse = $this->actingAs($this->admin)->post(route('complaint.whatsapp', $complaint));
        $waResponse->assertRedirect();

        $targetUrl = (string) $waResponse->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $targetUrl);
        $this->assertStringContainsString(rawurlencode('Bengkel RPL'), $targetUrl);
    }

    /**
     * 9. Penyimpanan ternormalisasi 628xxxxxxxxxx menjamin aman terhadap VARCHAR(20) dan kompatibel dengan edit & pengaduan PIC.
     */
    public function test_phone_normalization_handles_all_formats_respects_varchar20_and_works_on_edit_and_pic_complaints(): void
    {
        $validFormats = [
            '08' => ['input' => '081234567890', 'expected' => '6281234567890'],
            '+628' => ['input' => '+6281398765432', 'expected' => '6281398765432'],
            '628' => ['input' => '6285712345678', 'expected' => '6285712345678'],
            'spaces_and_punctuation_21_chars' => [
                'input' => '+62 (812) 3456-7890-1',
                'expected' => '62812345678901',
            ],
            'dots_and_hyphens' => [
                'input' => '0812.3456-7890',
                'expected' => '6281234567890',
            ],
        ];

        foreach ($validFormats as $key => $case) {
            $email = "guru.{$key}@rapi.test";
            $this->actingAs($this->admin)->post(route('users.store'), [
                'name' => "Guru {$key}",
                'email' => $email,
                'password' => 'password123',
                'role' => 'guru',
                'phone' => $case['input'],
            ])->assertRedirect(route('users.index'));

            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame($case['expected'], $user->phone);
            $this->assertLessThanOrEqual(20, strlen((string) $user->phone));

            // Nomor ternormalisasi tampil benar di form edit & dapat disimpan kembali tanpa error
            $editResponse = $this->actingAs($this->admin)->get(route('users.edit', $user));
            $editResponse->assertOk();
            $editResponse->assertSee('value="' . $case['expected'] . '"', false);

            $this->actingAs($this->admin)->put(route('users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'guru',
                'phone' => $user->phone,
            ])->assertRedirect(route('users.index'));

            $this->assertSame($case['expected'], $user->fresh()->phone);
        }

        // Nomor kosong -> tersimpan null
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Guru Tanpa Nomor',
            'email' => 'guru.kosong@rapi.test',
            'password' => 'password123',
            'role' => 'guru',
            'phone' => '   ',
        ])->assertRedirect(route('users.index'));

        $emptyPhoneUser = User::where('email', 'guru.kosong@rapi.test')->firstOrFail();
        $this->assertNull($emptyPhoneUser->phone);

        // Nomor terlalu panjang (>25 karakter raw maupun >15 digit saat normalisasi) & nomor tidak valid wajib ditolak
        $invalidNumbers = [
            'too_long_raw' => '+62 (812) 3456-7890-123456',
            'too_long_digits' => '08123456789012345',
            'too_long_62_digits' => '6281234567890123456',
            'too_short' => '08123',
            'landline' => '0215551234',
            'plus620' => '+6208123456789',
            'alpha' => '0812abcd7890',
        ];

        foreach ($invalidNumbers as $label => $badPhone) {
            $failStore = $this->actingAs($this->admin)
                ->from(route('users.create'))
                ->post(route('users.store'), [
                    'name' => "Invalid {$label}",
                    'email' => "invalid.{$label}@rapi.test",
                    'password' => 'password123',
                    'role' => 'guru',
                    'phone' => $badPhone,
                ]);

            $failStore->assertRedirect(route('users.create'));
            $failStore->assertSessionHasErrors('phone');
            $this->assertDatabaseMissing('users', ['email' => "invalid.{$label}@rapi.test"]);

            $failUpdate = $this->actingAs($this->admin)
                ->from(route('users.edit', $emptyPhoneUser))
                ->put(route('users.update', $emptyPhoneUser), [
                    'name' => $emptyPhoneUser->name,
                    'email' => $emptyPhoneUser->email,
                    'role' => 'guru',
                    'phone' => $badPhone,
                ]);

            $failUpdate->assertRedirect(route('users.edit', $emptyPhoneUser));
            $failUpdate->assertSessionHasErrors('phone');
            $this->assertNull($emptyPhoneUser->fresh()->phone);
        }

        // Kompatibilitas nomor PIC pengaduan setelah normalisasi server (input berformat spasi/tanda baca -> tersimpan 628...)
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'PIC Lab Normalized',
            'email' => 'pic.normalized@rapi.test',
            'password' => 'password123',
            'role' => 'pic',
            'phone' => '+62 812-9876-5432-1',
        ])->assertRedirect(route('users.index'));

        $normalizedPic = User::where('email', 'pic.normalized@rapi.test')->firstOrFail();
        $this->assertSame('62812987654321', $normalizedPic->phone);
        $this->assertLessThanOrEqual(20, strlen((string) $normalizedPic->phone));

        $reporter = User::factory()->create(['role' => 'guru']);
        $room = Room::create([
            'room_name' => 'Lab Multimedia',
            'location' => 'Gedung E',
            'capacity' => 32,
            'pic_id' => $normalizedPic->id,
            'is_active' => true,
        ]);

        $complaint = Complaint::create([
            'room_id' => $room->id,
            'reporter_id' => $reporter->id,
            'description' => 'Proyektor tidak menyala.',
            'status_repair' => 'Belum Diperbaiki',
            'reported_at' => now(),
        ]);

        $waPicResponse = $this->actingAs($this->admin)->post(route('complaint.whatsapp', $complaint));
        $waPicResponse->assertRedirect();
        $this->assertStringStartsWith(
            'https://wa.me/62812987654321?text=',
            (string) $waPicResponse->headers->get('Location')
        );
    }
}
