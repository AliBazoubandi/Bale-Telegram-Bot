<?php

/**
 * Jalali (Shamsi) date formatting for the admin panel.
 *
 * Pure PHP — no Composer, no intl extension. Uses the classic
 * Gregorian→Jalali conversion algorithm (same math as the widely used
 * jdf library).
 *
 * Usage:
 *   Jalali::format('2026-08-26 14:23:00');  // '1405/06/04 14:23'
 *   Jalali::formatDate('2026-08-26 ...');   // '1405/06/04'
 */
class Jalali
{
    /** Persian month names, index 0 = فروردین. */
    private const MONTHS = [
        'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
        'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
    ];

    /**
     * Converts a Gregorian date to Jalali.
     *
     * @return array{0: int, 1: int, 2: int} [year, month, day]
     */
    public static function convert(int $gy, int $gm, int $gd): array
    {
        $gDaysInMonth = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $jy = ($gy <= 1600) ? 0 : 979;
        $gy -= ($gy <= 1600) ? 621 : 1600;

        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = (365 * $gy)
              + intdiv($gy2 + 3, 4)
              - intdiv($gy2 + 99, 100)
              + intdiv($gy2 + 399, 400)
              - 80
              + $gd
              + $gDaysInMonth[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;

        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        // The year rollover MUST adjust jy and reset days together,
        // otherwise valid dates render as an impossible month 13.
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $jm = ($days < 186) ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));

        return [$jy, $jm, $jd];
    }

    /**
     * Parses a MySQL datetime string into [Y, m, d, H, i] parts, or null.
     */
    private static function parse(?string $datetime): ?array
    {
        $dt = trim((string) $datetime);

        if ($dt === '' || str_starts_with($dt, '0000-00-00')) {
            return null;
        }

        $ts = strtotime($dt);

        if ($ts === false) {
            return null;
        }

        return [
            (int) date('Y', $ts),
            (int) date('n', $ts),
            (int) date('j', $ts),
            date('H', $ts),
            date('i', $ts),
        ];
    }

    /**
     * '1405/06/04 14:23' — falls back to '—' for empty/unparseable values.
     */
    public static function format(?string $datetime): string
    {
        $parts = self::parse($datetime);

        if ($parts === null) {
            return '—';
        }

        [$gy, $gm, $gd, $H, $i] = $parts;
        [$jy, $jm, $jd] = self::convert($gy, $gm, $gd);

        return sprintf('%04d/%02d/%02d %s:%s', $jy, $jm, $jd, $H, $i);
    }

    /**
     * '1405/06/04' — date only.
     */
    public static function formatDate(?string $datetime): string
    {
        $formatted = self::format($datetime);

        return ($formatted === '—') ? $formatted : mb_substr($formatted, 0, 10);
    }
}
