<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

final class PdfDateTimeNormalizer
{
    public static function toUtc(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match("/^D:(\d{4})(\d{2})?(\d{2})?(\d{2})?(\d{2})?(\d{2})?(?:(Z)|([+\-])(\d{2})'?(?:(\d{2})'?)?)?$/", $value, $match) === 1) {
            return self::pdfDateToUtc($match);
        }

        if (preg_match('/(?:Z|[+\-]\d{2}:\d{2})$/', $value) !== 1) {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * @param array<int, string> $match
     */
    private static function pdfDateToUtc(array $match): ?string
    {
        if (($match[7] ?? '') === '' && ($match[8] ?? '') === '') {
            return null;
        }

        $year = (int) $match[1];
        $month = ($match[2] ?? '') === '' ? 1 : (int) $match[2];
        $day = ($match[3] ?? '') === '' ? 1 : (int) $match[3];
        $hour = ($match[4] ?? '') === '' ? 0 : (int) $match[4];
        $minute = ($match[5] ?? '') === '' ? 0 : (int) $match[5];
        $second = ($match[6] ?? '') === '' ? 0 : (int) $match[6];

        if (
            !checkdate($month, $day, $year)
            || $hour < 0 || $hour > 23
            || $minute < 0 || $minute > 59
            || $second < 0 || $second > 59
        ) {
            return null;
        }

        $date = new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second),
            new DateTimeZone('UTC')
        );

        $sign = $match[8] ?? '';
        if ($sign !== '') {
            $offsetHours = (int) ($match[9] ?? 0);
            $offsetMinutes = ($match[10] ?? '') === '' ? 0 : (int) $match[10];
            if ($offsetHours > 23 || $offsetMinutes > 59) {
                return null;
            }

            $offsetSeconds = ($offsetHours * 3600) + ($offsetMinutes * 60);
            $date = $date->modify(($sign === '+' ? '-' : '+') . $offsetSeconds . ' seconds');
        }

        return $date->format('Y-m-d\TH:i:s\Z');
    }
}
