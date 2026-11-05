<?php

namespace App\Services;

class NationalCalendarService
{
    /**
     * Daftar hari penting & libur tetap setiap tahun (format: MM-DD)
     */
    public static function getFixedDays(): array
    {
        return [
            // Januari
            '01-01' => ['name' => 'Tahun Baru Masehi', 'is_holiday' => true],
            '01-10' => ['name' => 'Hari Gerakan Satu Juta Pohon', 'is_holiday' => false],
            '01-25' => ['name' => 'Hari Gizi Nasional', 'is_holiday' => false],

            // Februari
            '02-09' => ['name' => 'Hari Pers Nasional', 'is_holiday' => false],
            '02-21' => ['name' => 'Hari Peduli Sampah Nasional', 'is_holiday' => false],

            // Maret
            '03-09' => ['name' => 'Hari Musik Nasional', 'is_holiday' => false],
            '03-30' => ['name' => 'Hari Film Nasional', 'is_holiday' => false],

            // April
            '04-21' => ['name' => 'Hari Kartini', 'is_holiday' => false],
            '04-22' => ['name' => 'Hari Bumi', 'is_holiday' => false],

            // Mei
            '05-01' => ['name' => 'Hari Buruh Internasional', 'is_holiday' => true],
            '05-02' => ['name' => 'Hari Pendidikan Nasional', 'is_holiday' => false],
            '05-20' => ['name' => 'Hari Kebangkitan Nasional', 'is_holiday' => false],

            // Juni
            '06-01' => ['name' => 'Hari Lahir Pancasila', 'is_holiday' => true],

            // Juli
            '07-23' => ['name' => 'Hari Anak Nasional', 'is_holiday' => false],

            // Agustus
            '08-10' => ['name' => 'Hari Veteran Nasional', 'is_holiday' => false],
            '08-17' => ['name' => 'Hari Kemerdekaan RI (HUT RI)', 'is_holiday' => true],

            // September
            '09-09' => ['name' => 'Hari Olahraga Nasional', 'is_holiday' => false],
            '09-24' => ['name' => 'Hari Tani Nasional', 'is_holiday' => false],

            // Oktober
            '10-01' => ['name' => 'Hari Kesaktian Pancasila', 'is_holiday' => false],
            '10-02' => ['name' => 'Hari Batik Nasional', 'is_holiday' => false],
            '10-05' => ['name' => 'Hari Tentara Nasional Indonesia (TNI)', 'is_holiday' => false],
            '10-22' => ['name' => 'Hari Santri Nasional', 'is_holiday' => false],
            '10-28' => ['name' => 'Hari Sumpah Pemuda', 'is_holiday' => false],

            // November
            '11-10' => ['name' => 'Hari Pahlawan', 'is_holiday' => false],
            '11-12' => ['name' => 'Hari Ayah & Kesehatan Nasional', 'is_holiday' => false],
            '11-25' => ['name' => 'Hari Guru Nasional (PGRI)', 'is_holiday' => false],

            // Desember
            '12-09' => ['name' => 'Hari Anti Korupsi Sedunia', 'is_holiday' => false],
            '12-13' => ['name' => 'Hari Nusantara', 'is_holiday' => false],
            '12-22' => ['name' => 'Hari Ibu', 'is_holiday' => false],
            '12-25' => ['name' => 'Hari Raya Natal', 'is_holiday' => true],
        ];
    }

    /**
     * Daftar hari libur keagamaan & pergeseran kalender per tahun (format: YYYY-MM-DD)
     */
    public static function getVariableHolidays(): array
    {
        return [
            // 2024
            '2024-02-08' => ['name' => "Isra Mi'raj Nabi Muhammad SAW", 'is_holiday' => true],
            '2024-02-10' => ['name' => 'Tahun Baru Imlek 2575 Kongzili', 'is_holiday' => true],
            '2024-03-11' => ['name' => 'Hari Suci Nyepi Saka 1946', 'is_holiday' => true],
            '2024-03-29' => ['name' => 'Wafat Yesus Kristus', 'is_holiday' => true],
            '2024-03-31' => ['name' => 'Hari Paskah', 'is_holiday' => true],
            '2024-04-10' => ['name' => 'Hari Raya Idul Fitri 1445 H', 'is_holiday' => true],
            '2024-04-11' => ['name' => 'Hari Raya Idul Fitri 1445 H', 'is_holiday' => true],
            '2024-05-09' => ['name' => 'Kenaikan Yesus Kristus', 'is_holiday' => true],
            '2024-05-23' => ['name' => 'Hari Raya Waisak 2568 BE', 'is_holiday' => true],
            '2024-06-17' => ['name' => 'Hari Raya Idul Adha 1445 H', 'is_holiday' => true],
            '2024-07-07' => ['name' => 'Tahun Baru Islam 1446 H', 'is_holiday' => true],
            '2024-09-16' => ['name' => 'Maulid Nabi Muhammad SAW', 'is_holiday' => true],

            // 2025
            '2025-01-27' => ['name' => "Isra Mi'raj Nabi Muhammad SAW", 'is_holiday' => true],
            '2025-01-29' => ['name' => 'Tahun Baru Imlek 2576 Kongzili', 'is_holiday' => true],
            '2025-03-29' => ['name' => 'Hari Suci Nyepi Saka 1947', 'is_holiday' => true],
            '2025-03-31' => ['name' => 'Hari Raya Idul Fitri 1446 H', 'is_holiday' => true],
            '2025-04-01' => ['name' => 'Hari Raya Idul Fitri 1446 H', 'is_holiday' => true],
            '2025-04-18' => ['name' => 'Wafat Yesus Kristus', 'is_holiday' => true],
            '2025-04-20' => ['name' => 'Hari Paskah', 'is_holiday' => true],
            '2025-05-12' => ['name' => 'Hari Raya Waisak 2569 BE', 'is_holiday' => true],
            '2025-05-29' => ['name' => 'Kenaikan Yesus Kristus', 'is_holiday' => true],
            '2025-06-06' => ['name' => 'Hari Raya Idul Adha 1446 H', 'is_holiday' => true],
            '2025-06-27' => ['name' => 'Tahun Baru Islam 1447 H', 'is_holiday' => true],
            '2025-09-05' => ['name' => 'Maulid Nabi Muhammad SAW', 'is_holiday' => true],

            // 2026
            '2026-01-16' => ['name' => "Isra Mi'raj Nabi Muhammad SAW", 'is_holiday' => true],
            '2026-02-17' => ['name' => 'Tahun Baru Imlek 2577 Kongzili', 'is_holiday' => true],
            '2026-03-19' => ['name' => 'Hari Suci Nyepi Saka 1948', 'is_holiday' => true],
            '2026-03-20' => ['name' => 'Hari Raya Idul Fitri 1447 H', 'is_holiday' => true],
            '2026-03-21' => ['name' => 'Hari Raya Idul Fitri 1447 H', 'is_holiday' => true],
            '2026-04-03' => ['name' => 'Wafat Yesus Kristus', 'is_holiday' => true],
            '2026-04-05' => ['name' => 'Hari Paskah', 'is_holiday' => true],
            '2026-05-14' => ['name' => 'Kenaikan Yesus Kristus', 'is_holiday' => true],
            '2026-05-26' => ['name' => 'Hari Raya Idul Adha 1447 H', 'is_holiday' => true],
            '2026-05-31' => ['name' => 'Hari Raya Waisak 2570 BE', 'is_holiday' => true],
            '2026-06-16' => ['name' => 'Tahun Baru Islam 1448 H', 'is_holiday' => true],
            '2026-08-25' => ['name' => 'Maulid Nabi Muhammad SAW', 'is_holiday' => true],

            // 2027
            '2027-01-06' => ['name' => "Isra Mi'raj Nabi Muhammad SAW", 'is_holiday' => true],
            '2027-02-06' => ['name' => 'Tahun Baru Imlek 2578 Kongzili', 'is_holiday' => true],
            '2027-03-09' => ['name' => 'Hari Suci Nyepi Saka 1949', 'is_holiday' => true],
            '2027-03-10' => ['name' => 'Hari Raya Idul Fitri 1448 H', 'is_holiday' => true],
            '2027-03-11' => ['name' => 'Hari Raya Idul Fitri 1448 H', 'is_holiday' => true],
            '2027-03-26' => ['name' => 'Wafat Yesus Kristus', 'is_holiday' => true],
            '2027-05-06' => ['name' => 'Kenaikan Yesus Kristus', 'is_holiday' => true],
            '2027-05-16' => ['name' => 'Hari Raya Idul Adha 1448 H', 'is_holiday' => true],
            '2027-05-20' => ['name' => 'Hari Raya Waisak 2571 BE', 'is_holiday' => true],
            '2027-06-06' => ['name' => 'Tahun Baru Islam 1449 H', 'is_holiday' => true],
            '2027-08-15' => ['name' => 'Maulid Nabi Muhammad SAW', 'is_holiday' => true],
        ];
    }

    /**
     * Dapatkan data hari libur/hari penting untuk tanggal YYYY-MM-DD
     */
    public static function getForDate(string $dateString): ?array
    {
        $variable = self::getVariableHolidays();
        if (isset($variable[$dateString])) {
            return $variable[$dateString];
        }

        $monthDay = substr($dateString, 5, 5);
        $fixed = self::getFixedDays();
        if (isset($fixed[$monthDay])) {
            return $fixed[$monthDay];
        }

        return null;
    }

    /**
     * Mengembalikan data lengkap untuk inject ke JavaScript
     */
    public static function getCalendarData(): array
    {
        return [
            'fixed' => self::getFixedDays(),
            'variable' => self::getVariableHolidays(),
        ];
    }
}

