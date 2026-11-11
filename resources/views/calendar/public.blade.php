<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kalender Agenda Sekolah</title>

    @vite(['resources/css/app.css'])
</head>

<body class="public-calendar-page">

    <main class="calendar-container">

        {{-- =========================================================
             HEADER
        ========================================================== --}}
        <header class="calendar-top">

            <div class="calendar-brand">

                <div>
                    <h1>Kalender Agenda Sekolah</h1>

                    <p>
                        Informasi agenda sekolah yang telah disetujui.
                    </p>
                </div>

            </div>

            <a href="{{ route('login') }}" class="login-button">
                Login
            </a>

        </header>


        {{-- =========================================================
             CALENDAR CARD
        ========================================================== --}}
        <div class="calendar-legend-box" style="margin: 0 0 16px 0;">
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

        {{-- =========================================================
             CALENDAR CONTAINER (POSISI SAMA DENGAN GURU, PIC & ADMIN)
        ========================================================== --}}
        <section class="calendar-container">

            {{-- TOOLBAR --}}
            <div class="calendar-toolbar">
                <button
                    type="button"
                    id="previous"
                    class="calendar-nav"
                    aria-label="Bulan Sebelumnya"
                >‹</button>

                <h3 id="monthLabel">-</h3>

                <button
                    type="button"
                    id="next"
                    class="calendar-nav"
                    aria-label="Bulan Berikutnya"
                >›</button>
            </div>

            <div class="calendar-scroll-wrapper">
                <div class="calendar-grid-inner">
                    {{-- NAMA HARI (SENIN S.D. MINGGU DI KANAN) --}}
                    <div class="calendar-weekdays">
                        <div>Sen</div>
                        <div>Sel</div>
                        <div>Rab</div>
                        <div>Kam</div>
                        <div>Jum</div>
                        <div>Sab</div>
                        <div>Min</div>
                    </div>

                    {{-- GRID KALENDER TANGGAL --}}
                    <div
                        id="calendar"
                        class="calendar-days"
                    ></div>
                </div>
            </div>

            {{-- PESAN KOSONG --}}
            <div
                id="emptyMessage"
                class="empty-message"
                hidden
            >
                <h3>Tidak ada agenda</h3>
                <p>Belum ada agenda yang disetujui pada bulan ini.</p>
            </div>

        </section>


        {{-- =========================================================
             KETERANGAN
        ========================================================== --}}
        <div class="calendar-information">

            <span class="information-dot"></span>

            <span>
                Agenda yang tampil adalah agenda yang telah disetujui.
            </span>

        </div>

    </main>


    {{-- =========================================================
         MODAL DETAIL AGENDA

         PENTING:
         Modal diletakkan SEBELUM JavaScript supaya
         getElementById() tidak menghasilkan null.
    ========================================================== --}}
    <div
        id="eventModal"
        class="event-modal"
        hidden
    >

        <div
            class="event-modal-overlay"
            id="eventModalOverlay"
        ></div>


        <div
            class="event-modal-content"
            role="dialog"
            aria-modal="true"
            aria-labelledby="eventModalTitle"
        >

            <button
                type="button"
                id="closeEventModal"
                class="event-modal-close"
                aria-label="Tutup"
            >
                ×
            </button>


            <div class="event-modal-header">

                <div>

                    <h2 id="eventModalTitle">
                        Detail Agenda
                    </h2>

                    <span class="event-modal-status" id="eventModalStatus">
                        Disetujui
                    </span>

                </div>

            </div>


            <div class="event-modal-body">

                {{-- TANGGAL --}}
                <div class="event-detail-item">

                    <div>

                        <small>
                            Tanggal
                        </small>

                        <strong id="eventModalDate">
                            -
                        </strong>

                    </div>

                </div>


                {{-- WAKTU --}}
                <div class="event-detail-item">

                    <div>

                        <small>
                            Waktu
                        </small>

                        <strong id="eventModalTime">
                            -
                        </strong>

                    </div>

                </div>

                {{-- KATEGORI / PENGAJU --}}
                <div class="event-detail-item">

                    <div>

                        <small>
                            Kategori / Pengaju
                        </small>

                        <strong id="eventModalRequester">
                            -
                        </strong>

                    </div>

                </div>


                {{-- RUANGAN --}}
                <div class="event-detail-item">

                    <div>

                        <small>
                            Ruangan
                        </small>

                        <strong id="eventModalRoom">
                            -
                        </strong>

                    </div>

                </div>


                {{-- KETERANGAN --}}
                <div class="event-detail-description">

                    <small>
                        Keterangan
                    </small>

                    <p id="eventModalDescription">
                        -
                    </p>

                </div>

            </div>

        </div>

    </div>

    {{-- =========================================================
         MODAL LIHAT SEMUA AGENDA PADA TANGGAL (PUBLIK)
    ========================================================== --}}
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
                <button type="button" id="btnDismissDayModal" class="btn btn-back" style="padding:7px 16px; font-size:13px; cursor:pointer;">Tutup</button>
            </div>
        </div>
    </div>


    {{-- =========================================================
         DATA AGENDA DARI LARAVEL
    ========================================================== --}}
    <script
        id="calendar-data"
        type="application/json"
    >@json($events)</script>

    <script
        id="national-holidays-data"
        type="application/json"
    >@json($nationalHolidays ?? [])</script>


    {{-- =========================================================
         JAVASCRIPT
    ========================================================== --}}
    <script>

        /* =========================================================
           DATA AGENDA
        ========================================================== */

        const dataElement =
            document.getElementById('calendar-data');
        const holidaysElement =
            document.getElementById('national-holidays-data');

        let allEvents = [];
        let nationalHolidays = { fixed: {}, variable: {} };

        try {

            allEvents = JSON.parse(
                dataElement.textContent || '[]'
            );

        } catch (error) {

            console.error(
                'Data agenda tidak dapat dibaca:',
                error
            );

            allEvents = [];

        }

        try {
            if (holidaysElement && holidaysElement.textContent) {
                nationalHolidays = JSON.parse(holidaysElement.textContent);
            }
        } catch (error) {
            console.error('Data hari libur tidak dapat dibaca:', error);
        }

        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
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

        let events = [...allEvents];


        /* =========================================================
           NAMA BULAN
        ========================================================== */

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


        /* =========================================================
           NAMA HARI
        ========================================================== */

        const dayNames = [
            'Minggu',
            'Senin',
            'Selasa',
            'Rabu',
            'Kamis',
            'Jumat',
            'Sabtu'
        ];


        /* =========================================================
           BULAN AKTIF
        ========================================================== */

        let activeMonth = new Date();

        activeMonth.setDate(1);


        /* =========================================================
           ELEMENT HTML
        ========================================================== */

        const calendar =
            document.getElementById('calendar');

        const monthLabel =
            document.getElementById('monthLabel');

        const emptyMessage =
            document.getElementById('emptyMessage');

        const emptyTitle =
            emptyMessage.querySelector('h3');

        const emptyText =
            emptyMessage.querySelector('p');


        /* =========================================================
           MODAL ELEMENT
        ========================================================== */

        const eventModal =
            document.getElementById('eventModal');

        const eventModalOverlay =
            document.getElementById('eventModalOverlay');

        const closeEventModal =
            document.getElementById('closeEventModal');

        const eventModalTitle =
            document.getElementById('eventModalTitle');

        const eventModalDate =
            document.getElementById('eventModalDate');

        const eventModalTime =
            document.getElementById('eventModalTime');

        const eventModalRoom =
            document.getElementById('eventModalRoom');

        const eventModalDescription =
            document.getElementById('eventModalDescription');


        /* =========================================================
           FORMAT TANGGAL
        ========================================================== */

        function formatDate(dateString) {

            if (!dateString) {
                return '';
            }

            const date = new Date(
                dateString + 'T00:00:00'
            );

            if (Number.isNaN(date.getTime())) {
                return dateString;
            }

            return date.toLocaleDateString(
                'id-ID',
                {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }
            );
        }


        /* =========================================================
           CEK HARI INI
        ========================================================== */

        function isToday(year, month, day) {

            const today = new Date();

            return (
                today.getFullYear() === year &&
                today.getMonth() === month &&
                today.getDate() === day
            );
        }


        /* =========================================================
           MODAL DETAIL AGENDA
        ========================================================== */

        function showEventDetail(item) {

            eventModalTitle.textContent =
                item.title || 'Agenda';

            const eventModalStatus = document.getElementById('eventModalStatus');
            if (eventModalStatus) {
                eventModalStatus.textContent = item.status || 'Disetujui';
            }

            eventModalDate.textContent =
                item.date_range || formatDate(item.date);

            eventModalTime.textContent =
                item.time || '-';

            const eventModalRequester = document.getElementById('eventModalRequester');
            if (eventModalRequester) {
                eventModalRequester.innerHTML = item.creator_role === 'admin'
                    ? '<span class="event-role-tag admin">Agenda Admin / Sekolah</span>'
                    : '<span class="event-role-tag guru">' + escapeHtml(item.requester_name || 'Guru / Organisasi') + '</span>';
            }

            eventModalRoom.textContent =
                item.room || 'Tanpa ruangan';

            eventModalDescription.textContent =
                item.description || '-';

            eventModal.hidden = false;

            document.body.classList.add(
                'modal-open'
            );

            closeEventModal.focus();
        }


        function hideEventDetail() {

            eventModal.hidden = true;

            document.body.classList.remove(
                'modal-open'
            );
        }

        /* =========================================================
           DAY EVENTS MODAL (LIHAT SEMUA AGENDA PADA TANGGAL)
        ========================================================== */

        function openDayDetailModal(dateString, matchingEvents) {
            const dayModal = document.getElementById('dayEventsModal');
            const dayTitle = document.getElementById('dayModalTitle');
            const daySub = document.getElementById('dayModalSubtitle');
            const dayList = document.getElementById('dayModalList');

            if (!dayModal || !dayTitle || !daySub || !dayList) return;

            if (!matchingEvents || !Array.isArray(matchingEvents)) {
                matchingEvents = events.filter(function(item) {
                    const start = item.date ? String(item.date).substring(0, 10) : '';
                    const end = item.end_date ? String(item.end_date).substring(0, 10) : start;
                    return dateString >= start && dateString <= end;
                });
            }

            const dateParts = dateString.split('-');
            let formattedDate = dateString;
            if (dateParts.length === 3) {
                const dayNum = parseInt(dateParts[2], 10);
                const monthIdx = parseInt(dateParts[1], 10) - 1;
                const yearNum = dateParts[0];
                formattedDate = dayNum + ' ' + (monthNames[monthIdx] || '') + ' ' + yearNum;
            }

            dayTitle.textContent = 'Agenda: ' + formattedDate;
            daySub.textContent = matchingEvents.length + ' kegiatan pada tanggal ini';

            dayList.innerHTML = '';
            if (matchingEvents.length === 0) {
                dayList.innerHTML = '<div style="text-align: center; color: #64748b; padding: 20px;">Tidak ada agenda pada tanggal ini.</div>';
            } else {
                matchingEvents.forEach(function (event) {
                    const isAdmin = event.creator_role === 'admin';
                    const borderColor = isAdmin ? '#7c3aed' : '#2563eb';
                    const bgColor = isAdmin ? '#f5f3ff' : '#eff6ff';
                    const titleColor = isAdmin ? '#5b21b6' : '#1e40af';

                    const itemEl = document.createElement('div');
                    itemEl.style.cssText = `margin-bottom: 12px; padding: 12px 14px; border-radius: 8px; background: ${bgColor}; border: 1px solid #e2e8f0; border-left: 4px solid ${borderColor}; cursor: pointer;`;

                    itemEl.innerHTML = `
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 6px;">
                            <strong style="font-size: 14.5px; color: ${titleColor}; line-height: 1.3;">${escapeHtml(event.title || 'Agenda')}</strong>
                            <span style="font-size: 11px; padding: 2px 8px; border-radius: 9999px; background: #ffffff; color: #0284c7; font-weight: 600; border: 1px solid #bae6fd; white-space: nowrap;">${escapeHtml(event.status || 'Disetujui')}</span>
                        </div>
                        <div style="font-size: 12.5px; color: #475569; display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 4px;">
                            <span><strong>Waktu:</strong> ${escapeHtml(event.time || '-')}</span>
                            <span><strong>Ruangan:</strong> ${escapeHtml(event.room || 'Tanpa ruangan')}</span>
                        </div>
                        ${event.description && event.description !== '-' ? `<div style="font-size: 12px; color: #64748b; margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1; line-height: 1.4;">${escapeHtml(event.description)}</div>` : ''}
                    `;

                    itemEl.addEventListener('click', function() {
                        hideDayDetailModal();
                        const fullItem = allEvents.find(e => e.id === event.id) || event;
                        showEventDetail(fullItem);
                    });

                    dayList.appendChild(itemEl);
                });
            }

            dayModal.style.display = 'flex';
        }

        window.openDayDetailModal = openDayDetailModal;

        function hideDayDetailModal() {
            const dayModal = document.getElementById('dayEventsModal');
            if (dayModal) {
                dayModal.style.display = 'none';
            }
        }

        const closeDayModal = document.getElementById('closeDayModal');
        const btnDismissDayModal = document.getElementById('btnDismissDayModal');
        const dayEventsModal = document.getElementById('dayEventsModal');

        if (closeDayModal) closeDayModal.addEventListener('click', hideDayDetailModal);
        if (btnDismissDayModal) btnDismissDayModal.addEventListener('click', hideDayDetailModal);
        if (dayEventsModal) {
            dayEventsModal.addEventListener('click', function(e) {
                if (e.target === dayEventsModal) {
                    hideDayDetailModal();
                }
            });
        }


        /* =========================================================
           EVENT MODAL
        ========================================================== */

        closeEventModal.addEventListener(
            'click',
            hideEventDetail
        );


        eventModalOverlay.addEventListener(
            'click',
            hideEventDetail
        );


        document.addEventListener(
            'keydown',
            function(event) {

                if (
                    event.key === 'Escape' &&
                    !eventModal.hidden
                ) {

                    hideEventDetail();

                }

            }
        );


        /* =========================================================
           RENDER CALENDAR
        ========================================================== */

        function renderCalendar() {

            const year =
                activeMonth.getFullYear();

            const month =
                activeMonth.getMonth();


            /* -----------------------------------------------------
               JUDUL BULAN
            ----------------------------------------------------- */

            monthLabel.textContent =
                monthNames[month] + ' ' + year;


            /* -----------------------------------------------------
               BERSIHKAN KALENDER
            ----------------------------------------------------- */

            calendar.innerHTML = '';


                        /* -----------------------------------------------------
               HARI PERTAMA BULAN (SENIN = 0 ... MINGGU = 6)
            ----------------------------------------------------- */

            const firstDay =
                new Date(year, month, 1);

            let startDay =
                firstDay.getDay() - 1;

            if (startDay < 0) {
                startDay = 6;
            }


            /* -----------------------------------------------------
               JUMLAH HARI DALAM BULAN
            ----------------------------------------------------- */

            const lastDate =
                new Date(year, month + 1, 0).getDate();


            /* -----------------------------------------------------
               KOTAK KOSONG SEBELUM TANGGAL 1
            ----------------------------------------------------- */

            for (let i = 0; i < startDay; i++) {

                const emptyDay =
                    document.createElement('div');

                emptyDay.className =
                    'calendar-day empty';

                calendar.appendChild(
                    emptyDay
                );

            }


            /* -----------------------------------------------------
               JUMLAH AGENDA YANG DITAMPILKAN
            ----------------------------------------------------- */

            let displayedEvents = 0;


            /* -----------------------------------------------------
               BUAT SEMUA TANGGAL
            ----------------------------------------------------- */

            for (
                let day = 1;
                day <= lastDate;
                day++
            ) {

                const date =
                    `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;


                /* -------------------------------------------------
                   CARI AGENDA PADA TANGGAL INI
                ------------------------------------------------- */

                const dailyEvents =
                    events.filter(
                        function(item) {

                            const start = item.date ? String(item.date).substring(0, 10) : '';
                            const end = item.end_date ? String(item.end_date).substring(0, 10) : start;
                            return date >= start && date <= end;

                        }
                    );


                displayedEvents +=
                    dailyEvents.length;


                /* -------------------------------------------------
                   CELL TANGGAL
                ------------------------------------------------- */

                const dayElement =
                    document.createElement('div');

                dayElement.className =
                    'calendar-day';


                /* -------------------------------------------------
                   HARI INI
                ------------------------------------------------- */

                if (
                    isToday(
                        year,
                        month,
                        day
                    )
                ) {

                    dayElement.classList.add(
                        'today'
                    );

                }


                /* -------------------------------------------------
                   HARI MINGGU & HARI LIBUR / PENTING NASIONAL
                ------------------------------------------------- */
                const isSunday = new Date(year, month, day).getDay() === 0;
                if (isSunday) {
                    dayElement.classList.add('is-sunday');
                }

                const nationalDay = getNationalDayInfo(year, month, day);
                const isHoliday = isSunday || (nationalDay && nationalDay.is_holiday);
                if (isHoliday) {
                    dayElement.classList.add('is-holiday');
                }

                /* -------------------------------------------------
                   WARNA TANGGAL JIKA ADA EVENT / HARI PENTING / LIBUR
                ------------------------------------------------- */

                let pubDateBadgeClass = '';
                if (isHoliday) {
                    pubDateBadgeClass = 'date-badge-holiday';
                }

                if (
                    dailyEvents.length > 0
                ) {

                    dayElement.classList.add(
                        'has-event'
                    );

                    const hasAdmin = dailyEvents.some(function(e) { return e.creator_role === 'admin'; });
                    const hasGuru = dailyEvents.some(function(e) { return e.creator_role !== 'admin'; });
                    if (hasAdmin && hasGuru) {
                        dayElement.classList.add('has-mixed-event');
                        if (!isHoliday) pubDateBadgeClass = 'date-badge-mixed';
                    } else if (hasAdmin) {
                        dayElement.classList.add('has-admin-event');
                        if (!isHoliday) pubDateBadgeClass = 'date-badge-admin';
                    } else {
                        dayElement.classList.add('has-guru-event');
                        if (!isHoliday) pubDateBadgeClass = 'date-badge-guru';
                    }

                }


                /* -------------------------------------------------
                   NOMOR TANGGAL
                ------------------------------------------------- */

                const dayNumber =
                    document.createElement('div');

                dayNumber.className = 'calendar-date ' + pubDateBadgeClass;

                dayNumber.textContent =
                    day;

                dayElement.appendChild(
                    dayNumber
                );

                if (nationalDay) {
                    const natPill = document.createElement('div');
                    if (nationalDay.is_holiday) {
                        natPill.className = 'calendar-holiday-pill';
                        natPill.title = 'Hari Libur Nasional: ' + nationalDay.name;
                        natPill.innerHTML = '<span>' + escapeHtml(nationalDay.name) + '</span>';
                    } else {
                        natPill.className = 'calendar-national-pill';
                        natPill.title = 'Hari Penting Nasional: ' + nationalDay.name;
                        natPill.innerHTML = '<span>' + escapeHtml(nationalDay.name) + '</span>';
                    }
                    dayElement.appendChild(natPill);
                }


                /* -------------------------------------------------
                   LIST AGENDA
                ------------------------------------------------- */

                const eventList =
                    document.createElement('div');

                eventList.className =
                    'event-list';


                const maxVisible = 1;
                const visibleEvents = dailyEvents.slice(0, maxVisible);
                const hiddenCount = dailyEvents.length - maxVisible;

                visibleEvents.forEach(
                    function(item) {

                        const eventElement =
                            document.createElement('div');

                        const isAdmin = item.creator_role === 'admin';
                        eventElement.className =
                            'calendar-event ' + (isAdmin ? 'event-admin' : 'event-guru');


                        /* -----------------------------
                           AKSESIBILITAS
                        ----------------------------- */

                        eventElement.setAttribute(
                            'role',
                            'button'
                        );

                        eventElement.setAttribute(
                            'tabindex',
                            '0'
                        );


                        /* -----------------------------
                           CLICK
                        ----------------------------- */

                        eventElement.addEventListener(
                            'click',
                            function() {

                                showEventDetail(item);

                            }
                        );


                        /* -----------------------------
                           KEYBOARD
                        ----------------------------- */

                        eventElement.addEventListener(
                            'keydown',
                            function(event) {

                                if (
                                    event.key === 'Enter' ||
                                    event.key === ' '
                                ) {

                                    event.preventDefault();

                                    showEventDetail(item);

                                }

                            }
                        );


                        /* -----------------------------
                           JUDUL
                        ----------------------------- */

                        const title =
                            document.createElement('div');

                        title.className =
                            'event-title';

                        title.textContent =
                            item.title || 'Agenda';


                        /* -----------------------------
                           WAKTU
                        ----------------------------- */

                        const time =
                            document.createElement('div');

                        time.className =
                            'event-time';

                        time.textContent =
                            (item.time || '-');


                        /* -----------------------------
                           RUANGAN
                        ----------------------------- */

                        const room =
                            document.createElement('div');

                        room.className =
                            'event-room';

                        room.textContent =
                            (item.room || 'Tanpa ruangan');


                        /* -----------------------------
                           MASUKKAN KE EVENT
                        ----------------------------- */

                        eventElement.appendChild(
                            title
                        );

                        eventElement.appendChild(
                            time
                        );

                        eventElement.appendChild(
                            room
                        );


                        eventList.appendChild(
                            eventElement
                        );

                    }
                );

                if (hiddenCount > 0) {
                    const moreBtn = document.createElement('button');
                    moreBtn.type = 'button';
                    moreBtn.className = 'calendar-more-btn';
                    moreBtn.textContent = `+${hiddenCount} lainnya`;
                    moreBtn.setAttribute('title', `Lihat semua ${dailyEvents.length} agenda pada tanggal ini`);
                    moreBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        openDayDetailModal(date, dailyEvents);
                    });
                    eventList.appendChild(moreBtn);
                }


                dayElement.appendChild(
                    eventList
                );


                calendar.appendChild(
                    dayElement
                );

            }


            /* -----------------------------------------------------
               KOTAK KOSONG SETELAH TANGGAL TERAKHIR (AKHIR BULAN)
            ----------------------------------------------------- */

            const totalRendered =
                startDay + lastDate;

            const remainingDays =
                (7 - (totalRendered % 7)) % 7;

            for (let i = 0; i < remainingDays; i++) {

                const emptyDay =
                    document.createElement('div');

                emptyDay.className =
                    'calendar-day empty';

                calendar.appendChild(
                    emptyDay
                );

            }


            /* =====================================================
               PESAN KOSONG
            ====================================================== */

            if (events.length === 0) {

                emptyTitle.textContent =
                    'Agenda tidak ditemukan';

                emptyText.textContent =
                    'Tidak ada agenda yang sesuai dengan pencarian.';

                emptyMessage.hidden = false;

            }

            else if (
                displayedEvents === 0
            ) {

                emptyTitle.textContent =
                    'Tidak ada agenda bulan ini';

                emptyText.textContent =
                    'Belum ada agenda yang disetujui pada bulan ini.';

                emptyMessage.hidden = false;

            }

            else {

                emptyMessage.hidden = true;

            }

        }


        /* =========================================================
           BULAN SEBELUMNYA
        ========================================================== */

        document
            .getElementById('previous')
            .addEventListener(
                'click',
                function() {

                    activeMonth.setMonth(
                        activeMonth.getMonth() - 1
                    );

                    renderCalendar();

                }
            );


        /* =========================================================
           BULAN BERIKUTNYA
        ========================================================== */

        document
            .getElementById('next')
            .addEventListener(
                'click',
                function() {

                    activeMonth.setMonth(
                        activeMonth.getMonth() + 1
                    );

                    renderCalendar();

                }
            );


        /* =========================================================
           HARI INI
        ========================================================== */

        const todayBtn = document.getElementById('today');
        if (todayBtn) todayBtn.addEventListener(
                'click',
                function() {

                    activeMonth =
                        new Date();

                    activeMonth.setDate(1);

                    renderCalendar();

                }
            );


        /* =========================================================
           RENDER PERTAMA
        ========================================================== */

        renderCalendar();

    </script>

</body>

</html>