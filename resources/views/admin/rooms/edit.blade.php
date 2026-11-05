@extends('layouts.app')

@section('title', 'Edit Room')

@section('judul', 'Edit Ruangan')

@section('sidebar')

<a href="{{ route('dashboard.admin') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('users.index') }}" class="menu-item">
    Data User
</a>

<a href="{{ route('rooms.index') }}" class="menu-item active">
    Data Ruangan
</a>

<a href="{{ route('events.index') }}" class="menu-item">
    Data Agenda
</a>

<a href="{{ route('admin.calendar') }}" class="menu-item">
    Kalender
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf

    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection


@section('content')


{{-- PESAN ERROR --}}

@if ($errors->any())

    <div class="alert-danger">

        <ul>

            @foreach ($errors->all() as $error)

                <li>{{ $error }}</li>

            @endforeach

        </ul>

    </div>

@endif


{{-- FORM EDIT RUANGAN --}}

<form
    action="{{ route('rooms.update', $room->id) }}"
    method="POST"
>

    @csrf
    @method('PUT')


    {{-- NAMA RUANGAN --}}

    <div class="form-group">

        <label for="room_name">
            Nama Ruangan
        </label>

        <input
            type="text"
            id="room_name"
            name="room_name"
            value="{{ old('room_name', $room->room_name) }}"
            class="form-control"
            placeholder="Masukkan nama ruangan"
            required
        >

    </div>


    {{-- LOKASI --}}

    <div class="form-group">

        <label for="location">
            Lokasi
        </label>

        <input
            type="text"
            id="location"
            name="location"
            value="{{ old('location', $room->location) }}"
            class="form-control"
            placeholder="Masukkan lokasi"
            required
        >

    </div>


    {{-- KAPASITAS --}}

    <div class="form-group">

        <label for="capacity">
            Kapasitas
        </label>

        <input
            type="number"
            id="capacity"
            name="capacity"
            value="{{ old('capacity', $room->capacity) }}"
            class="form-control"
            placeholder="Masukkan kapasitas"
            min="1"
            required
        >

    </div>


    {{-- PIC RUANGAN --}}

    <div class="form-group">

        <label for="pic_id">
            PIC Ruangan
        </label>

        <select
            name="pic_id"
            id="pic_id"
            class="form-control"
            required
        >

            <option value="">
                -- Pilih PIC Ruangan --
            </option>

            @foreach ($pics as $pic)

                <option
                    value="{{ $pic->id }}"
                    {{ old('pic_id', $room->pic_id) == $pic->id ? 'selected' : '' }}
                >
                    {{ $pic->name }} - {{ $pic->email }}
                </option>

            @endforeach

        </select>


        @error('pic_id')

            <small class="text-danger">
                {{ $message }}
            </small>

        @enderror

    </div>


    {{-- KETERANGAN --}}

    <div class="form-group">

        <label for="description">
            Keterangan
        </label>

        <textarea
            name="description"
            id="description"
            class="form-control"
            rows="4"
            placeholder="Masukkan keterangan"
        >{{ old('description', $room->description) }}</textarea>

    </div>


    {{-- BUTTON --}}

    <button
        type="submit"
        class="btn-login"
    >
        Update
    </button>

    <a href="{{ route('rooms.index') }}" class="btn btn-back">Kembali</a>

</form>

@endsection
