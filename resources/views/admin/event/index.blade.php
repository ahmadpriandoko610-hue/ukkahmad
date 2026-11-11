@extends('layouts.app')

@section('title', 'Kelola Agenda')

@section('judul', 'Kelola Agenda')

@section('sidebar')

<a href="{{ route('dashboard.admin') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('users.index') }}" class="menu-item">
    Kelola User
</a>

<a href="{{ route('rooms.index') }}" class="menu-item">
    Kelola Ruangan
</a>

<a href="{{ route('agenda.index') }}" class="menu-item active">
    Kelola Agenda
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection


@section('content')

<h2>Daftar Agenda</h2>

@if(session('success'))
<div class="alert-success">
    {{ session('success') }}
</div>
@endif

@if(session('warning'))
<div class="alert-warning">
    {{ session('warning') }}
</div>
@endif

@if(session('error'))
<div class="alert-danger">
    {{ session('error') }}
</div>
@endif

@if($errors->any())
<div class="alert-danger">
    @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

@includeWhen(!empty($roomMoveWhatsapp['available']), 'admin.event.partials.room-move-whatsapp-notice', ['roomMoveWhatsapp' => $roomMoveWhatsapp ?? null])


<div class="page-action-bar">

    <a href="{{ route('agenda.create') }}"
       class="btn-login page-primary-btn">

        + Tambah Agenda

    </a>

</div>

<form action="{{ route('agenda.index') }}" method="GET" class="date-filter-form">
    <div class="filter-field filter-field-search">
        <label for="admin_agenda_search">Pencarian</label>
        <input
            type="search"
            id="admin_agenda_search"
            name="search"
            value="{{ old('search', $filters['search'] ?? request('search')) }}"
            class="form-control"
            placeholder="Cari judul, ruangan, atau pengaju..."
        >
    </div>

    <div class="filter-field">
        <label for="admin_tanggal_mulai">Tanggal Mulai</label>
        <input
            type="date"
            id="admin_tanggal_mulai"
            name="tanggal_mulai"
            value="{{ old('tanggal_mulai', $filters['tanggal_mulai'] ?? request('tanggal_mulai')) }}"
            class="form-control"
        >
    </div>

    <div class="filter-field">
        <label for="admin_tanggal_selesai">Tanggal Selesai</label>
        <input
            type="date"
            id="admin_tanggal_selesai"
            name="tanggal_selesai"
            value="{{ old('tanggal_selesai', $filters['tanggal_selesai'] ?? request('tanggal_selesai')) }}"
            class="form-control"
        >
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-edit">Terapkan Filter</button>
        <a href="{{ route('agenda.index') }}" class="btn btn-back">Reset Filter</a>
    </div>
</form>


<div class="agenda-table-wrapper">

<table class="agenda-table">

    <thead>

        <tr>
            <th>No</th>
            <th>Judul</th>
            <th>Tanggal</th>
            <th>Waktu Sesi</th>
            <th>Jam Mulai</th>
            <th>Jam Selesai</th>
            <th>Ruangan</th>
            <th>Pengaju</th>
            <th>Status</th>
            <th>Disetujui</th>
            <th>Aksi</th>
        </tr>

    </thead>


    <tbody>

    @forelse($agenda as $item)

        <tr>

            <td>
                {{ method_exists($agenda, 'firstItem') && $agenda->firstItem() ? ($agenda->firstItem() + $loop->index) : $loop->iteration }}
            </td>

            <td>
                {{ $item->judul }}
            </td>

            <td>
                {{ $item->formatted_date_range }}
            </td>

            <td>
                {{ $item->session_range }}
            </td>

            <td>
                {{ $item->start_time ? substr((string) $item->start_time, 0, 5) : '-' }}
            </td>

            <td>
                {{ $item->end_time ? substr((string) $item->end_time, 0, 5) : '-' }}
            </td>

            <td>
                @if(isset($item->rooms) && $item->rooms->count() > 1)
                    <div class="room-pill-list">
                        @foreach($item->rooms as $r)
                            <span class="room-pill-badge">
                                {{ $r->room_name ?: $r->nama_ruangan }}
                            </span>
                        @endforeach
                    </div>
                @else
                    {{ $item->ruangan->nama_ruangan ?? ($item->room->room_name ?? '-') }}
                @endif
            </td>

            <td>
                {{ $item->user?->name ?? '-' }}
            </td>

            <td>
                @if($item->status_pelaksanaan === 'Sedang Berlangsung')
                    <span class="badge-ongoing">Sedang Berlangsung</span>
                @elseif($item->status_pelaksanaan === 'Belum Terlaksana')
                    <span class="badge-upcoming">Belum Terlaksana</span>
                @elseif($item->status_pelaksanaan === 'Sudah Terlaksana')
                    <span class="badge-completed">Sudah Terlaksana</span>
                @elseif($item->status === 'Menunggu')
                    <span class="badge-warning">Menunggu</span>
                @elseif($item->status === 'Ditolak')
                    <span class="badge-danger">Ditolak</span>
                    @if($item->rejection_reason)
                        <div class="rejection-reason-note">
                            <strong>Alasan:</strong> {{ $item->rejection_reason }}
                        </div>
                    @endif
                @else
                    <span class="badge-danger">{{ $item->status }}</span>
                @endif
            </td>

            <td>{{ $item->status === 'Menunggu' ? '-' : ($item->approver?->name ?? '-') }}</td>


            <td class="agenda-action-cell">

                <div class="agenda-actions">

                    <a href="{{ route('events.show', $item->id) }}" class="btn btn-back" style="grid-column:1/-1">
                        Detail
                    </a>

                    @if($item->status === 'Menunggu')
                        <form action="{{ route('admin.agenda.approve', $item) }}" method="POST">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-edit"
                                onclick="return confirm('Setujui agenda ini?')"
                            >
                                Setujui
                            </button>
                        </form>
                        <button
                            type="button"
                            class="btn btn-delete btn-open-reject-modal"
                            data-action="{{ route('admin.agenda.reject', $item->id) }}"
                            data-title="{{ $item->judul }}"
                        >
                            Tolak
                        </button>
                    @elseif($item->status === 'Ditolak')
                        <form
                            action="{{ route('agenda.destroy', $item->id) }}"
                            method="POST"
                            class="agenda-delete-form"
                        >
                            @csrf
                            @method('DELETE')
                            <button
                                type="submit"
                                class="btn btn-delete"
                                onclick="return confirm('Yakin ingin menghapus agenda ini?')"
                            >
                                Hapus
                            </button>
                        </form>

                    @else

                    @if($item->status === 'Disetujui')
                    <a href="{{ route('admin.complaints.create', $item) }}" class="btn btn-edit" style="grid-column:1/-1">
                        Buat Pengaduan
                    </a>
                    @endif


                    {{-- EDIT --}}

                    <a href="{{ route('agenda.edit', $item->id) }}"
                       class="btn btn-edit agenda-edit-button">

                        Edit

                    </a>


                    {{-- PINDAH RUANGAN --}}

                    <a href="{{ route('events.move.form', $item->id) }}"
                       class="btn btn-edit agenda-move-button">

                        Pindah Ruangan

                    </a>
                    {{-- HAPUS --}}

                    <form
                        action="{{ route('agenda.destroy', $item->id) }}"
                        method="POST"
                        class="agenda-delete-form"
                    >

                        @csrf

                        @method('DELETE')

                        <button
                            type="submit"
                            class="btn btn-delete"
                            onclick="return confirm('Yakin ingin menghapus agenda ini?')"
                        >

                            Hapus

                        </button>

                    </form>

                    @endif


                </div>

            </td>

        </tr>

    @empty

        <tr>

            <td colspan="11" style="text-align:center;">

                Belum ada data agenda.

            </td>

        </tr>

    @endforelse

    </tbody>

</table>

</div>


<div style="margin-top:20px;">

    {{ $agenda->withQueryString()->links() }}

</div>

{{-- MODAL TOLAK AGENDA --}}
<div id="rejectModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; padding:16px;">
    <div class="modal-content" style="background:#fff; border-radius:8px; max-width:480px; width:100%; padding:24px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <h3 style="margin-top:0; margin-bottom:12px; font-size:18px; color:#1e293b;">Tolak Pengajuan Agenda</h3>
        <p style="margin-bottom:16px; color:#64748b; font-size:14px;">
            Masukkan alasan / keterangan penolakan untuk agenda <strong id="modalEventTitle"></strong>:
        </p>

        <form id="rejectForm" method="POST" action="">
            @csrf
            <div class="form-group" style="margin-bottom:16px;">
                <label for="modalRejectionReason" style="display:block; margin-bottom:6px; font-weight:600; font-size:13px; color:#334155;">Keterangan Penolakan <span style="color:#ef4444;">*</span></label>
                <textarea
                    name="rejection_reason"
                    id="modalRejectionReason"
                    class="form-control"
                    rows="4"
                    placeholder="Masukkan alasan penolakan agenda..."
                    required
                    style="width:100%; box-sizing:border-box; border:1px solid #cbd5e1; border-radius:6px; padding:10px; font-size:14px;"
                ></textarea>
            </div>

            <div class="modal-actions-row">
                <button type="button" id="btnCancelRejectModal" class="btn btn-back" style="padding:8px 16px; font-size:13px;">Batal</button>
                <button type="submit" class="btn btn-delete" style="padding:8px 16px; font-size:13px;">Kirim Penolakan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rejectModal = document.getElementById('rejectModal');
    const rejectForm = document.getElementById('rejectForm');
    const modalEventTitle = document.getElementById('modalEventTitle');
    const modalRejectionReason = document.getElementById('modalRejectionReason');
    const btnCancelRejectModal = document.getElementById('btnCancelRejectModal');

    document.querySelectorAll('.btn-open-reject-modal').forEach(button => {
        button.addEventListener('click', function () {
            const action = this.getAttribute('data-action');
            const title = this.getAttribute('data-title');
            rejectForm.action = action;
            modalEventTitle.textContent = '"' + title + '"';
            modalRejectionReason.value = '';
            rejectModal.style.display = 'flex';
            modalRejectionReason.focus();
        });
    });

    if (btnCancelRejectModal) {
        btnCancelRejectModal.addEventListener('click', function () {
            rejectModal.style.display = 'none';
        });
    }

    if (rejectModal) {
        rejectModal.addEventListener('click', function (e) {
            if (e.target === rejectModal) {
                rejectModal.style.display = 'none';
            }
        });
    }
});
</script>

@endsection
