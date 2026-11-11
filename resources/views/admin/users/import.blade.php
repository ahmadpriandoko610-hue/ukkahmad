@extends('layouts.app')

@section('title', 'Import Pengguna melalui Excel - RAPI Sekolah')
@section('judul', 'Import Pengguna Excel')

@section('sidebar')
<a href="{{ route('dashboard.admin') }}" class="menu-item">
    Dashboard
</a>
<a href="{{ route('users.index') }}" class="menu-item active">
    Kelola User
</a>
<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>
@endsection

@section('content')
<div style="max-width: 1200px; margin: 0 auto; padding-bottom: 40px;">

    {{-- Breadcrumb & Navigasi Cepat --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
        <div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.875rem; color: #64748b; margin-bottom: 4px;">
                <a href="{{ route('dashboard.admin') }}" style="color: #2563eb; text-decoration: none;">Dashboard</a>
                <span>/</span>
                <a href="{{ route('users.index') }}" style="color: #2563eb; text-decoration: none;">Data User</a>
                <span>/</span>
                <span style="color: #1e293b; font-weight: 600;">Import Pengguna Excel</span>
            </div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0;">Import Pengguna Massal via Excel</h2>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="{{ route('users.index') }}" class="btn" style="background: #f1f5f9; color: #334155; padding: 9px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #cbd5e1;">
                ← Kembali ke Data User
            </a>
            <a href="{{ route('users.import.template') }}" class="btn-login" style="background: #1d4ed8; color: #fff; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; width: auto; box-shadow: 0 2px 4px rgba(37,99,235,0.2);">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                Download Template Excel (.xlsx)
            </a>
        </div>
    </div>

    {{-- Notifikasi Pesan Flash --}}
    @if(session('success'))
        <div style="background: #ecfdf5; border-left: 4px solid #10b981; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #065f46; font-size: 0.95rem; display: flex; align-items: flex-start; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <span style="font-size: 1.25rem;">✅</span>
            <div style="flex: 1;">
                <strong>Berhasil:</strong> {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #92400e; font-size: 0.95rem; display: flex; align-items: flex-start; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <span style="font-size: 1.25rem;">⚠️</span>
            <div style="flex: 1;">
                <strong>Perhatian:</strong> {{ session('warning') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #991b1b; font-size: 0.95rem; display: flex; align-items: flex-start; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <span style="font-size: 1.25rem;">❌</span>
            <div style="flex: 1;">
                <strong>Gagal:</strong> {{ session('error') }}
            </div>
        </div>
    @endif

    @if($errors->any())
        <div style="background: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; color: #991b1b; font-size: 0.95rem;">
            <strong>Terdapat kesalahan validasi:</strong>
            <ul style="margin: 6px 0 0 20px; padding: 0;">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Laporan Hasil Eksekusi Import --}}
    @if(isset($importReport) && is_array($importReport))
        <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 22px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
                    Ringkasan Hasil Eksekusi Import
                </h3>
                <span style="font-size: 0.85rem; color: #64748b;">Baru saja selesai diproses</span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Total Data Dibaca</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a;">{{ $importReport['total_read'] ?? 0 }}</div>
                </div>
                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 0.8rem; color: #065f46; font-weight: 600; text-transform: uppercase;">Berhasil Ditambahkan</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #059669;">{{ $importReport['imported_count'] ?? 0 }}</div>
                </div>
                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px; text-align: center;">
                    <div style="font-size: 0.8rem; color: #991b1b; font-weight: 600; text-transform: uppercase;">Gagal Ditambahkan</div>
                    <div style="font-size: 1.75rem; font-weight: 800; color: #dc2626;">{{ $importReport['failed_count'] ?? 0 }}</div>
                </div>
            </div>

            {{-- Informasi Keamanan Password Sementara --}}
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 14px 18px; margin-bottom: 16px; color: #1e40af; font-size: 0.9rem; line-height: 1.5;">
                <strong>Informasi Akses Akun Baru:</strong>
                <p style="margin: 4px 0 0 0;">
                    Akun pengguna yang berhasil ditambahkan diberikan <em>password sementara unik</em> dengan format:
                    <code style="background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-weight: 600;">[PrefixEmail]@Rapi{{ date('Y') }}</code>
                    (contoh: email <code>budi.santoso@gmail.com</code> → password sementara <code>Budisantoso@Rapi{{ date('Y') }}</code>).
                    Password telah diamankan melalui enkripsi hash Laravel. Arahkan pengguna baru untuk segera mengganti password saat login pertama kali.
                </p>
            </div>

            @if(!empty($importReport['failed_rows']))
                <div style="margin-top: 14px;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: #991b1b; margin-bottom: 8px;">Daftar Baris yang Gagal Disimpan:</h4>
                    <div style="overflow-x: auto; border: 1px solid #fee2e2; border-radius: 6px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                            <thead style="background: #fee2e2; color: #991b1b;">
                                <tr>
                                    <th style="padding: 8px 12px; text-align: left;">Baris Excel</th>
                                    <th style="padding: 8px 12px; text-align: left;">Nama</th>
                                    <th style="padding: 8px 12px; text-align: left;">Email</th>
                                    <th style="padding: 8px 12px; text-align: left;">Alasan Kegagalan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($importReport['failed_rows'] as $fRow)
                                    <tr style="border-top: 1px solid #fee2e2;">
                                        <td style="padding: 8px 12px; font-weight: 600;">Baris ke-{{ $fRow['row_number'] ?? '-' }}</td>
                                        <td style="padding: 8px 12px;">{{ $fRow['name'] ?? '-' }}</td>
                                        <td style="padding: 8px 12px;">{{ $fRow['email'] ?? '-' }}</td>
                                        <td style="padding: 8px 12px; color: #dc2626; font-weight: 600;">{{ $fRow['reason'] ?? 'Kesalahan validasi' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div style="margin-top: 18px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="{{ route('users.index') }}" class="btn-login" style="background: #059669; color: #fff; padding: 9px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; width: auto;">
                    Lihat Daftar Pengguna di Sistem →
                </a>
            </div>
        </div>
    @endif

    {{-- Form Upload Excel --}}
    <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
            <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: bold;">
                1
            </div>
            <div>
                <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Unggah Berkas Excel Pengguna</h3>
                <p style="font-size: 0.875rem; color: #64748b; margin: 2px 0 0 0;">
                    Unggah berkas spreadsheet (.xlsx atau .csv) yang sudah diisi sesuai format template resmi.
                </p>
            </div>
        </div>

        <form action="{{ route('users.import.preview') }}" method="POST" enctype="multipart/form-data" id="importForm">
            @csrf

            <div style="border: 2px dashed #cbd5e1; border-radius: 10px; padding: 30px 20px; text-align: center; background: #f8fafc; transition: all 0.2s ease; cursor: pointer;" id="dropArea">
                <input type="file" name="file" id="excelFile" accept=".xlsx,.csv" style="display: none;" required>

                <div style="display: flex; flex-direction: column; align-items: center; gap: 10px;">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>

                    <div>
                        <span style="font-weight: 600; color: #0f172a; font-size: 1rem;">Klik di sini untuk memilih berkas</span>
                        <span style="color: #64748b; font-size: 0.9rem;"> atau seret berkas Excel Anda ke area ini</span>
                    </div>

                    <div id="fileInfo" style="display: none; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 6px 14px; border-radius: 20px; font-size: 0.875rem; font-weight: 600;">
                        <span id="fileName">Nama file</span>
                    </div>

                    <div style="font-size: 0.8rem; color: #94a3b8;">
                        Format yang didukung: <strong>.XLSX</strong>, <strong>.CSV</strong> • Ukuran maksimal: <strong>5 MB</strong>
                    </div>
                </div>
            </div>

            {{-- Petunjuk Singkat Pengisian --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; margin-top: 16px;">
                <div style="background: #f8fafc; padding: 10px 14px; border-radius: 6px; border-left: 3px solid #2563eb; font-size: 0.825rem; color: #475569;">
                    <strong>Header Wajib:</strong> Kolom pertama harus berisi <code>No</code>, <code>Nama</code>, <code>Email</code>, <code>Role</code>.
                </div>
                <div style="background: #f8fafc; padding: 10px 14px; border-radius: 6px; border-left: 3px solid #10b981; font-size: 0.825rem; color: #475569;">
                    <strong>Pilihan Role:</strong> Wajib diisi salah satu dari <code>guru</code>, <code>pic</code>, <code>siswa</code>, atau <code>admin</code>.
                </div>
                <div style="background: #f8fafc; padding: 10px 14px; border-radius: 6px; border-left: 3px solid #f59e0b; font-size: 0.825rem; color: #475569;">
                    <strong>Keunikan Email:</strong> Email tidak boleh duplikat di dalam file maupun di database sistem.
                </div>
            </div>

            <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap;">
                <button type="submit" id="btnPreview" class="btn-login" style="width: auto; padding: 10px 24px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; background: #2563eb; color: #fff;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    Periksa Data (Preview)
                </button>
            </div>
        </form>
    </div>

    {{-- Area Preview Data --}}
    @if(isset($preview) && is_array($preview) && !empty($preview['rows']))
        <div style="background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: bold;">
                        2
                    </div>
                    <div>
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0;">Pratinjau & Validasi Data Pengguna</h3>
                        <p style="font-size: 0.875rem; color: #64748b; margin: 2px 0 0 0;">
                            Periksa hasil validasi setiap baris data di bawah sebelum menyimpan ke database.
                        </p>
                    </div>
                </div>

                {{-- Tombol Batal Preview --}}
                <form action="{{ route('users.import.cancel') }}" method="POST" style="margin: 0;">
                    @csrf
                    <button type="submit" class="btn" style="background: #f8fafc; border: 1px solid #cbd5e1; color: #64748b; padding: 8px 14px; border-radius: 8px; font-size: 0.875rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        ✕ Batal / Unggah Berkas Baru
                    </button>
                </form>
            </div>

            {{-- 4 Stat Badges --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; margin-bottom: 22px;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px;">
                    <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Total Data Terdeteksi</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-top: 4px;">{{ $preview['total_rows'] ?? 0 }}</div>
                </div>

                <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 16px;">
                    <div style="font-size: 0.75rem; color: #065f46; font-weight: 700; text-transform: uppercase;">Data Valid (Siap Impor)</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #059669; margin-top: 4px;">{{ $preview['valid_count'] ?? 0 }}</div>
                </div>

                <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px 16px;">
                    <div style="font-size: 0.75rem; color: #991b1b; font-weight: 700; text-transform: uppercase;">Data Tidak Valid</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #dc2626; margin-top: 4px;">{{ $preview['invalid_count'] ?? 0 }}</div>
                </div>

                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 16px;">
                    <div style="font-size: 0.75rem; color: #92400e; font-weight: 700; text-transform: uppercase;">Email Duplikat</div>
                    <div style="font-size: 1.6rem; font-weight: 800; color: #d97706; margin-top: 4px;">{{ $preview['duplicate_count'] ?? 0 }}</div>
                </div>
            </div>

            {{-- Tabel Pratinjau --}}
            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 22px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                        <tr>
                            <th style="padding: 12px 14px; text-align: center; width: 60px; color: #475569; font-weight: 700;">Baris</th>
                            <th style="padding: 12px 14px; text-align: left; color: #475569; font-weight: 700;">Nama Lengkap</th>
                            <th style="padding: 12px 14px; text-align: left; color: #475569; font-weight: 700;">Email</th>
                            <th style="padding: 12px 14px; text-align: center; width: 140px; color: #475569; font-weight: 700;">Role</th>
                            <th style="padding: 12px 14px; text-align: center; width: 130px; color: #475569; font-weight: 700;">Status</th>
                            <th style="padding: 12px 14px; text-align: left; color: #475569; font-weight: 700;">Keterangan Validasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($preview['rows'] as $row)
                            <tr style="border-bottom: 1px solid #f1f5f9; background: {{ $row['is_valid'] ? '#ffffff' : '#fef2f2' }}; transition: background 0.15s ease;">
                                <td style="padding: 10px 14px; text-align: center; font-weight: 600; color: #64748b;">
                                    {{ $row['row_number'] }}
                                </td>
                                <td style="padding: 10px 14px; font-weight: 600; color: #0f172a;">
                                    {{ $row['name'] ?: '-' }}
                                </td>
                                <td style="padding: 10px 14px; color: #334155; font-family: monospace; font-size: 0.85rem;">
                                    {{ $row['email'] ?: '-' }}
                                </td>
                                <td style="padding: 10px 14px; text-align: center;">
                                    @php
                                        $roleBadgeBg = match($row['role']) {
                                            'admin' => '#fee2e2; color:#991b1b',
                                            'pic' => '#fef3c7; color:#92400e',
                                            'guru' => '#dbeafe; color:#1e40af',
                                            'siswa' => '#e0e7ff; color:#3730a3',
                                            default => '#f1f5f9; color:#475569'
                                        };
                                    @endphp
                                    <span style="display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: {{ $roleBadgeBg }}; text-transform: uppercase;">
                                        {{ $row['role'] ?: 'KOSONG' }}
                                    </span>
                                </td>
                                <td style="padding: 10px 14px; text-align: center;">
                                    @if($row['is_valid'])
                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: #ecfdf5; color: #059669;">
                                            ● Valid
                                        </span>
                                    @else
                                        <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; background: #fee2e2; color: #dc2626;">
                                            ● Tidak Valid
                                        </span>
                                    @endif
                                </td>
                                <td style="padding: 10px 14px; font-size: 0.825rem;">
                                    @if($row['is_valid'])
                                        <span style="color: #059669; font-weight: 500;">✓ Data lengkap & siap diimpor</span>
                                    @else
                                        <ul style="margin: 0; padding-left: 18px; color: #dc2626; font-weight: 500;">
                                            @foreach($row['errors'] as $err)
                                                <li>{{ $err }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Konfirmasi Tindakan Import --}}
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div style="font-size: 0.875rem; color: #475569;">
                    @if(($preview['valid_count'] ?? 0) > 0)
                        <span>Siap mengimpor <strong style="color: #059669;">{{ $preview['valid_count'] }} pengguna valid</strong> ke dalam database. Baris yang tidak valid akan dilewati secara otomatis.</span>
                    @else
                        <span style="color: #dc2626; font-weight: 600;">Tidak ada baris data yang valid. Silakan perbaiki berkas Excel Anda lalu unggah kembali.</span>
                    @endif
                </div>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <form action="{{ route('users.import.cancel') }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" class="btn" style="background: #ffffff; border: 1px solid #cbd5e1; color: #64748b; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            Batal
                        </button>
                    </form>

                    <form action="{{ route('users.import.store') }}" method="POST" style="margin: 0;" onsubmit="return confirm('Apakah Anda yakin ingin mengimpor {{ $preview['valid_count'] }} akun pengguna yang valid ke database?');">
                        @csrf
                        <button type="submit"
                                class="btn-login"
                                style="width: auto; padding: 10px 22px; border-radius: 8px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; background: {{ ($preview['valid_count'] ?? 0) > 0 ? '#059669' : '#94a3b8' }}; color: #fff; cursor: {{ ($preview['valid_count'] ?? 0) > 0 ? 'pointer' : 'not-allowed' }}; box-shadow: {{ ($preview['valid_count'] ?? 0) > 0 ? '0 2px 6px rgba(5,150,105,0.3)' : 'none' }};"
                                {{ ($preview['valid_count'] ?? 0) > 0 ? '' : 'disabled' }}>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Import Pengguna ({{ $preview['valid_count'] }} Akun Valid)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

</div>

{{-- Interaktivitas Drag & Drop dan File Picker --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('excelFile');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');

        if (dropArea && fileInput) {
            dropArea.addEventListener('click', () => fileInput.click());

            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropArea.style.borderColor = '#2563eb';
                    dropArea.style.background = '#eff6ff';
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropArea.style.borderColor = '#cbd5e1';
                    dropArea.style.background = '#f8fafc';
                });
            });

            dropArea.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    updateFileLabel(files[0].name);
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    updateFileLabel(fileInput.files[0].name);
                }
            });

            function updateFileLabel(name) {
                if (fileName && fileInfo) {
                    fileName.textContent = name;
                    fileInfo.style.display = 'inline-block';
                }
            }
        }
    });
</script>
@endsection

