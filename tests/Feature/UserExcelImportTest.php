<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UserExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guru;
    private User $pic;
    private User $siswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin RAPI',
            'email' => 'admin@rapi.sch.id',
        ]);

        $this->guru = User::factory()->create([
            'role' => 'guru',
            'name' => 'Guru RAPI',
            'email' => 'guru@rapi.sch.id',
        ]);

        $this->pic = User::factory()->create([
            'role' => 'pic',
            'name' => 'PIC Ruang',
            'email' => 'pic@rapi.sch.id',
        ]);

        $this->siswa = User::factory()->create([
            'role' => 'siswa',
            'name' => 'Siswa RAPI',
            'email' => 'siswa@rapi.sch.id',
        ]);
    }

    /**
     * Helper untuk membuat file Excel sementara dalam pengujian.
     */
    private function createTemporaryExcelFile(array $rows, array $headers = ['No', 'Nama', 'Email', 'Role']): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Tulis header
        foreach ($headers as $colIdx => $h) {
            $col = chr(65 + $colIdx);
            $sheet->setCellValue("{$col}1", $h);
        }

        // Tulis data baris
        foreach ($rows as $rowIdx => $row) {
            $r = $rowIdx + 2;
            foreach ($row as $colIdx => $val) {
                $col = chr(65 + $colIdx);
                $sheet->setCellValue("{$col}{$r}", $val);
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return $tempPath;
    }

    /**
     * Uji 1: Batasan Hak Akses (Hanya Admin yang dapat mengakses fitur import).
     */
    public function test_non_admin_cannot_access_import_routes(): void
    {
        // Tamu (belum login)
        $this->get(route('users.import.form'))->assertRedirect(route('login'));
        $this->get(route('users.import.template'))->assertRedirect(route('login'));

        // Role Guru
        $this->actingAs($this->guru)->get(route('users.import.form'))->assertForbidden();
        $this->actingAs($this->guru)->get(route('users.import.template'))->assertForbidden();

        // Role PIC
        $this->actingAs($this->pic)->get(route('users.import.form'))->assertForbidden();

        // Role Siswa
        $this->actingAs($this->siswa)->get(route('users.import.form'))->assertForbidden();

        // Role Admin dapat mengakses form
        $response = $this->actingAs($this->admin)->get(route('users.import.form'));
        $response->assertOk();
        $response->assertSee('Import Pengguna Massal via Excel');
        $response->assertSee(route('users.import.template'));
    }

    /**
     * Uji 2: Admin dapat mengunduh berkas template Excel (.xlsx).
     */
    public function test_admin_can_download_excel_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.import.template'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('Template_Import_Pengguna_RAPI_Sekolah.xlsx', $response->headers->get('content-disposition'));
    }

    /**
     * Uji 3: Pratinjau berkas Excel dengan data valid.
     */
    public function test_preview_valid_excel_file(): void
    {
        $excelPath = $this->createTemporaryExcelFile([
            [1, 'Pak Joko Susilo', 'joko.susilo@rapi.sch.id', 'guru'],
            [2, 'Ibu Sri Handayani', 'sri.handayani@rapi.sch.id', 'pic'],
            [3, 'Muhammad Fatih', 'fatih@rapi.sch.id', 'siswa'],
        ]);

        $uploadedFile = new UploadedFile($excelPath, 'data_pengguna.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->admin)->post(route('users.import.preview'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect(route('users.import.form'));
        $response->assertSessionHas('user_import_preview');

        $preview = session('user_import_preview');
        $this->assertTrue($preview['success']);
        $this->assertEquals(3, $preview['total_rows']);
        $this->assertEquals(3, $preview['valid_count']);
        $this->assertEquals(0, $preview['invalid_count']);

        // Buka form untuk melihat render tabel preview
        $formResponse = $this->actingAs($this->admin)->get(route('users.import.form'));
        $formResponse->assertOk();
        $formResponse->assertSee('Pak Joko Susilo');
        $formResponse->assertSee('joko.susilo@rapi.sch.id');
        $formResponse->assertSee('Ibu Sri Handayani');
        $formResponse->assertSee('Muhammad Fatih');
        $formResponse->assertSee('Data Valid (Siap Impor)');

        @unlink($excelPath);
    }

    /**
     * Uji 4: Pratinjau menandai baris yang bermasalah (role tidak valid, email duplikat di DB & file, format salah).
     */
    public function test_preview_flags_invalid_rows_correctly(): void
    {
        $excelPath = $this->createTemporaryExcelFile([
            [1, 'Valid User', 'valid.new@rapi.sch.id', 'guru'],
            [2, '', 'empty.name@rapi.sch.id', 'guru'], // Nama kosong
            [3, 'Invalid Email Format', 'bukan-email-valid', 'pic'], // Email tidak valid
            [4, 'Duplicate In DB', 'admin@rapi.sch.id', 'admin'], // Email sudah ada di DB
            [5, 'Duplicate In File 1', 'duplikat@rapi.sch.id', 'guru'],
            [6, 'Duplicate In File 2', 'duplikat@rapi.sch.id', 'siswa'], // Duplikat di file
            [7, 'Invalid Role', 'unknown.role@rapi.sch.id', 'superadmin'], // Role tidak dikenal
        ]);

        $uploadedFile = new UploadedFile($excelPath, 'data_campuran.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->admin)->post(route('users.import.preview'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect(route('users.import.form'));
        $preview = session('user_import_preview');

        $this->assertTrue($preview['success']);
        $this->assertEquals(7, $preview['total_rows']);
        $this->assertEquals(1, $preview['valid_count']); // Hanya baris 1
        $this->assertEquals(6, $preview['invalid_count']);

        // Verifikasi error spesifik
        $rows = $preview['rows'];
        $this->assertTrue($rows[0]['is_valid']);

        $this->assertFalse($rows[1]['is_valid']);
        $this->assertContains('Nama wajib diisi.', $rows[1]['errors']);

        $this->assertFalse($rows[2]['is_valid']);
        $this->assertContains('Format email tidak valid.', $rows[2]['errors']);

        $this->assertFalse($rows[3]['is_valid']);
        $this->assertContains('Email sudah terdaftar di database sistem.', $rows[3]['errors']);

        $this->assertFalse($rows[5]['is_valid']);
        $this->assertTrue(collect($rows[5]['errors'])->contains(fn($err) => str_contains($err, 'Email duplikat di dalam berkas Excel')));

        $this->assertFalse($rows[6]['is_valid']);
        $this->assertTrue(collect($rows[6]['errors'])->contains(fn($err) => str_contains($err, 'tidak valid')));

        @unlink($excelPath);
    }

    /**
     * Uji 5: Eksekusi impor hanya memasukkan baris yang valid ke database MySQL dan akun dapat login.
     */
    public function test_import_stores_valid_users_with_secure_password(): void
    {
        $previewData = [
            'success' => true,
            'message' => 'Data diperiksa.',
            'total_rows' => 3,
            'valid_count' => 2,
            'invalid_count' => 1,
            'duplicate_count' => 0,
            'rows' => [
                [
                    'row_number' => 2,
                    'no' => 1,
                    'name' => 'Akun Baru Pertama',
                    'email' => 'pertama.user@rapi.sch.id',
                    'role' => 'guru',
                    'is_valid' => true,
                    'errors' => [],
                ],
                [
                    'row_number' => 3,
                    'no' => 2,
                    'name' => 'Akun Baru Kedua',
                    'email' => 'kedua.user@rapi.sch.id',
                    'role' => 'siswa',
                    'is_valid' => true,
                    'errors' => [],
                ],
                [
                    'row_number' => 4,
                    'no' => 3,
                    'name' => 'Akun Gagal',
                    'email' => 'admin@rapi.sch.id',
                    'role' => 'admin',
                    'is_valid' => false,
                    'errors' => ['Email sudah terdaftar.'],
                ],
            ],
        ];

        session(['user_import_preview' => $previewData]);

        $response = $this->actingAs($this->admin)->post(route('users.import.store'));

        $response->assertRedirect(route('users.import.form'));
        $response->assertSessionHas('success');
        $response->assertSessionHas('import_report');

        // Pastikan session preview sudah dibersihkan
        $this->assertNull(session('user_import_preview'));

        // Cek database
        $this->assertDatabaseHas('users', [
            'name' => 'Akun Baru Pertama',
            'email' => 'pertama.user@rapi.sch.id',
            'role' => 'guru',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Akun Baru Kedua',
            'email' => 'kedua.user@rapi.sch.id',
            'role' => 'siswa',
        ]);

        // Verifikasi akun baru dapat melakukan login dengan password sementara yang digenerate
        $userPertama = User::where('email', 'pertama.user@rapi.sch.id')->firstOrFail();
        $expectedPassword = 'Pertamauser@Rapi' . date('Y');

        $this->assertTrue(Hash::check($expectedPassword, $userPertama->password));

        // Uji login langsung via Auth
        Auth::logout();
        $loginSuccess = Auth::attempt([
            'email' => 'pertama.user@rapi.sch.id',
            'password' => $expectedPassword,
        ]);
        $this->assertTrue($loginSuccess);
    }

    /**
     * Uji 6: Header berkas yang salah atau file kosong ditolak dengan pesan yang jelas.
     */
    public function test_invalid_header_or_empty_file_is_rejected(): void
    {
        // Header salah (misal: A, B, C tanpa No, Nama, Email, Role)
        $excelPath = $this->createTemporaryExcelFile([
            ['Data 1', 'Data 2', 'Data 3'],
        ], ['KolomA', 'KolomB', 'KolomC']);

        $uploadedFile = new UploadedFile($excelPath, 'salah_header.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->admin)->post(route('users.import.preview'), [
            'file' => $uploadedFile,
        ]);

        $response->assertRedirect(route('users.import.form'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Header kolom Excel tidak sesuai template', session('error'));

        @unlink($excelPath);
    }

    /**
     * Uji 7: Tombol Import Pengguna Excel terpasang pada halaman Daftar User (users.index).
     */
    public function test_users_index_page_contains_import_button(): void
    {
        $response = $this->actingAs($this->admin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Import Pengguna Excel');
        $response->assertSee(route('users.import.form'));
        $response->assertSee(route('users.create')); // Tombol tambah manual tetap ada
    }
}

