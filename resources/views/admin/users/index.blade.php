@extends('layouts.app')

@section('title', 'Kelola User')

@section('judul', 'Kelola User')

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

<h2>Daftar User</h2>

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

<div class="page-action-bar">
    <a href="{{ route('users.create') }}"
       class="btn-login page-primary-btn">
        + Tambah User Manual
    </a>

    <a href="{{ route('users.import.form') }}"
       class="btn-login page-primary-btn"
       style="background:#059669; border-color:#059669;">
        Import Pengguna Excel
    </a>
</div>

<form action="{{ route('users.index') }}" method="GET" class="filter-bar-form">

    <input
        type="text"
        name="keyword"
        value="{{ $keyword }}"
        class="form-control"
        placeholder="Cari nama atau email...">

    <div class="filter-actions">
        <button type="submit" class="btn btn-edit">
            Cari
        </button>
    </div>

</form>

<div class="agenda-table-wrapper">

<table class="agenda-table data-table-medium">

    <thead>
        <tr>
            <th>No</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th width="180">Aksi</th>
        </tr>
    </thead>

    <tbody>

    @foreach($users as $user)

        <tr>
            <td>{{ $users->firstItem() + $loop->index }}</td>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td>
                {{ $user->role_label }}
                @if($user->rooms->isNotEmpty())
                    <div>
                        <small style="color: #64748b;">
                            PJ Ruangan: {{ $user->rooms->pluck('room_name')->implode(', ') }}
                        </small>
                    </div>
                @endif
            </td>

            <td class="room-actions-cell">

                <div class="room-actions">
                    <a href="{{ route('users.edit', $user->id) }}"
                       class="btn btn-edit">
                        Edit
                    </a>

                    @if($user->rooms->isEmpty())
                        <form action="{{ route('users.destroy', $user->id) }}"
                              method="POST"
                              class="room-delete-form">

                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="btn btn-delete"
                                    onclick="return confirm('Yakin ingin menghapus user ini?')">
                                Hapus
                            </button>

                        </form>
                    @endif
                </div>

            </td>

        </tr>

    @endforeach

    </tbody>

</table>

</div>

<div style="margin-top:20px;">
   {{ $users->withQueryString()->links() }}
</div>

@endsection
