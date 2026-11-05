<?php

namespace App\Support;

/** Small dependency-free PDF writer for downloadable tabular reports. */
class TablePdf
{
    public static function make(string $title, string $period, array $summary, array $headers, array $rows, array $widths): string
    {
        $pageWidth = 842;
        $pageHeight = 595;
        $margin = 30;
        $usableWidth = $pageWidth - ($margin * 2);
        $scale = $usableWidth / array_sum($widths);
        $widths = array_map(fn ($width) => $width * $scale, $widths);
        $pages = [];
        $commands = [];
        $y = 0;

        $startPage = function () use (&$commands, &$y, $title, $period, $summary, $headers, $widths, $pageHeight, $margin): void {
            $commands = [];
            self::text($commands, $margin, $pageHeight - 38, 16, 'RAPI SEKOLAH - ' . $title, true);
            self::text($commands, $margin, $pageHeight - 56, 9, $period);
            self::text($commands, $margin, $pageHeight - 76, 9, implode('   |   ', $summary));
            $y = $pageHeight - 105;
            self::tableHeader($commands, $headers, $widths, $margin, $y);
            $y -= 22;
        };

        $startPage();

        foreach ($rows as $row) {
            $wrapped = [];
            $maxLines = 1;
            foreach (array_values($row) as $index => $value) {
                $maxChars = max(5, (int) floor(($widths[$index] - 10) / 4.5));
                $wrapped[$index] = self::wrap((string) $value, $maxChars);
                $maxLines = max($maxLines, count($wrapped[$index]));
            }
            $rowHeight = max(22, ($maxLines * 11) + 8);

            if ($y - $rowHeight < 35) {
                $pages[] = $commands;
                $startPage();
            }

            $commands[] = sprintf('0.94 0.96 0.98 rg %d %d %.2f %.2f re f', $margin, (int) ($y - $rowHeight), $usableWidth, $rowHeight);
            $x = $margin;
            foreach ($widths as $width) {
                $commands[] = sprintf('0.75 0.78 0.82 RG 0.5 w %.2f %.2f m %.2f %.2f l S', $x, $y, $x, $y - $rowHeight);
                $x += $width;
            }
            $commands[] = sprintf('0.75 0.78 0.82 RG 0.5 w %d %.2f m %.2f %.2f l S', $margin, $y - $rowHeight, $margin + $usableWidth, $y - $rowHeight);

            $x = $margin;
            foreach ($widths as $index => $width) {
                foreach ($wrapped[$index] as $lineIndex => $line) {
                    self::text($commands, $x + 5, $y - 13 - ($lineIndex * 11), 8, $line);
                }
                $x += $width;
            }
            $y -= $rowHeight;
        }
        $pages[] = $commands;

        return self::document($pages, $pageWidth, $pageHeight);
    }

    private static function tableHeader(array &$commands, array $headers, array $widths, int $left, float $top): void
    {
        $totalWidth = array_sum($widths);
        $commands[] = sprintf('0.12 0.22 0.36 rg %d %d %.2f 22 re f', $left, (int) ($top - 22), $totalWidth);
        $x = $left;
        foreach ($widths as $index => $width) {
            self::text($commands, $x + 5, $top - 14, 8, (string) $headers[$index], true, true);
            $x += $width;
        }
    }

    private static function text(array &$commands, float $x, float $y, int $size, string $value, bool $bold = false, bool $white = false): void
    {
        $value = self::ascii($value);
        $font = $bold ? 'F2' : 'F1';
        $color = $white ? '1 1 1 rg' : '0.12 0.16 0.20 rg';
        $commands[] = sprintf("%s BT /%s %d Tf %.2f %.2f Td (%s) Tj ET", $color, $font, $size, $x, $y, self::escape($value));
    }

    private static function wrap(string $value, int $maxChars): array
    {
        $value = trim(preg_replace('/\s+/', ' ', self::ascii($value)) ?? '');
        if ($value === '') {
            return ['-'];
        }

        return explode("\n", wordwrap($value, $maxChars, "\n", true));
    }

    private static function ascii(string $value): string
    {
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private static function document(array $pages, int $width, int $height): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
        ];
        $pageIds = [];
        $nextId = 5;

        foreach ($pages as $page) {
            $pageId = $nextId++;
            $streamId = $nextId++;
            $pageIds[] = $pageId . ' 0 R';
            $stream = implode("\n", $page);
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', $width, $height, $streamId);
            $objects[$streamId] = "<< /Length " . strlen($stream) . ">>\nstream\n" . $stream . "\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . count($pageIds) . ' >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
