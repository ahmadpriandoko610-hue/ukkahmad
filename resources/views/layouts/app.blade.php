@php
    $unreadNotifications = Auth::user()
        ->complaintNotifications()
        ->where('is_read', false)
        ->latest()
        ->get();
@endphp

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'RAPI Sekolah')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>

    {{-- ========================================================= --}}
    {{-- SIDEBAR --}}
    {{-- ========================================================= --}}

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <aside class="sidebar" id="sidebar" aria-label="Navigasi Utama">

        {{-- BRAND --}}
        <div class="sidebar-brand">

            <div>
                <strong>RAPI</strong>
                <br>
                <small>Sekolah</small>
            </div>

            <div class="sidebar-brand-actions">
                <span class="badge-role">
                    {{ Auth::user()->role_label }}
                </span>
                <button type="button" class="sidebar-close-btn" id="sidebarClose" aria-label="Tutup menu">✕</button>
            </div>

        </div>


        {{-- ===================================================== --}}
        {{-- MENU SIDEBAR --}}
        {{-- ===================================================== --}}

        <div class="sidebar-menu">


            {{-- ================================================= --}}
            {{-- ADMIN --}}
            {{-- ================================================= --}}

            @if(Auth::user()->role === 'admin')

                <a href="{{ route('dashboard.admin') }}"
                   class="menu-item {{ request()->routeIs('dashboard.admin') ? 'active' : '' }}">

                    Dashboard

                </a>


                <a href="{{ route('users.index') }}"
                   class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">

                    Data User

                </a>


                <a href="{{ route('rooms.index') }}"
                   class="menu-item {{ request()->routeIs('rooms.*', 'ruangan.*') ? 'active' : '' }}">

                    Data Ruangan

                </a>


                <a href="{{ route('events.index') }}"
                   class="menu-item {{ (request()->routeIs('events.*', 'agenda.*') && !request()->routeIs('admin.agenda.report')) ? 'active' : '' }}">

                    Data Agenda

                </a>


                <a href="{{ route('admin.agenda.report') }}"
                   class="menu-item {{ request()->routeIs('admin.agenda.report') ? 'active' : '' }}">

                    Laporan Agenda

                </a>


                <a href="{{ route('admin.complaints') }}"
                   class="menu-item {{ request()->routeIs('admin.complaints*') ? 'active' : '' }}">

                    Pengaduan Kerusakan

                </a>


                <a href="{{ route('admin.calendar') }}"
                   class="menu-item {{ request()->routeIs('admin.calendar') ? 'active' : '' }}">

                    Kalender

                </a>


                <a href="{{ route('jadwal.index') }}"
                   class="menu-item {{ request()->routeIs('jadwal.index') ? 'active' : '' }}">

                    Jadwal Ruangan

                </a>

            @endif



            {{-- ================================================= --}}
            {{-- PIC --}}
            {{-- ================================================= --}}

            @if(Auth::user()->role === 'pic')

                <a href="{{ route('dashboard.pic') }}"
                   class="menu-item {{ request()->routeIs('dashboard.pic') ? 'active' : '' }}">

                    Dashboard

                </a>


                <a href="{{ route('pic.events') }}"
                   class="menu-item {{ request()->routeIs('pic.events', 'pic.agenda', 'events.approve', 'events.reject*', 'agenda.setujui', 'agenda.tolak*') ? 'active' : '' }}">

                    Verifikasi Agenda

                </a>


                <a href="{{ route('pic.calendar') }}"
                   class="menu-item {{ request()->routeIs('pic.calendar') ? 'active' : '' }}">

                    Kalender Agenda

                </a>


                <a href="{{ route('pic.complaints') }}"
                   class="menu-item {{ (request()->routeIs('pic.complaints', 'pic.complaints.update', 'pic.complaints.edit') && !request()->routeIs('pic.complaints.report')) ? 'active' : '' }}">

                    Pengaduan Kerusakan

                </a>


                <a href="{{ route('pic.complaints.report') }}"
                   class="menu-item {{ request()->routeIs('pic.complaints.report') ? 'active' : '' }}">

                    Laporan Kerusakan

                </a>


                <a href="{{ route('pic.schedule') }}"
                   class="menu-item {{ request()->routeIs('pic.schedule', 'schedule.index') ? 'active' : '' }}">

                    Jadwal Ruangan

                </a>

            @endif



            {{-- ================================================= --}}
            {{-- GURU --}}
            {{-- ================================================= --}}

            @if(Auth::user()->role === 'guru')

                <a href="{{ route('dashboard.guru') }}"
                   class="menu-item {{ request()->routeIs('dashboard.guru') ? 'active' : '' }}">

                    Dashboard

                </a>


                <a href="{{ route('guru.agenda') }}"
                   class="menu-item {{ (request()->routeIs('guru.agenda', 'guru.events') && !request()->routeIs('guru.events.create', 'guru.events.store')) ? 'active' : '' }}">

                    Agenda Saya

                </a>


                <a href="{{ route('guru.events.create') }}"
                   class="menu-item {{ request()->routeIs('guru.events.create', 'guru.events.store') ? 'active' : '' }}">

                    Ajukan Agenda

                </a>


                <a href="{{ route('guru.calendar') }}"
                   class="menu-item {{ request()->routeIs('guru.calendar') ? 'active' : '' }}">

                    Kalender Agenda

                </a>


                <a href="{{ route('jadwal.index') }}"
                   class="menu-item {{ request()->routeIs('jadwal.index') ? 'active' : '' }}">

                    Jadwal

                </a>


                <a href="{{ route('complaints.index') }}"
                   class="menu-item {{ (request()->routeIs('complaints.index') && !request()->routeIs('complaints.create', 'complaints.store')) ? 'active' : '' }}">

                    Pengaduan Kerusakan

                </a>


                <a href="{{ route('complaints.create') }}"
                   class="menu-item {{ request()->routeIs('complaints.create', 'complaints.store') ? 'active' : '' }}">

                    Buat Pengaduan

                </a>


                <a href="{{ route('guru.whatsapp.settings') }}"
                   class="menu-item {{ request()->routeIs('guru.whatsapp.*') ? 'active' : '' }}">

                    Notifikasi WhatsApp

                </a>

            @endif



            {{-- ================================================= --}}
            {{-- LOGOUT --}}
            {{-- ================================================= --}}

            <form
                action="{{ route('logout') }}"
                method="POST"
                style="margin: 0;"
            >

                @csrf

                <button
                    type="submit"
                    class="menu-item btn-logout-sidebar"
                >

                    Logout

                </button>

            </form>

        </div>

    </aside>



    {{-- ========================================================= --}}
    {{-- MAIN WRAPPER --}}
    {{-- ========================================================= --}}

    <div class="main-wrapper">


        {{-- ===================================================== --}}
        {{-- NAVBAR --}}
        {{-- ===================================================== --}}

        <header class="navbar-top">

            {{-- JUDUL HALAMAN --}}
            <div class="navbar-title-group">
                <button
                    type="button"
                    class="sidebar-toggle-btn"
                    id="sidebarToggle"
                    aria-label="Buka menu"
                    aria-expanded="false"
                    aria-controls="sidebar"
                >
                    ☰
                </button>

                <h2>
                    @yield('judul')
                </h2>

            </div>



            {{-- ================================================= --}}
            {{-- USER INFO --}}
            {{-- ================================================= --}}

           <div class="navbar-user">

    {{-- ================================================= --}}
    {{-- NOTIFIKASI --}}
    {{-- ================================================= --}}

    <div class="notification-wrapper">

        <button
            type="button"
            class="notification-button"
            id="notificationButton"
        >
            🔔

            @if($unreadNotifications->count() > 0)
                <span class="notification-badge">
                    {{ $unreadNotifications->count() }}
                </span>
            @endif
        </button>


        {{-- DROPDOWN NOTIFIKASI --}}
        <div
            class="notification-dropdown"
            id="notificationDropdown"
        >

            <div class="notification-header">
                <strong>Notifikasi</strong>
            </div>


            @forelse($unreadNotifications as $notification)

                <div class="notification-item">

                    <strong>
                        {{ $notification->title }}
                    </strong>

                    <p>
                        {{ $notification->message }}
                    </p>

                    <small>
                        {{ $notification->created_at->diffForHumans() }}
                    </small>

                </div>

            @empty

                <div class="notification-empty">
                    Tidak ada notifikasi baru.
                </div>

            @endforelse

        </div>

    </div>


    {{-- ================================================= --}}
    {{-- AVATAR --}}
    {{-- ================================================= --}}

    <div class="avatar">

        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}

    </div>


                {{-- NAMA + ROLE --}}
                <div class="navbar-user-meta">

                    <strong>
                        {{ Auth::user()->name }}
                    </strong>

                    <br>

                    <small>
                        {{ Auth::user()->role_label }}
                    </small>

                </div>

            </div>

        </header>



        {{-- ===================================================== --}}
        {{-- CONTENT --}}
        {{-- ===================================================== --}}

        <main class="content-body">

            @yield('content')

        </main>

    </div>

   <script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
        document.body.classList.remove('sidebar-open');
        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
    }

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (sidebarBackdrop) sidebarBackdrop.classList.add('show');
        document.body.classList.add('sidebar-open');
        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'true');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    if (sidebar) {
        sidebar.querySelectorAll('a.menu-item').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 1024) {
                    closeSidebar();
                }
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    });

    const button = document.getElementById('notificationButton');
    const dropdown = document.getElementById('notificationDropdown');

    if (!button || !dropdown) {
        return;
    }

    button.addEventListener('click', function (event) {

        event.stopPropagation();

        // Buka/tutup dropdown
        dropdown.classList.toggle('show');

        // Jika dropdown baru saja dibuka
        if (dropdown.classList.contains('show')) {

            // Cari badge angka merah
            const badge = button.querySelector('.notification-badge');

            // Jika ada notifikasi yang belum dibaca
            if (badge) {

                fetch("{{ route('notifications.read') }}", {

                    method: 'POST',

                    headers: {
                        'X-CSRF-TOKEN':
                            document.querySelector(
                                'meta[name="csrf-token"]'
                            ).getAttribute('content'),

                        'Accept': 'application/json',

                        'Content-Type': 'application/json'
                    },

                    body: JSON.stringify({})
                })

                .then(response => response.json())

                .then(data => {

                    if (data.success) {

                        // Hilangkan angka merah
                        badge.remove();

                    }

                })

                .catch(error => {

                    console.error(
                        'Gagal menandai notifikasi sebagai dibaca:',
                        error
                    );

                });
            }
        }
    });


    // Klik di luar dropdown → tutup
    document.addEventListener('click', function (event) {

        if (
            !dropdown.contains(event.target) &&
            !button.contains(event.target)
        ) {

            dropdown.classList.remove('show');

        }

    });

});
</script>

</body>

</html>
