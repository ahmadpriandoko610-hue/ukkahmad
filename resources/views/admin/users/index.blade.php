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

<div style="margin:20px 0; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
    <a href="{{ route('users.create') }}"
       class="btn-login"
       style="display:inline-flex; align-items:center; gap:8px; width:auto; padding:10px 20px; text-decoration:none;">
        + Tambah User Manual
    </a>

    <a href="{{ route('users.import.form') }}"
       class="btn-login"
       style="display:inline-flex; align-items:center; gap:8px; width:auto; padding:10px 20px; text-decoration:none; background:#059669; border-color:#059669;">
        Import Pengguna Excel
    </a>
</div>

<form action="{{ route('users.index') }}" method="GET" style="margin-bottom:20px; display:flex; gap:10px;">

    <input
        type="text"
        name="keyword"
        value="{{ $keyword }}"
        class="form-control"
        placeholder="Cari nama atau email...">

    <button type="submit" class="btn-login" style="width:auto;padding:0 20px;">
        Cari
    </button>

</form>

<table border="1" width="100%" cellpadding="10">

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
            <td>{{ $user->role_label }}</td>

            <td>

                <a href="{{ route('users.edit', $user->id) }}"
                   class="btn btn-edit">
                    Edit
                </a>

                <form action="{{ route('users.destroy', $user->id) }}"
                      method="POST"
                      style="display:inline-block;">

                    @csrf
                    @method('DELETE')

                    <button type="submit"
                            class="btn btn-delete"
                            onclick="return confirm('Yakin ingin menghapus user ini?')">
                        Hapus
                    </button>

                </form>

            </td>

        </tr>

    @endforeach

    </tbody>

</table>

<div style="margin-top:20px;">
   {{ $users->withQueryString()->links() }}
</div>

@endsection
