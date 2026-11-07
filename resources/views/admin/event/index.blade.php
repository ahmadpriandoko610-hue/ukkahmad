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

<a href="{{ route('ruangan.index') }}" class="menu-item">
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

@if(session('error'))
<div class="alert-danger">
    {{ session('error') }}
</div>
@endif


<div style="margin:20px 0;">

    <a href="{{ route('agenda.create') }}"
       class="btn-login"
       style="display:inline-block;width:auto;padding:10px 20px;text-decoration:none;">

        + Tambah Agenda

    </a>

</div>


<div class="agenda-table-wrapper">

<table class="agenda-table">

    <thead>

        <tr>
            <th>No</th>
            <th>Judul</th>
            <th>Tanggal</th>
            <th>Sesi</th>
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
                {{ $loop->iteration }}
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
                @if(isset($item->rooms) && $item->rooms->count() > 1)
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        @foreach($item->rooms as $r)
                            <span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; display: inline-block;">
                                {{ $r->room_name ?: $r->nama_ruangan }}
                            </span>
                        @endforeach
                    </div>
                @else
                    {{ $item->ruangan->nama_ruangan ?? ($item->room->room_name ?? '-') }}
                @endif
            </td>

            <td>
                {{ $item->user->name }}
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
                        <div style="font-size: 11px; color: #b91c1c; margin-top: 4px; max-width: 180px; word-break: break-word; line-height: 1.3;">
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

            <td colspan="9" style="text-align:center;">

                Belum ada data agenda.

            </td>

        </tr>

    @endforelse

    </tbody>

</table>

</div>


<div style="margin-top:20px;">

    {{ $agenda->links() }}

</div>

{{-- MODAL TOLAK AGENDA --}}
<div id="rejectModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div class="modal-content" style="background:#fff; border-radius:8px; max-width:480px; width:90%; padding:24px; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
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

            <div style="display:flex; justify-content:flex-end; gap:10px;">
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
