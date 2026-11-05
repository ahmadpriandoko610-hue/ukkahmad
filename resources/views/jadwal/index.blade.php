@extends('layouts.app')

@section('title', 'Schedule')

@section('judul', 'Schedule')

@section('sidebar')

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit" class="menu-item btn-logout-sidebar">
            Logout
        </button>
    </form>

@endsection

@section('content')

@if(session('success'))

    <div class="alert alert-success">
        {{ session('success') }}
    </div>

@endif

<div class="table-container">

    <table class="table">

        <thead>
            <tr>
                <th>No</th>
                <th>Activity</th>
                <th>Room</th>
                <th>Date</th>
                <th>Time</th>
                <th>Description</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>

            @forelse($agenda as $item)

                <tr>

                    <td>
                        {{ $loop->iteration }}
                    </td>

                    <td>
                        {{ $item->event_name }}
                    </td>

                    <td>
                        {{ $item->room->room_name ?? '-' }}
                    </td>

                    <td>
                        {{ $item->formatted_date_range }}
                    </td>

                    <td>
                        {{ $item->session_range }}
                    </td>

                    <td>
                        {{ $item->description ?? '-' }}
                    </td>

                    <td>
                        @if($item->status_pelaksanaan === 'Sedang Berlangsung')
                            <span class="badge-ongoing">Sedang Berlangsung</span>
                        @elseif($item->status_pelaksanaan === 'Belum Terlaksana')
                            <span class="badge-upcoming">Belum Terlaksana</span>
                        @else
                            <span class="badge-completed">Sudah Terlaksana</span>
                        @endif
                    </td>

                </tr>

            @empty

                <tr>
                    <td colspan="7" style="text-align:center; padding:20px;">
                        No approved schedules yet.
                    </td>
                </tr>

            @endforelse

        </tbody>

    </table>

</div>

@endsection
