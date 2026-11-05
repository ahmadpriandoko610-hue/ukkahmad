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

                <div class="calendar-logo">
                    📅
                </div>

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
             SEARCH
        ========================================================== --}}
        <section class="calendar-search">

            <div class="search-box">

                <span class="search-icon">
                    🔍
                </span>

                <input
                    type="text"
                    id="searchAgenda"
                    placeholder="Cari agenda, ruangan, waktu, atau tanggal..."
                    autocomplete="off"
                >

                <button
                    type="button"
                    id="clearSearch"
                    class="clear-search"
                    hidden
                >
                    ✕
                </button>

            </div>

            <div
                id="searchInfo"
                class="search-info"
                hidden
            ></div>

        </section>


        {{-- =========================================================
             CALENDAR CARD
        ========================================================== --}}
        <div class="calendar-legend-box" style="margin: 0 0 16px 0;">
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

            {{-- PESAN KOSONG --}}
            <div
                id="emptyMessage"
                class="empty-message"
                hidden
            >
                <div class="empty-icon">📅</div>
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

                <div class="event-modal-icon">
                    📅
                </div>

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

                    <span class="event-detail-icon">
                        📅
                    </span>

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

                    <span class="event-detail-icon">
                        🕐
                    </span>

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

                    <span class="event-detail-icon">
                        👤
                    </span>

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

                    <span class="event-detail-icon">
                        📍
                    </span>

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

        const searchInput =
            document.getElementById('searchAgenda');

        const clearSearch =
            document.getElementById('clearSearch');

        const searchInfo =
            document.getElementById('searchInfo');

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
                    ? '<span class="event-role-tag admin">⭐ Agenda Admin / Sekolah</span>'
                    : '<span class="event-role-tag guru">👤 ' + escapeHtml(item.requester_name || 'Guru / Organisasi') + '</span>';
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
                        natPill.innerHTML = '<span class="holiday-icon">🔴</span><span>' + escapeHtml(nationalDay.name) + '</span>';
                    } else {
                        natPill.className = 'calendar-national-pill';
                        natPill.title = 'Hari Penting Nasional: ' + nationalDay.name;
                        natPill.innerHTML = '<span class="national-icon">🇮🇩</span><span>' + escapeHtml(nationalDay.name) + '</span>';
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


                dailyEvents.forEach(
                    function(item) {

                        const eventElement =
                            document.createElement('div');

                        const isAdmin = item.creator_role === 'admin';
                        eventElement.className =
                            'calendar-event ' + (isAdmin ? 'event-admin' : 'event-guru');

                        const roleTag = document.createElement('span');
                        roleTag.className = 'event-role-tag ' + (isAdmin ? 'admin' : 'guru');
                        roleTag.textContent = isAdmin ? '⭐ Admin / Sekolah' : '👤 Guru / Organisasi';
                        eventElement.appendChild(roleTag);


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
                            '🕐 ' +
                            (item.time || '-');


                        /* -----------------------------
                           RUANGAN
                        ----------------------------- */

                        const room =
                            document.createElement('div');

                        room.className =
                            'event-room';

                        room.textContent =
                            '📍 ' +
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
           SEARCH AGENDA
        ========================================================== */

        function searchAgenda() {

            const keyword =
                searchInput.value
                    .trim()
                    .toLowerCase();


            /* -----------------------------------------------------
               SEARCH KOSONG
            ----------------------------------------------------- */

            if (!keyword) {

                events = [
                    ...allEvents
                ];

                clearSearch.hidden =
                    true;

                searchInfo.hidden =
                    true;

                activeMonth =
                    new Date();

                activeMonth.setDate(1);

                renderCalendar();

                return;
            }


            /* -----------------------------------------------------
               FILTER AGENDA
            ----------------------------------------------------- */

            events =
                allEvents.filter(
                    function(item) {

                        const title =
                            String(
                                item.title ?? ''
                            ).toLowerCase();

                        const room =
                            String(
                                item.room ?? ''
                            ).toLowerCase();

                        const time =
                            String(
                                item.time ?? ''
                            ).toLowerCase();

                        const date =
                            String(
                                item.date ?? ''
                            ).toLowerCase();

                        const formattedDate =
                            formatDate(
                                item.date
                            ).toLowerCase();


                        return (
                            title.includes(keyword) ||
                            room.includes(keyword) ||
                            time.includes(keyword) ||
                            date.includes(keyword) ||
                            formattedDate.includes(keyword)
                        );

                    }
                );


            /* -----------------------------------------------------
               TOMBOL CLEAR
            ----------------------------------------------------- */

            clearSearch.hidden =
                false;


            /* -----------------------------------------------------
               INFORMASI HASIL
            ----------------------------------------------------- */

            searchInfo.hidden =
                false;


            if (events.length > 0) {

                searchInfo.textContent =
                    `Ditemukan ${events.length} agenda.`;

            }

            else {

                searchInfo.textContent =
                    'Agenda tidak ditemukan.';

            }


            /* -----------------------------------------------------
               PINDAH KE BULAN AGENDA PERTAMA
            ----------------------------------------------------- */

            if (
                events.length > 0 &&
                events[0].date
            ) {

                const firstDate =
                    new Date(
                        events[0].date +
                        'T00:00:00'
                    );


                if (
                    !Number.isNaN(
                        firstDate.getTime()
                    )
                ) {

                    activeMonth =
                        new Date(
                            firstDate.getFullYear(),
                            firstDate.getMonth(),
                            1
                        );

                }

            }


            /* -----------------------------------------------------
               RENDER ULANG
            ----------------------------------------------------- */

            renderCalendar();

        }


        /* =========================================================
           SEARCH EVENT
        ========================================================== */

        searchInput.addEventListener(
            'input',
            searchAgenda
        );


        /* =========================================================
           CLEAR SEARCH
        ========================================================== */

        clearSearch.addEventListener(
            'click',
            function() {

                searchInput.value =
                    '';

                events =
                    [...allEvents];

                clearSearch.hidden =
                    true;

                searchInfo.hidden =
                    true;

                activeMonth =
                    new Date();

                activeMonth.setDate(1);

                renderCalendar();

                searchInput.focus();

            }
        );


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