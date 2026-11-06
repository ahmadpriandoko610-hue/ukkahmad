<?php

namespace App\Http\Controllers;

use App\Services\UserImportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserImportController extends Controller
{
    /**
     * Menampilkan halaman upload dan preview import pengguna Excel.
     */
    public function form()
    {
        $preview = session('user_import_preview');
        $importReport = session('import_report');

        return view('admin.users.import', compact('preview', 'importReport'));
    }

    /**
     * Mengunduh berkas template Excel (.xlsx) resmi untuk import pengguna.
     */
    public function downloadTemplate(UserImportService $service)
    {
        $content = $service->generateTemplate();
        $filename = 'Template_Import_Pengguna_RAPI_Sekolah.xlsx';

        return response($content, Response::HTTP_OK, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate, proxy-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Memproses file Excel yang diunggah dan menampilkan pratinjau validasi data.
     */
    public function preview(Request $request, UserImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'], // maksimal 5MB
        ], [
            'file.required' => 'Silakan pilih berkas Excel atau CSV terlebih dahulu.',
            'file.file' => 'Berkas yang diunggah tidak valid.',
            'file.max' => 'Ukuran berkas maksimal adalah 5 MB.',
        ]);

        $uploadedFile = $request->file('file');
        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        if (!in_array($extension, ['xlsx', 'csv', 'txt'], true)) {
            return redirect()->route('users.import.form')->with('error', 'Format berkas tidak didukung. Harap unggah berkas berekstensi .xlsx atau .csv.');
        }

        try {
            $previewResult = $service->parseAndValidate($uploadedFile->getRealPath());

            if (!$previewResult['success']) {
                session()->forget('user_import_preview');
                return redirect()->route('users.import.form')->with('error', $previewResult['message']);
            }

            session(['user_import_preview' => $previewResult]);

            if ($previewResult['invalid_count'] > 0) {
                return redirect()->route('users.import.form')
                    ->with('warning', 'Berkas berhasil diperiksa. Terdapat ' . $previewResult['invalid_count'] . ' data yang bermasalah. Data yang valid tetap dapat diimpor.');
            }

            return redirect()->route('users.import.form')
                ->with('success', 'Seluruh data (' . $previewResult['valid_count'] . ' baris) valid dan siap diimpor ke database.');
        } catch (\Throwable $e) {
            session()->forget('user_import_preview');
            return redirect()->route('users.import.form')
                ->with('error', 'Terjadi kesalahan saat memproses berkas: ' . $e->getMessage());
        }
    }

    /**
     * Menyimpan baris-baris pengguna yang valid ke database tabel users.
     */
    public function store(Request $request, UserImportService $service)
    {
        $preview = session('user_import_preview');

        if (!$preview || empty($preview['rows'])) {
            return redirect()->route('users.import.form')
                ->with('error', 'Tidak ada data yang sedang ditinjau. Silakan unggah berkas Excel terlebih dahulu.');
        }

        $validRows = array_values(array_filter($preview['rows'], function ($row) {
            return !empty($row['is_valid']);
        }));

        if (empty($validRows)) {
            return redirect()->route('users.import.form')
                ->with('error', 'Tidak ada data valid yang dapat diimpor ke database.');
        }

        $result = $service->importValidRows($validRows);

        // Hapus session preview setelah eksekusi
        session()->forget('user_import_preview');

        // Simpan laporan eksekusi ke session flash
        session()->flash('import_report', $result);

        if ($result['success'] && $result['imported_count'] > 0) {
            $msg = 'Berhasil mengimpor ' . $result['imported_count'] . ' pengguna baru ke sistem.';
            if ($result['failed_count'] > 0) {
                $msg .= ' (' . $result['failed_count'] . ' data gagal diproses).';
            }
            return redirect()->route('users.import.form')->with('success', $msg);
        }

        return redirect()->route('users.import.form')
            ->with('error', $result['message'] ?? 'Gagal mengimpor data pengguna ke database.');
    }

    /**
     * Membatalkan sesi pratinjau data import.
     */
    public function cancel()
    {
        session()->forget('user_import_preview');

        return redirect()->route('users.import.form')
            ->with('info', 'Pratinjau data import telah dibatalkan.');
    }
}

