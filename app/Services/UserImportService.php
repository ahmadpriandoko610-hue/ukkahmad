<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class UserImportService
{
    /**
     * Role yang diizinkan pada sistem RAPI-Sekolah.
     * Mengikuti enum role pada tabel users: admin, pic, guru, siswa.
     */
    public const ALLOWED_ROLES = ['admin', 'pic', 'guru', 'siswa'];

    /**
     * Label resmi role untuk panduan tampilan.
     */
    public const ROLE_LABELS = [
        'guru' => 'Guru / Organisasi',
        'pic' => 'PIC Ruang & Fasilitas',
        'siswa' => 'Siswa',
        'admin' => 'Administrator',
    ];

    /**
     * Membuat dan menghasilkan berkas template Excel (.xlsx).
     *
     * @return string Konten binary dari berkas .xlsx
     */
    public function generateTemplate(): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('RAPI-Sekolah')
            ->setTitle('Template Import Pengguna RAPI-Sekolah')
            ->setSubject('Template Import Pengguna')
            ->setDescription('Template Excel resmi untuk menambahkan pengguna baru secara massal pada RAPI-Sekolah.');

        // -------------------------------------------------------------
        // SHEET 1: Data Pengguna
        // -------------------------------------------------------------
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Pengguna');

        // Header Kolom
        $headers = ['No', 'Nama', 'Email', 'Role'];
        foreach ($headers as $colIdx => $header) {
            $colLetter = chr(65 + $colIdx);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        // Styling Header
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'], // Biru RAPI
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '1E3A8A'],
                ],
            ],
        ];
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Contoh Pengisian Data
        $sampleData = [
            [1, 'Ahmad Priandoko', 'ahmad@gmail.com', 'guru'],
            [2, 'Budi Santoso', 'budi@gmail.com', 'pic'],
            [3, 'Siti Aminah', 'siti@gmail.com', 'siswa'],
        ];

        foreach ($sampleData as $idx => $row) {
            $rowNum = $idx + 2;
            $sheet->setCellValueExplicit("A{$rowNum}", $row[0], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("B{$rowNum}", $row[1], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$rowNum}", $row[2], DataType::TYPE_STRING); // Email teks eksplisit
            $sheet->setCellValueExplicit("D{$rowNum}", $row[3], DataType::TYPE_STRING);

            $rowStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            $sheet->getStyle("A{$rowNum}:D{$rowNum}")->applyFromArray($rowStyle);
            $sheet->getStyle("A{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($rowNum)->setRowHeight(22);
        }

        // Lebar Kolom Otomatis yang Nyaman
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(34);
        $sheet->getColumnDimension('D')->setWidth(18);

        // -------------------------------------------------------------
        // SHEET 2: Petunjuk Pengisian
        // -------------------------------------------------------------
        $guideSheet = $spreadsheet->createSheet();
        $guideSheet->setTitle('Petunjuk Pengisian');

        $guideSheet->setCellValue('A1', 'PANDUAN PENGISIAN IMPORT PENGGUNA RAPI-SEKOLAH');
        $guideSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1E40AF');

        $instructions = [
            ['1.', 'Kolom Header', 'DILARANG mengubah nama kolom baris pertama (No, Nama, Email, Role) pada sheet "Data Pengguna".'],
            ['2.', 'Contoh Data', 'Hapus baris contoh (baris 2 s/d 4) sebelum mengisi data pengguna baru yang sebenarnya.'],
            ['3.', 'Kolom No', 'Nomor urut (angka 1, 2, 3, dst).'],
            ['4.', 'Kolom Nama', 'Nama lengkap pengguna (wajib diisi, maksimal 255 karakter).'],
            ['5.', 'Kolom Email', 'Alamat email aktif (wajib diisi, format email valid, tidak boleh duplikat, dan belum terdaftar di sistem).'],
            ['6.', 'Kolom Role', 'Peran akun pengguna di sistem. Wajib diisi salah satu dari pilihan role berikut:'],
            ['', '', '• guru  : Guru / Pembina Organisasi'],
            ['', '', '• pic   : Penanggung Jawab Ruangan & Fasilitas'],
            ['', '', '• siswa : Siswa Sekolah'],
            ['', '', '• admin : Administrator Sistem'],
            ['7.', 'Password Akun', 'Password akun baru akan dibuatkan secara otomatis dan aman dengan format unik. Pengguna dapat login dan mengganti password setelahnya.'],
        ];

        $r = 3;
        foreach ($instructions as $inst) {
            $guideSheet->setCellValue("A{$r}", $inst[0]);
            $guideSheet->setCellValue("B{$r}", $inst[1]);
            $guideSheet->setCellValue("C{$r}", $inst[2]);
            if ($inst[0] !== '') {
                $guideSheet->getStyle("A{$r}:B{$r}")->getFont()->setBold(true);
            }
            $guideSheet->getRowDimension($r)->setRowHeight(20);
            $r++;
        }

        $guideSheet->getColumnDimension('A')->setWidth(6);
        $guideSheet->getColumnDimension('B')->setWidth(20);
        $guideSheet->getColumnDimension('C')->setWidth(75);

        // Kembalikan ke Sheet 1 sebagai sheet aktif
        $spreadsheet->setActiveSheetIndex(0);

        // Export ke buffer
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return $content ?: '';
    }

    /**
     * Membaca dan memvalidasi file Excel / CSV yang diunggah.
     *
     * @param string $filePath Path file fisik di server
     * @return array Hasil validasi data preview
     */
    public function parseAndValidate(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException('Berkas Excel tidak ditemukan di server.');
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rawRows = $sheet->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal membaca format berkas. Pastikan format berkas adalah Excel (.xlsx) atau CSV valid: ' . $e->getMessage());
        }

        if (empty($rawRows)) {
            return [
                'success' => false,
                'message' => 'Berkas Excel kosong atau tidak memiliki data.',
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 0,
                'duplicate_count' => 0,
                'rows' => [],
            ];
        }

        // Periksa Header pada Baris Pertama
        $headerRow = array_shift($rawRows);
        $headerMap = $this->mapHeaders($headerRow);

        if ($headerMap === null) {
            return [
                'success' => false,
                'message' => 'Header kolom Excel tidak sesuai template. Pastikan terdapat kolom: No, Nama, Email, Role.',
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 0,
                'duplicate_count' => 0,
                'rows' => [],
            ];
        }

        // Ambil seluruh email yang sudah ada di database (case-insensitive)
        $existingEmails = User::pluck('email')
            ->map(fn($e) => strtolower(trim($e)))
            ->filter()
            ->flip()
            ->toArray();

        // Pra-pemindaian untuk mendeteksi seluruh kemunculan email di dalam berkas
        $emailOccurrences = [];
        $scanRowNum = 1;
        foreach ($rawRows as $scanRow) {
            $scanRowNum++;
            $scanEmail = strtolower(trim((string) ($scanRow[$headerMap['email']] ?? '')));
            if ($scanEmail !== '' && filter_var($scanEmail, FILTER_VALIDATE_EMAIL)) {
                $emailOccurrences[$scanEmail][] = $scanRowNum;
            }
        }

        $rowsData = [];
        $validCount = 0;
        $invalidCount = 0;
        $duplicateCount = 0;

        $excelRowNumber = 1; // baris 1 adalah header

        foreach ($rawRows as $row) {
            $excelRowNumber++;

            $rawNo = trim((string) ($row[$headerMap['no']] ?? ''));
            $rawName = trim((string) ($row[$headerMap['nama']] ?? ''));
            $rawEmail = trim((string) ($row[$headerMap['email']] ?? ''));
            $rawRole = trim((string) ($row[$headerMap['role']] ?? ''));

            // Abaikan baris kosong total
            if ($rawName === '' && $rawEmail === '' && $rawRole === '') {
                continue;
            }

            $errors = [];
            $normalizedEmail = strtolower($rawEmail);
            $normalizedRole = strtolower($rawRole);

            // Validasi Nama
            if ($rawName === '') {
                $errors[] = 'Nama wajib diisi.';
            } elseif (mb_strlen($rawName) > 255) {
                $errors[] = 'Nama maksimal 255 karakter.';
            }

            // Validasi Email
            $isDuplicate = false;
            if ($rawEmail === '') {
                $errors[] = 'Email wajib diisi.';
            } elseif (!filter_var($rawEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Format email tidak valid.';
            } else {
                // Duplikat di dalam file yang sama (semua baris yang memakai email sama diberi peringatan)
                if (isset($emailOccurrences[$normalizedEmail]) && count($emailOccurrences[$normalizedEmail]) > 1) {
                    $otherRows = array_values(array_filter($emailOccurrences[$normalizedEmail], fn($r) => $r !== $excelRowNumber));
                    $errors[] = 'Email duplikat di dalam berkas Excel (juga ditemukan pada baris ke-' . implode(', ', $otherRows) . ').';
                    $isDuplicate = true;
                }

                // Duplikat sudah terdaftar di database
                if (isset($existingEmails[$normalizedEmail])) {
                    $errors[] = 'Email sudah terdaftar di database sistem.';
                    $isDuplicate = true;
                }
            }

            if ($isDuplicate) {
                $duplicateCount++;
            }

            // Validasi Role
            if ($rawRole === '') {
                $errors[] = 'Role wajib diisi.';
            } elseif (!in_array($normalizedRole, self::ALLOWED_ROLES, true)) {
                $allowedList = implode(', ', self::ALLOWED_ROLES);
                $errors[] = "Role '{$rawRole}' tidak valid. Pilihan role yang tersedia: {$allowedList}.";
            }

            $isValid = empty($errors);
            if ($isValid) {
                $validCount++;
            } else {
                $invalidCount++;
            }

            $rowsData[] = [
                'row_number' => $excelRowNumber,
                'no' => $rawNo ?: ($excelRowNumber - 1),
                'name' => $rawName,
                'email' => $rawEmail,
                'role' => $normalizedRole,
                'role_label' => self::ROLE_LABELS[$normalizedRole] ?? ucfirst($rawRole),
                'is_valid' => $isValid,
                'errors' => $errors,
            ];
        }

        if (empty($rowsData)) {
            return [
                'success' => false,
                'message' => 'Berkas Excel tidak memiliki baris data pengguna untuk diproses.',
                'total_rows' => 0,
                'valid_count' => 0,
                'invalid_count' => 0,
                'duplicate_count' => 0,
                'rows' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Data berkas Excel berhasil diperiksa.',
            'total_rows' => count($rowsData),
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'duplicate_count' => $duplicateCount,
            'rows' => $rowsData,
        ];
    }

    /**
     * Memasukkan data pengguna valid ke dalam database tabel users.
     *
     * @param array $rows Array data pengguna yang telah diverifikasi
     * @return array Hasil eksekusi impor
     */
    public function importValidRows(array $rows): array
    {
        $importedCount = 0;
        $failedCount = 0;
        $failedRows = [];

        DB::beginTransaction();

        try {
            // Ambil email segar dari database saat eksekusi untuk mencegah race-condition
            $currentEmails = User::pluck('email')
                ->map(fn($e) => strtolower(trim($e)))
                ->filter()
                ->flip()
                ->toArray();

            $seenEmails = [];

            foreach ($rows as $item) {
                $rowNum = $item['row_number'] ?? 0;
                $name = trim((string) ($item['name'] ?? ''));
                $email = strtolower(trim((string) ($item['email'] ?? '')));
                $role = strtolower(trim((string) ($item['role'] ?? '')));

                // Validasi ulang tingkat akhir
                if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failedCount++;
                    $failedRows[] = [
                        'row_number' => $rowNum,
                        'name' => $name,
                        'email' => $email,
                        'reason' => 'Nama atau format email tidak valid.',
                    ];
                    continue;
                }

                if (!in_array($role, self::ALLOWED_ROLES, true)) {
                    $failedCount++;
                    $failedRows[] = [
                        'row_number' => $rowNum,
                        'name' => $name,
                        'email' => $email,
                        'reason' => "Role '{$role}' tidak diizinkan.",
                    ];
                    continue;
                }

                if (isset($currentEmails[$email]) || isset($seenEmails[$email])) {
                    $failedCount++;
                    $failedRows[] = [
                        'row_number' => $rowNum,
                        'name' => $name,
                        'email' => $email,
                        'reason' => 'Email sudah terdaftar di sistem.',
                    ];
                    continue;
                }

                // Pembuatan password sementara unik berbasis prefix email
                $emailPrefix = preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]) ?: 'User';
                $temporaryPassword = ucfirst(strtolower($emailPrefix)) . '@Rapi' . date('Y');

                User::create([
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'password' => Hash::make($temporaryPassword),
                ]);

                $seenEmails[$email] = true;
                $currentEmails[$email] = true;
                $importedCount++;
            }

            DB::commit();

            return [
                'success' => true,
                'total_read' => count($rows),
                'imported_count' => $importedCount,
                'failed_count' => $failedCount,
                'failed_rows' => $failedRows,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'success' => false,
                'message' => 'Terjadi kesalahan sistem saat menyimpan data: ' . $e->getMessage(),
                'total_read' => count($rows),
                'imported_count' => 0,
                'failed_count' => count($rows),
                'failed_rows' => [],
            ];
        }
    }

    /**
     * Memetakan kolom header baris pertama ke key standar (no, nama, email, role).
     */
    private function mapHeaders(array $headerRow): ?array
    {
        $normalized = [];
        foreach ($headerRow as $colKey => $value) {
            $clean = strtolower(trim((string) $value));
            $normalized[$colKey] = $clean;
        }

        $map = [
            'no' => null,
            'nama' => null,
            'email' => null,
            'role' => null,
        ];

        foreach ($normalized as $colKey => $val) {
            if (in_array($val, ['no', 'nomor', '#'], true) && $map['no'] === null) {
                $map['no'] = $colKey;
            } elseif (in_array($val, ['nama', 'name', 'nama lengkap', 'full name'], true) && $map['nama'] === null) {
                $map['nama'] = $colKey;
            } elseif (in_array($val, ['email', 'e-mail', 'alamat email'], true) && $map['email'] === null) {
                $map['email'] = $colKey;
            } elseif (in_array($val, ['role', 'peran', 'jabatan'], true) && $map['role'] === null) {
                $map['role'] = $colKey;
            }
        }

        // Jika kolom No tidak terdeteksi spesifik, ambil kolom pertama default
        if ($map['no'] === null && isset($normalized['A'])) {
            $map['no'] = 'A';
        }

        // Nama, Email, Role wajib ditemukan
        if ($map['nama'] === null || $map['email'] === null || $map['role'] === null) {
            return null;
        }

        return $map;
    }
}
