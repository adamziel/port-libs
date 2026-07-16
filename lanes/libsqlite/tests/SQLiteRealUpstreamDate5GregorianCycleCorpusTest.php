<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$date5Seeds = [
    [1, 2024, 2, 29, 2460369.5],
    [2, 2024, 3, 1, 2460370.5],
    [3, 2023, 2, 28, 2460003.5],
    [4, 2023, 3, 1, 2460004.5],
    [5, 2000, 2, 29, 2451603.5],
    [6, 2000, 3, 1, 2451604.5],
    [7, 1900, 2, 28, 2415078.5],
    [8, 1900, 3, 1, 2415079.5],
    [9, 1712, 2, 29, 2346413.5],
    [10, 1712, 3, 1, 2346414.5],
    [11, 1977, 4, 26, 2443259.5],
    [12, 2013, 1, 1, 2456293.5],
];

$formatDate5Year = static function (int $year, int $month, int $day): string {
    if ($year < 0) {
        return sprintf('-%04d-%02d-%02d', -$year, $month, $day);
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
};

$date5Rows = [];
foreach ($date5Seeds as [$id, $year, $month, $day, $julianDay]) {
    $date5Rows[] = [$id, 0, $year, $month, $day, $julianDay, $formatDate5Year($year, $month, $day)];

    for ($cycle = 1; $year + 400 * $cycle <= 9999; $cycle++) {
        $cycleYear = $year + 400 * $cycle;
        $cycleJulianDay = $julianDay + 146097 * $cycle;
        $date5Rows[] = [$id, $cycle, $cycleYear, $month, $day, $cycleJulianDay, $formatDate5Year($cycleYear, $month, $day)];
    }

    for ($cycle = -1; $year + 400 * $cycle >= -4712; $cycle--) {
        $cycleYear = $year + 400 * $cycle;
        $cycleJulianDay = $julianDay + 146097 * $cycle;
        $date5Rows[] = [$id, $cycle, $cycleYear, $month, $day, $cycleJulianDay, $formatDate5Year($cycleYear, $month, $day)];
    }
}

foreach ($date5Rows as [$seedId, $cycle, $year, $month, $day, $julianDay, $date]) {
    $tests[sprintf(
        'real upstream date5 gregorian 400-year cycle seed %02d cycle %+03d jd %.1f',
        $seedId,
        $cycle,
        $julianDay
    )] = static function (TestRunner $t) use ($year, $month, $day, $julianDay, $date): void {
        $dateFromFloat = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julianDay]);
        $dateFromString = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [(string) $julianDay]);
        $datetimeFromFloat = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$julianDay]);
        $strftimeDate = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%F', $julianDay]);
        $strftimeJulian = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%J', $date]);
        $julianFromDate = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$date]);
        $julianFromDatetime = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$date . ' 00:00:00']);
        $yearFromDate = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y', $date]);
        $monthFromDate = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%m', $date]);
        $dayFromDate = SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%d', $date]);
        $dateViaStartOfDay = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$date . ' 12:34:56', 'start of day']);
        $julianViaExplicitModifier = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [(string) $julianDay, 'julianday']);

        $t->same($date, $dateFromFloat);
        $t->same($date, $dateFromString);
        $t->same($date . ' 00:00:00', $datetimeFromFloat);
        $t->same($date, $strftimeDate);
        $t->same($julianDay, (float) $strftimeJulian);
        $t->same($julianDay, $julianFromDate);
        $t->same($julianDay, $julianFromDatetime);
        $t->same(sprintf($year < 0 ? '-%04d' : '%04d', abs($year)), $yearFromDate);
        $t->same(sprintf('%02d', $month), $monthFromDate);
        $t->same(sprintf('%02d', $day), $dayFromDate);
        $t->same($date, $dateViaStartOfDay);
        $t->same($julianDay, $julianViaExplicitModifier);
    };
}

$tests['real upstream date5 gregorian cycle generated row count'] = static function (TestRunner $t) use ($date5Rows): void {
    $t->same(437, count($date5Rows));
};

return $tests;
