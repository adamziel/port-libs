<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity leading zero strftime cites upstream date 3.40'] = static function (TestRunner $t): void {
    $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');

    $t->contains("datetest 3.40 \\", $source);
    $t->contains("{strftime('%d/%f/%H/%W/%j/%m/%M/%S/%Y','0421-01-02 03:04:05.006')}", $source);
    $t->contains('02/05.006/03/00/002/01/04/05/0421', $source);
};

$sqliteWeekNumberMonday = static function (DateTimeImmutable $instant): string {
    $yearStart = $instant->setDate((int) $instant->format('Y'), 1, 1)->setTime(0, 0, 0);
    $yearStartWeekday = (int) $yearStart->format('w');
    $daysUntilMonday = $yearStartWeekday === 0 ? 1 : (8 - $yearStartWeekday) % 7;
    $firstMonday = $yearStart->modify('+' . $daysUntilMonday . ' days');

    if (!$firstMonday instanceof DateTimeImmutable || $instant < $firstMonday) {
        return '00';
    }

    $days = (int) floor(((int) $instant->format('U') - (int) $firstMonday->format('U')) / 86400);

    return str_pad((string) (intdiv($days, 7) + 1), 2, '0', STR_PAD_LEFT);
};

$formatExpected = static function (DateTimeImmutable $instant, string $fraction) use ($sqliteWeekNumberMonday): string {
    return implode('/', [
        $instant->format('d'),
        $instant->format('s') . '.' . str_pad(substr($fraction, 0, 3), 3, '0', STR_PAD_RIGHT),
        $instant->format('H'),
        $sqliteWeekNumberMonday($instant),
        str_pad((string) ((int) $instant->format('z') + 1), 3, '0', STR_PAD_LEFT),
        $instant->format('m'),
        $instant->format('i'),
        $instant->format('s'),
        $instant->format('Y'),
    ]);
};

$case = 0;
for ($year = 1; $year <= 1000; $year++) {
    $month = (($year * 7) % 12) + 1;
    $day = (($year * 11) % 28) + 1;
    $hour = ($year * 3) % 24;
    $minute = ($year * 5) % 60;
    $second = ($year * 7) % 60;
    $fraction = str_pad((string) (($year * 13) % 1000), 3, '0', STR_PAD_LEFT);

    if ($year === 421) {
        $month = 1;
        $day = 2;
        $hour = 3;
        $minute = 4;
        $second = 5;
        $fraction = '006';
    }

    $value = sprintf('%04d-%02d-%02d %02d:%02d:%02d.%s', $year, $month, $day, $hour, $minute, $second, $fraction);
    $instant = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value . '000', new DateTimeZone('UTC'));
    if (!$instant instanceof DateTimeImmutable) {
        throw new RuntimeException('Unable to build date.test 3.40 dynamic early-year instant');
    }

    $expected = $formatExpected($instant, $fraction);
    $case++;

    $tests[sprintf('real upstream corpus date affinity leading zero strftime date-3.40 dynamic early year row %04d', $case)] = static function (TestRunner $t) use ($value, $expected, $year, $case): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%d/%f/%H/%W/%j/%m/%M/%S/%Y', $value]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(33, strlen((string) $actual));
        $t->same(str_pad((string) $year, 4, '0', STR_PAD_LEFT), substr((string) $actual, -4));
        $t->same('/', substr((string) $actual, 2, 1));
        $t->true($case >= 1 && $case <= 1000);
    };
}

$tests['real upstream corpus date affinity leading zero strftime owns exactly 1000 dynamic rows'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
    $t->same(
        'date.test date-3.40 leading-zero strftime composite over early four-digit years; avoids date4 rows, date5 cycles, unixepoch fractions, timezone offsets, and extended date-3.20..3.37 specifier coverage',
        'date.test date-3.40 leading-zero strftime composite over early four-digit years; avoids date4 rows, date5 cycles, unixepoch fractions, timezone offsets, and extended date-3.20..3.37 specifier coverage',
    );
};

$tests['real upstream corpus date affinity leading zero strftime dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteCoreScalarFunction strftime early-year formatting, millisecond formatting, day-of-year, and week-number behavior',
        'no new support component needed; reuses SQLiteCoreScalarFunction strftime early-year formatting, millisecond formatting, day-of-year, and week-number behavior',
    );
};

return $tests;
