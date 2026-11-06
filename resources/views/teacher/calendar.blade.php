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
        <span class="calendar-legend-title">Petunjuk Warna:</span>
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
                            <span>${escapeHtml(nationalDay.name)}</span>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="calendar-national-pill" title="Hari Penting Nasional: ${escapeHtml(nationalDay.name)}">
                            <span>${escapeHtml(nationalDay.name)}</span>
                        </div>
                    `;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | TAMPILKAN AGENDA (MAX 1 KARTU + "+X LAINNYA")
            |--------------------------------------------------------------------------
            */

            const maxVisible = 1;
            const visibleEvents = dayEvents.slice(0, maxVisible);
            const hiddenCount = dayEvents.length - maxVisible;

            visibleEvents.forEach(function (event) {

                const isAdmin = event.creator_role === 'admin';
                const roleClass = isAdmin ? 'event-admin' : 'event-guru';

                html += `
                    <div class="calendar-event ${roleClass}" role="button" tabindex="0" onclick="openDayDetailModal('${dateString}')" style="cursor: pointer;">

                        <strong class="event-title">
                            ${escapeHtml(event.title)}
                        </strong>

                        <span class="event-time">
                            ${escapeHtml(event.time)}
                        </span>

                        <small class="event-room">
                            ${escapeHtml(event.room)}
                        </small>

                    </div>
                `;

            });

            if (hiddenCount > 0) {
                html += `
                    <button type="button" class="calendar-more-btn" onclick="openDayDetailModal('${dateString}')">
                        +${hiddenCount} lainnya
                    </button>
                `;
            }


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
        if (text === null || text === undefined || text === '') {
            return '-';
        }
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }


    /*
    |--------------------------------------------------------------------------
    | MODAL LIHAT SEMUA AGENDA PADA TANGGAL
    |--------------------------------------------------------------------------
    */
    const dayEventsModal = document.getElementById('dayEventsModal');
    const dayModalTitle = document.getElementById('dayModalTitle');
    const dayModalSubtitle = document.getElementById('dayModalSubtitle');
    const dayModalList = document.getElementById('dayModalList');
    const closeDayModal = document.getElementById('closeDayModal');
    const btnDismissDayModal = document.getElementById('btnDismissDayModal');

    window.openDayDetailModal = function (dateString) {
        const matchingEvents = events.filter(function (event) {
            const eventStart = String(event.date).substring(0, 10);
            const eventEnd = event.end_date ? String(event.end_date).substring(0, 10) : eventStart;
            return dateString >= eventStart && dateString <= eventEnd;
        });

        const dateParts = dateString.split('-');
        let formattedDate = dateString;
        if (dateParts.length === 3) {
            const dayNum = parseInt(dateParts[2], 10);
            const monthIdx = parseInt(dateParts[1], 10) - 1;
            const yearNum = dateParts[0];
            formattedDate = dayNum + ' ' + (monthNames[monthIdx] || '') + ' ' + yearNum;
        }

        dayModalTitle.textContent = 'Agenda: ' + formattedDate;
        dayModalSubtitle.textContent = matchingEvents.length + ' kegiatan pada tanggal ini';

        let listHtml = '';
        if (matchingEvents.length === 0) {
            listHtml = '<div style="text-align: center; color: #64748b; padding: 20px;">Tidak ada agenda pada tanggal ini.</div>';
        } else {
            matchingEvents.forEach(function (event) {
                const isAdmin = event.creator_role === 'admin';
                const borderColor = isAdmin ? '#7c3aed' : '#2563eb';
                const bgColor = isAdmin ? '#f5f3ff' : '#eff6ff';
                const titleColor = isAdmin ? '#5b21b6' : '#1e40af';

                listHtml += `
                    <div style="margin-bottom: 12px; padding: 12px 14px; border-radius: 8px; background: ${bgColor}; border: 1px solid #e2e8f0; border-left: 4px solid ${borderColor};">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 6px;">
                            <strong style="font-size: 14.5px; color: ${titleColor}; line-height: 1.3;">${escapeHtml(event.title)}</strong>
                            <span style="font-size: 11px; padding: 2px 8px; border-radius: 9999px; background: #ffffff; color: #0284c7; font-weight: 600; border: 1px solid #bae6fd; white-space: nowrap;">${escapeHtml(event.status || 'Disetujui')}</span>
                        </div>
                        <div style="font-size: 12.5px; color: #475569; display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 4px;">
                            <span><strong>Waktu:</strong> ${escapeHtml(event.time)}</span>
                            <span><strong>Ruangan:</strong> ${escapeHtml(event.room)}</span>
                        </div>
                        ${event.description && event.description !== '-' ? `<div style="font-size: 12px; color: #64748b; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1; line-height: 1.4;">${escapeHtml(event.description)}</div>` : ''}
                    </div>
                `;
            });
        }

        dayModalList.innerHTML = listHtml;
        dayEventsModal.style.display = 'flex';
    };

    function hideDayDetailModal() {
        if (dayEventsModal) {
            dayEventsModal.style.display = 'none';
        }
    }

    if (closeDayModal) closeDayModal.addEventListener('click', hideDayDetailModal);
    if (btnDismissDayModal) btnDismissDayModal.addEventListener('click', hideDayDetailModal);
    if (dayEventsModal) {
        dayEventsModal.addEventListener('click', function (e) {
            if (e.target === dayEventsModal) {
                hideDayDetailModal();
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            hideDayDetailModal();
        }
    });


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

{{-- MODAL DETAIL AGENDA PER TANGGAL --}}
<div id="dayEventsModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center; padding:15px;">
    <div class="modal-content" style="background:#fff; border-radius:10px; max-width:520px; width:100%; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow:hidden;">
        <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
            <div>
                <h3 id="dayModalTitle" style="margin:0; font-size:16px; font-weight:700; color:#1e293b;">Daftar Agenda</h3>
                <small id="dayModalSubtitle" style="color:#64748b; font-size:12px;"></small>
            </div>
            <button type="button" id="closeDayModal" style="background:transparent; border:none; font-size:20px; cursor:pointer; color:#64748b; line-height:1; padding:4px 8px;">✕</button>
        </div>
        <div id="dayModalList" style="padding:16px 20px; overflow-y:auto; flex:1;"></div>
        <div style="padding:12px 20px; border-top:1px solid #e2e8f0; background:#f8fafc; text-align:right;">
            <button type="button" id="btnDismissDayModal" class="btn btn-back" style="padding:7px 16px; font-size:13px;">Tutup</button>
        </div>
    </div>
</div>

@endsection