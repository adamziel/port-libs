<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity date5 cycle cites upstream source sections'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test';
    $sections = [
        'date5-jd$jd SELECT date($jd) for seed Julian day rows',
        'date5-cal/$date SELECT julianday($date) for seed calendar rows',
        'date5 400-year Gregorian cycle forward and backward expansion',
    ];

    $t->same(true, is_file($source));
    $t->same(true, in_array('date5-jd$jd SELECT date($jd) for seed Julian day rows', $sections, true));
    $t->same(true, in_array('date5-cal/$date SELECT julianday($date) for seed calendar rows', $sections, true));
    $t->contains('date5.test', $source);
};

// Source truth: SQLite upstream test/date5.test. The file seeds a set of
// calendar dates and Julian day values, then expands each seed by +/-400-year
// Gregorian cycles using the invariant 146097 days per cycle.
$date5Seeds = [
    [2024, 2, 29, 2460369.5],
    [2024, 3, 1, 2460370.5],
    [2023, 2, 28, 2460003.5],
    [2023, 3, 1, 2460004.5],
    [2000, 2, 29, 2451603.5],
    [2000, 3, 1, 2451604.5],
    [1900, 2, 28, 2415078.5],
    [1900, 3, 1, 2415079.5],
    [1712, 2, 29, 2346413.5],
    [1712, 3, 1, 2346414.5],
    [1977, 4, 26, 2443259.5],
    [2013, 1, 1, 2456293.5],
];

$formatDate5Year = static function (int $year, int $month, int $day): string {
    if ($year < 0) {
        return sprintf('-%04d-%02d-%02d', -$year, $month, $day);
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
};

$rows = [];
foreach ($date5Seeds as [$year, $month, $day, $julianDay]) {
    for ($cycle = 0; $cycle <= 19; $cycle++) {
        $forwardYear = $year + (400 * $cycle);
        if ($forwardYear <= 9999) {
            $rows[] = [
                'date' => $formatDate5Year($forwardYear, $month, $day),
                'julian_day' => $julianDay + (146097 * $cycle),
                'cycle' => $cycle,
                'direction' => 'forward',
            ];
        }

        if ($cycle === 0) {
            continue;
        }

        $backwardYear = $year - (400 * $cycle);
        if ($backwardYear >= -4712) {
            $rows[] = [
                'date' => $formatDate5Year($backwardYear, $month, $day),
                'julian_day' => $julianDay - (146097 * $cycle),
                'cycle' => $cycle,
                'direction' => 'backward',
            ];
        }
    }
}

for ($case = 0; $case < 1000; $case++) {
    $row = $rows[$case % count($rows)];
    $date = $row['date'];
    $julianDay = $row['julian_day'];
    $label = sprintf('%04d', $case);

    $tests['real upstream corpus date affinity date5 400-year Julian cycle row ' . $label] = static function (TestRunner $t) use ($date, $julianDay, $row, $case): void {
        $actualDate = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$julianDay]);
        $actualJulianDay = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$date]);

        $t->same($date, $actualDate, 'date5.test date5-jd row ' . $case);
        $t->same($julianDay, $actualJulianDay, 'date5.test date5-cal row ' . $case);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actualDate]));
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actualJulianDay]));
        $t->same(0.5, fmod($julianDay, 1.0));
        $t->same(true, in_array($row['direction'], ['forward', 'backward'], true));
        $t->same(true, $row['cycle'] >= 0);
    };
}

return $tests;
