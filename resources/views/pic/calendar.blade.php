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

    <div class="calendar-container">

        <div class="calendar-toolbar">
            <button type="button" id="prevMonth" class="calendar-nav">‹</button>
            <h3 id="currentMonth"></h3>
            <button type="button" id="nextMonth" class="calendar-nav">›</button>
        </div>

        <div class="calendar-scroll-wrapper">
            <div class="calendar-grid-inner">
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
            </div>
        </div>
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
                return dateString >= eventStart && dateString <= eventEnd;
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

            const maxVisible = 1;
            const visibleEvents = dayEvents.slice(0, maxVisible);
            const hiddenCount = dayEvents.length - maxVisible;

            visibleEvents.forEach(function (event) {
                visibleEventCount++;
                const isAdmin = event.creator_role === 'admin';
                const roleClass = isAdmin ? 'event-admin' : 'event-guru';

                html += `
                    <div class="calendar-event ${roleClass}" role="button" tabindex="0" onclick="openDayDetailModal('${dateString}')" style="cursor: pointer;">
                        <strong class="event-title">${escapeHtml(event.title)}</strong>
                        <span class="event-time">${escapeHtml(event.time)}</span>
                        <small class="event-room">${escapeHtml(event.room)}</small>
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

            dayElement.innerHTML = html;
            calendarDays.appendChild(dayElement);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MODAL LIHAT SEMUA AGENDA PADA TANGGAL (PIC)
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

{{-- MODAL DETAIL AGENDA PER TANGGAL (PIC) --}}
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
