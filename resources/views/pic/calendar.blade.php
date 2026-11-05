@extends('layouts.app')

@section('title', 'Kalender Agenda PIC')

@section('judul', 'Kalender Agenda')

@section('sidebar')

<a href="{{ route('dashboard.pic') }}" class="menu-item">
    Dashboard
</a>

<a href="{{ route('pic.agenda') }}" class="menu-item">
    Verifikasi Agenda
</a>

<a href="{{ route('pic.calendar') }}" class="menu-item active">
    Kalender Agenda
</a>

<a href="{{ route('pic.complaints') }}" class="menu-item">
    Pengaduan Kerusakan
</a>

<a href="{{ route('pic.schedule') }}" class="menu-item">
    Jadwal Ruangan
</a>

<form action="{{ route('logout') }}" method="POST">
    @csrf
    <button type="submit" class="menu-item btn-logout-sidebar">
        Logout
    </button>
</form>

@endsection

@section('content')

<div class="calendar-page">

    <div class="calendar-header">
        <div>
            <h2>Kalender Agenda Ruangan</h2>
            <p>
                Berikut adalah kalender agenda yang telah disetujui untuk semua ruangan.
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

    <div class="calendar-container">

        <div class="calendar-search">
            <input
                type="search"
                id="calendarSearch"
                class="calendar-search-input"
                placeholder="Cari agenda, ruangan, atau tanggal..."
                aria-label="Cari agenda pada kalender"
            >
        </div>

        <div class="calendar-toolbar">
            <button type="button" id="prevMonth" class="calendar-nav">‹</button>
            <h3 id="currentMonth"></h3>
            <button type="button" id="nextMonth" class="calendar-nav">›</button>
        </div>

        <div class="calendar-weekdays">
            <div>Sen</div>
            <div>Sel</div>
            <div>Rab</div>
            <div>Kam</div>
            <div>Jum</div>
            <div>Sab</div>
            <div>Min</div>
        </div>

        <div id="calendarDays" class="calendar-days"></div>
        <p id="calendarSearchEmpty" class="calendar-search-empty" role="status" hidden>
            Tidak ada agenda yang cocok pada bulan ini.
        </p>
    </div>

</div>

<div id="calendar-data" data-events='@json($events)' data-national-holidays='@json($nationalHolidays ?? [])' style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarData = document.getElementById('calendar-data');
    const calendarDays = document.getElementById('calendarDays');
    const currentMonth = document.getElementById('currentMonth');
    const prevMonth = document.getElementById('prevMonth');
    const nextMonth = document.getElementById('nextMonth');
    const calendarSearch = document.getElementById('calendarSearch');
    const calendarSearchEmpty = document.getElementById('calendarSearchEmpty');

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
        console.error('Data hari libur gagal dibaca:', error);
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

    let currentDate = new Date();
    let searchTerm = '';

    const monthNames = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderCalendar() {
        calendarDays.innerHTML = '';

        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        currentMonth.textContent = monthNames[month] + ' ' + year;

        const firstDay = new Date(year, month, 1);
        let startDay = firstDay.getDay() - 1;
        if (startDay < 0) startDay = 6;

        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let visibleEventCount = 0;

        for (let i = 0; i < startDay; i++) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day empty';
            calendarDays.appendChild(emptyDay);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dayElement = document.createElement('div');
            dayElement.className = 'calendar-day';

            const dateString = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const today = new Date();
            const todayString = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

            if (dateString === todayString) {
                dayElement.classList.add('today');
            }

            const dayEvents = events.filter(function (event) {
                const eventStart = String(event.date).substring(0, 10);
                const eventEnd = event.end_date ? String(event.end_date).substring(0, 10) : eventStart;
                const matchesDate = dateString >= eventStart && dateString <= eventEnd;
                const searchableText = [
                    event.title,
                    event.time,
                    event.room,
                    event.description,
                    event.date,
                    event.end_date,
                    event.date_range
                ].join(' ').toLocaleLowerCase('id');

                return matchesDate && searchableText.includes(searchTerm);
            });

            const isSunday = new Date(currentDate.getFullYear(), currentDate.getMonth(), day).getDay() === 0;
            if (isSunday) {
                dayElement.classList.add('is-sunday');
            }

            const nationalDay = getNationalDayInfo(currentDate.getFullYear(), currentDate.getMonth(), day);
            const isHoliday = isSunday || (nationalDay && nationalDay.is_holiday);
            if (isHoliday) {
                dayElement.classList.add('is-holiday');
            }

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

            let html = `<div class="calendar-date ${dateBadgeClass}">${day}</div>`;

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

            dayEvents.forEach(function (event) {
                visibleEventCount++;
                const isAdmin = event.creator_role === 'admin';
                const roleClass = isAdmin ? 'event-admin' : 'event-guru';
                const roleBadge = isAdmin
                    ? '<span class="event-role-tag admin">⭐ Admin / Sekolah</span>'
                    : '<span class="event-role-tag guru">👤 Guru / Organisasi</span>';

                html += `
                    <div class="calendar-event ${roleClass}">
                        ${roleBadge}
                        <strong class="event-title">${escapeHtml(event.title)}</strong>
                        <span class="event-time">🕐 ${escapeHtml(event.time)}</span>
                        <small class="event-room">📍 ${escapeHtml(event.room)}</small>
                    </div>
                `;
            });

            dayElement.innerHTML = html;
            calendarDays.appendChild(dayElement);
        }

        calendarSearchEmpty.hidden = searchTerm === '' || visibleEventCount > 0;
    }

    calendarSearch.addEventListener('input', function () {
        searchTerm = calendarSearch.value.trim().toLocaleLowerCase('id');
        renderCalendar();
    });

    prevMonth.addEventListener('click', function () {
        currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() - 1, 1);
        renderCalendar();
    });

    nextMonth.addEventListener('click', function () {
        currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 1);
        renderCalendar();
    });

    renderCalendar();
});
</script>

@endsection
