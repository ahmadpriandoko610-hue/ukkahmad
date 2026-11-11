@extends('layouts.app')

@section('title', 'Pengaturan Notifikasi WhatsApp')

@section('judul', 'Pengaturan Notifikasi WhatsApp')

@section('content')
<div class="dash-wrapper">

    <h2>Pengaturan Notifikasi WhatsApp Guru</h2>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-error">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="dash-card" style="margin-bottom: 20px;">
        <div class="dash-card-header">
            <h3 class="dash-card-title">Status Nomor & Persetujuan Saat Ini</h3>
        </div>
        <div class="dash-card-body">
            <p style="margin-bottom: 10px; color: #334155;">
                <strong>Nomor WhatsApp Tersimpan:</strong>
                @if($user->masked_phone)
                    <code>{{ $user->masked_phone }}</code>
                @else
                    <span style="color: #64748b;">Belum tersimpan</span>
                @endif
            </p>

            <p style="margin-bottom: 10px; color: #334155;">
                <strong>Status Persetujuan:</strong>
                @if($user->hasWhatsAppConsent())
                    <span class="badge-success">Sudah Menyetujui</span>
                    @if($user->whatsapp_opt_in_at)
                        <small style="color: #475569; margin-left: 6px;">
                            (sejak {{ $user->whatsapp_opt_in_at->timezone('Asia/Jakarta')->format('d-m-Y H:i') }} WIB,
                            sumber: {{ $user->whatsapp_opt_in_source ?? 'guru_self_service' }})
                        </small>
                    @endif
                @else
                    <span class="badge-warning">Belum Menyetujui</span>
                @endif
            </p>

            <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 14px; color: #334155; font-size: 13.5px; line-height: 1.55;">
                <strong>Penjelasan Penggunaan Nomor:</strong><br>
                Nomor WhatsApp Anda hanya digunakan untuk mengirimkan notifikasi perubahan ruangan agenda sekolah yang Anda ajukan apabila ruangan dipindahkan oleh Admin. Nomor tidak digunakan untuk tujuan lain, dan notifikasi internal di dashboard RAPI-Sekolah tetap berjalan meski Anda tidak mengaktifkan WhatsApp. Persetujuan bersifat sukarela dan dapat Anda cabut kapan saja; apabila Anda mengganti ke nomor baru, persetujuan wajib diberikan ulang.
            </div>

            @if($user->hasWhatsAppConsent() || $user->phone)
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px;">
                    @if($user->hasWhatsAppConsent())
                        <form action="{{ route('guru.whatsapp.consent.destroy') }}" method="POST" style="margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="btn btn-edit"
                                onclick="return confirm('Yakin ingin mencabut persetujuan notifikasi WhatsApp?')"
                            >
                                Cabut Persetujuan
                            </button>
                        </form>
                    @endif

                    @if($user->phone)
                        <form action="{{ route('guru.whatsapp.phone.destroy') }}" method="POST" style="margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="btn btn-delete"
                                onclick="return confirm('Yakin ingin menghapus nomor WhatsApp dan mencabut persetujuan?')"
                            >
                                Hapus Nomor WhatsApp
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="dash-card">
        <div class="dash-card-header">
            <h3 class="dash-card-title">Perbarui Nomor & Persetujuan WhatsApp</h3>
        </div>
        <div class="dash-card-body">
            <form action="{{ route('guru.whatsapp.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="phone">Nomor WhatsApp (Baru / Pembaruan)</label>
                    <input
                        type="text"
                        name="phone"
                        id="phone"
                        class="form-control"
                        value="{{ old('phone') }}"
                        placeholder="Contoh: 081234567890 atau +6281234567890"
                    >
                    <small style="color: #64748b;">
                        Format diterima: 08..., 628..., atau +628... (10-14 digit lokal). Kosongkan kolom ini apabila hanya ingin memperbarui status persetujuan untuk nomor tersamar yang sudah tersimpan.
                    </small>
                    @error('phone')
                        <small class="form-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-weight: 500;">
                        <input
                            type="checkbox"
                            name="whatsapp_opt_in"
                            id="whatsapp_opt_in"
                            value="1"
                            style="margin-top: 3px;"
                            @checked((bool) old('whatsapp_opt_in', false))
                        >
                        <span>
                            Saya menyetujui bahwa nomor WhatsApp saya hanya digunakan untuk notifikasi perubahan ruangan agenda sekolah oleh RAPI-Sekolah.
                        </span>
                    </label>
                    <small style="color: #64748b;">
                        Centang kotak persetujuan di atas secara eksplisit untuk mengaktifkan atau memberikan persetujuan ulang saat memasukkan nomor baru. Jika kotak ini tidak dicentang saat menyimpan, persetujuan tidak diaktifkan / dicabut.
                    </small>
                    @error('whatsapp_opt_in')
                        <small class="form-error">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-login">
                        Simpan Pengaturan WhatsApp
                    </button>

                    <a href="{{ route('dashboard.guru') }}" class="btn btn-back">
                        Kembali ke Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

