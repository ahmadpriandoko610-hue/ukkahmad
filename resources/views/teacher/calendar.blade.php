@extends('layouts.app')

@section('title', 'Kalender Agenda')

@section('judul', 'Kalender Agenda')

@section('sidebar')

@if(Auth::user()->role === 'admin')
    <a href="{{ route('dashboard.admin') }}" class="menu-item">Dashboard</a>
    <a href="{{ route('rooms.index') }}" class="menu-item">Kelola Ruangan</a>
    <a href="{{ route('users.index') }}" class="menu-item">Kelola User</a>
    <a href="{{ route('events.index') }}" class="menu-item">Kelola Agenda</a>
    <a href="{{ route('admin.complaints') }}" class="menu-item">Pengaduan Kerusakan</a>
    <a href="{{ route('admin.calendar') }}" class="menu-item active">Kalender Agenda</a>
    <a href="{{ route('jadwal.index') }}" class="menu-item">Jadwal Ruangan</a>
@elseif(Auth::user()->role === 'pic')
    <a href="{{ route('dashboard.pic') }}" class="menu-item">Dashboard</a>
    <a href="{{ route('pic.agenda') }}" class="menu-item">Verifikasi Agenda</a>
    <a href="{{ route('pic.calendar') }}" class="menu-item active">Kalender Agenda</a>
    <a href="{{ route('pic.complaints') }}" class="menu-item">Pengaduan Kerusakan</a>
    <a href="{{ route('pic.schedule') }}" class="menu-item">Jadwal Ruangan</a>
@else
    <a href="{{ route('dashboard.guru') }}" class="menu-item">Dashboard</a>
    <a href="{{ route('agenda.create') }}" class="menu-item">Ajukan Agenda</a>
    <a href="{{ route('guru.agenda') }}" class="menu-item">Agenda Saya</a>
    <a href="{{ route('guru.calendar') }}" class="menu-item active">Kalender Agenda</a>
    <a href="{{ route('jadwal.index') }}" class="menu-item">Jadwal Ruangan</a>
    <a href="{{ route('complaints.index') }}" class="menu-item">Pengaduan Kerusakan</a>
    <a href="{{ route('complaints.create') }}" class="menu-item">Buat Pengaduan</a>
@endif

<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection

@section('content')

<div class="calendar-page">

    {{-- HEADER --}}
    <div class="calendar-header">

        <div>
            <h2>Kalender Agenda</h2>

            <p>
                Berikut adalah kalender agenda dan kegiatan yang telah disetujui.
            </p>
        </div>

    </div>

    {{-- PETUNJUK WARNA KALENDER --}}
    <div class="calendar-legend-box">
        <span class="calendar-legend-title">🎨 Petunjuk Warna:</span>
        <div class="calendar-legend-items">
            <span class="calendar-legend-item">
                <span class="calendar-legend-color holiday"></span>
                <strong>Hari Libur Nasional & Hari Minggu (Merah)</strong>
            </span>
            <span class="calendar-legend-item">
                <span class="calendar-legend-color national"></span>
                <strong>Hari Penting Nasional</strong>
            </span>
            <span class="calendar-legend-item">
                <span class="calendar-legend-color admin"></span>
                <strong>Hari Penting / Agenda Admin (Sekolah)</strong>
            </span>
            <span class="calendar-legend-item">
                <span class="calendar-legend-color guru"></span>
                <strong>Agenda Guru / Organisasi</strong>
            </span>
            <span class="calendar-legend-item">
                <span class="calendar-legend-color today"></span>
                <span>Hari Ini</span>
            </span>
        </div>
    </div>


    {{-- KALENDER --}}
    <div class="calendar-container">

        {{-- TOOLBAR --}}
        <div class="calendar-toolbar">

            <button
                type="button"
                id="prevMonth"
                class="calendar-nav">
                ‹
            </button>

            <h3 id="currentMonth"></h3>

            <button
                type="button"
                id="nextMonth"
                class="calendar-nav">
                ›
            </button>

        </div>


        {{-- NAMA HARI --}}
        <div class="calendar-weekdays">

            <div>Sen</div>
            <div>Sel</div>
            <div>Rab</div>
            <div>Kam</div>
            <div>Jum</div>
            <div>Sab</div>
            <div>Min</div>

        </div>


        {{-- TANGGAL --}}
        <div
            id="calendarDays"
            class="calendar-days">
        </div>

    </div>

</div>


{{-- ========================================================= --}}
{{-- DATA AGENDA DARI LARAVEL --}}
{{-- ========================================================= --}}

<div
    id="calendar-data"
    data-events='@json($events)'
    data-national-holidays='@json($nationalHolidays ?? [])'
    style="display: none;">
</div>


{{-- ========================================================= --}}
{{-- JAVASCRIPT KALENDER --}}
{{-- ========================================================= --}}

<script>

document.addEventListener('DOMContentLoaded', function () {

    const calendarData =
        document.getElementById('calendar-data');

    const calendarDays =
        document.getElementById('calendarDays');

    const currentMonth =
        document.getElementById('currentMonth');

    const prevMonth =
        document.getElementById('prevMonth');

    const nextMonth =
        document.getElementById('nextMonth');


    /*
    |--------------------------------------------------------------------------
    | AMBIL DATA AGENDA DARI LARAVEL
    |--------------------------------------------------------------------------
    */

    let events = [];
    let nationalHolidays = { fixed: {}, variable: {} };

    try {
        events = JSON.parse(calendarData.dataset.events);
    } catch (error) {
        console.error('Data agenda gagal dibaca:', error);
        events = [];
    }

    try {
        if (calendarData.dataset.nationalHolidays) {
            nationalHolidays = JSON.parse(calendarData.dataset.nationalHolidays);
        }
    } catch (error) {
        console.error('Data hari libur nasional gagal dibaca:', error);
    }

    function getNationalDayInfo(year, month, day) {
        const monthStr = String(month + 1).padStart(2, '0');
        const dayStr = String(day).padStart(2, '0');
        const fullDate = year + '-' + monthStr + '-' + dayStr;
        const monthDay = monthStr + '-' + dayStr;

        if (nationalHolidays.variable && nationalHolidays.variable[fullDate]) {
            return nationalHolidays.variable[fullDate];
        }
        if (nationalHolidays.fixed && nationalHolidays.fixed[monthDay]) {
            return nationalHolidays.fixed[monthDay];
        }
        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | TANGGAL SAAT INI
    |--------------------------------------------------------------------------
    */

    let currentDate = new Date();


    /*
    |--------------------------------------------------------------------------
    | NAMA BULAN
    |--------------------------------------------------------------------------
    */

    const monthNames = [
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];


    /*
    |--------------------------------------------------------------------------
    | RENDER CALENDAR
    |--------------------------------------------------------------------------
    */

    function renderCalendar() {

        calendarDays.innerHTML = '';


        const year =
            currentDate.getFullYear();

        const month =
            currentDate.getMonth();


        /*
        |--------------------------------------------------------------------------
        | JUDUL BULAN
        |--------------------------------------------------------------------------
        */

        currentMonth.textContent =
            monthNames[month] + ' ' + year;


        /*
        |--------------------------------------------------------------------------
        | HARI PERTAMA
        |--------------------------------------------------------------------------
        */

        const firstDay =
            new Date(year, month, 1);


        /*
        |--------------------------------------------------------------------------
        | SENIN = 0
        | SELASA = 1
        | ...
        | MINGGU = 6
        |--------------------------------------------------------------------------
        */

        let startDay =
            firstDay.getDay() - 1;


        if (startDay < 0) {
            startDay = 6;
        }


        /*
        |--------------------------------------------------------------------------
        | JUMLAH HARI
        |--------------------------------------------------------------------------
        */

        const daysInMonth =
            new Date(
                year,
                month + 1,
                0
            ).getDate();


        /*
        |--------------------------------------------------------------------------
        | KOTAK KOSONG
        |--------------------------------------------------------------------------
        */

        for (
            let i = 0;
            i < startDay;
            i++
        ) {

            const emptyDay =
                document.createElement('div');

            emptyDay.className =
                'calendar-day empty';

            calendarDays.appendChild(
                emptyDay
            );

        }


        /*
        |--------------------------------------------------------------------------
        | TANGGAL
        |--------------------------------------------------------------------------
        */

        for (
            let day = 1;
            day <= daysInMonth;
            day++
        ) {

            const dayElement =
                document.createElement('div');

            dayElement.className =
                'calendar-day';


            /*
            |--------------------------------------------------------------------------
            | FORMAT YYYY-MM-DD
            |--------------------------------------------------------------------------
            */

            const dateString =
                year +
                '-' +
                String(month + 1).padStart(2, '0') +
                '-' +
                String(day).padStart(2, '0');


            /*
            |--------------------------------------------------------------------------
            | TANGGAL HARI INI
            |--------------------------------------------------------------------------
            */

            const today =
                new Date();

            const todayString =
                today.getFullYear() +
                '-' +
                String(
                    today.getMonth() + 1
                ).padStart(2, '0') +
                '-' +
                String(
                    today.getDate()
                ).padStart(2, '0');


            if (dateString === todayString) {

                dayElement.classList.add(
                    'today'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CARI AGENDA PADA TANGGAL INI
            |--------------------------------------------------------------------------
            */

            const dayEvents =
                events.filter(function (event) {

                    const start = String(event.date).substring(0, 10);
                    const end = event.end_date ? String(event.end_date).substring(0, 10) : start;
                    return dateString >= start && dateString <= end;

                });

            /*
            |--------------------------------------------------------------------------
            | HARI MINGGU & HARI LIBUR / PENTING NASIONAL
            |--------------------------------------------------------------------------
            */
            const isSunday = new Date(year, month, day).getDay() === 0;
            if (isSunday) {
                dayElement.classList.add('is-sunday');
            }

            const nationalDay = getNationalDayInfo(year, month, day);
            const isHoliday = isSunday || (nationalDay && nationalDay.is_holiday);
            if (isHoliday) {
                dayElement.classList.add('is-holiday');
            }

            /*
            |--------------------------------------------------------------------------
            | WARNA TANGGAL JIKA ADA EVENT / HARI PENTING / HARI LIBUR
            |--------------------------------------------------------------------------
            */
            let dateBadgeClass = '';
            if (isHoliday) {
                dateBadgeClass = 'date-badge-holiday';
            }

            if (dayEvents.length > 0) {
                dayElement.classList.add('has-event');
                const hasAdmin = dayEvents.some(function(e) { return e.creator_role === 'admin'; });
                const hasGuru = dayEvents.some(function(e) { return e.creator_role !== 'admin'; });
                if (hasAdmin && hasGuru) {
                    dayElement.classList.add('has-mixed-event');
                    if (!isHoliday) dateBadgeClass = 'date-badge-mixed';
                } else if (hasAdmin) {
                    dayElement.classList.add('has-admin-event');
                    if (!isHoliday) dateBadgeClass = 'date-badge-admin';
                } else {
                    dayElement.classList.add('has-guru-event');
                    if (!isHoliday) dateBadgeClass = 'date-badge-guru';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | NOMOR TANGGAL & PILL HARI PENTING / LIBUR NASIONAL
            |--------------------------------------------------------------------------
            */

            let html = `
                <div class="calendar-date ${dateBadgeClass}">
                    ${day}
                </div>
            `;

            if (nationalDay) {
                if (nationalDay.is_holiday) {
                    html += `
                        <div class="calendar-holiday-pill" title="Hari Libur Nasional: ${escapeHtml(nationalDay.name)}">
                            <span class="holiday-icon">🔴</span>
                            <span>${escapeHtml(nationalDay.name)}</span>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="calendar-national-pill" title="Hari Penting Nasional: ${escapeHtml(nationalDay.name)}">
                            <span class="national-icon">🇮🇩</span>
                            <span>${escapeHtml(nationalDay.name)}</span>
                        </div>
                    `;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | TAMPILKAN AGENDA BERDASARKAN WARNA PEMBUAT (ADMIN VS GURU)
            |--------------------------------------------------------------------------
            */

            dayEvents.forEach(function (event) {

                const isAdmin = event.creator_role === 'admin';
                const roleClass = isAdmin ? 'event-admin' : 'event-guru';
                const roleBadge = isAdmin
                    ? '<span class="event-role-tag admin">⭐ Admin / Sekolah</span>'
                    : '<span class="event-role-tag guru">👤 Guru / Organisasi</span>';

                html += `
                    <div class="calendar-event ${roleClass}">

                        ${roleBadge}

                        <strong class="event-title">
                            ${escapeHtml(event.title)}
                        </strong>

                        <span class="event-time">
                            🕐 ${escapeHtml(event.time)}
                        </span>

                        <small class="event-room">
                            📍 ${escapeHtml(event.room)}
                        </small>

                    </div>
                `;

            });


            dayElement.innerHTML =
                html;


            calendarDays.appendChild(
                dayElement
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(text) {

        if (
            text === null ||
            text === undefined ||
            text === ''
        ) {

            return '-';

        }


        const div =
            document.createElement('div');

        div.textContent =
            String(text);

        return div.innerHTML;

    }


    /*
    |--------------------------------------------------------------------------
    | TOMBOL BULAN SEBELUMNYA
    |--------------------------------------------------------------------------
    */

    prevMonth.addEventListener(
        'click',
        function () {

            currentDate.setMonth(
                currentDate.getMonth() - 1
            );

            renderCalendar();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | TOMBOL BULAN BERIKUTNYA
    |--------------------------------------------------------------------------
    */

    nextMonth.addEventListener(
        'click',
        function () {

            currentDate.setMonth(
                currentDate.getMonth() + 1
            );

            renderCalendar();

        }
    );


    /*
    |--------------------------------------------------------------------------
    | TAMPILKAN KALENDER
    |--------------------------------------------------------------------------
    */

    renderCalendar();

});

</script>

@endsection