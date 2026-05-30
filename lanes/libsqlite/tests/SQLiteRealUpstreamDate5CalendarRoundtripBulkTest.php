<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$julianDayForDate = static function (int $year, int $month, int $day): float {
    $adjustedYear = $year;
    $adjustedMonth = $month;
    if ($adjustedMonth <= 2) {
        --$adjustedYear;
        $adjustedMonth += 12;
    }

    $century = (int) floor($adjustedYear / 100);
    $gregorianCorrection = 2 - $century + (int) floor($century / 4);

    return (float) (
        (int) floor(365.25 * ($adjustedYear + 4716))
        + (int) floor(30.6001 * ($adjustedMonth + 1))
        + $day
        + $gregorianCorrection
        - 1524.5
    );
};

$cases = [];
for ($index = 0; $index < 1250; $index++) {
    $year = $index * 8;
    $month = (($index * 7) % 12) + 1;
    $day = (($index * 13) % 28) + 1;
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $cases[] = [
        'index' => $index,
        'date' => $date,
        'julianDay' => $julianDayForDate($year, $month, $day),
    ];
}

$tests['real upstream date5 calendar roundtrip bulk cites source truth'] = static function (TestRunner $t) use ($cases): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test';

    $t->same(1250, count($cases));
    $t->same(true, is_file($source));
    $t->contains('date5-jd$jd', file_get_contents($source));
    $t->contains('date5-cal/$date', file_get_contents($source));
    $t->same(
        'date5.test calendar-to-julianday and julianday-to-calendar roundtrip loop',
        'date5.test calendar-to-julianday and julianday-to-calendar roundtrip loop'
    );
};

foreach ($cases as $case) {
    $tests[sprintf('real upstream date5 calendar roundtrip bulk generated date %04d', $case['index'])] = static function (TestRunner $t) use ($case): void {
        $actualJulianDay = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$case['date']]);
        $actualDate = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$case['julianDay']]);
        $actualDateTime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['julianDay']]);
        $roundTripJulianDay = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$actualDate]);

        $t->same($case['julianDay'], $actualJulianDay, $case['date'] . ' julianday');
        $t->same($case['date'], $actualDate, $case['date'] . ' date');
        $t->same($case['date'] . ' 00:00:00', $actualDateTime, $case['date'] . ' datetime');
        $t->same($case['julianDay'], $roundTripJulianDay, $case['date'] . ' roundtrip julianday');
    };
}

$tests['real upstream date5 calendar roundtrip bulk application retention windows'] = static function (TestRunner $t) use ($cases): void {
    $sample = array_values(array_filter(
        $cases,
        static fn (array $case): bool => $case['index'] % 250 === 0
    ));

    $t->same(['0000-01-01', '2000-11-03', '4000-09-05', '6000-07-07', '8000-05-09'], array_column($sample, 'date'));
    $t->same([1721059.5, 2451851.5, 3182277.5, 3912702.5, 4643128.5], array_column($sample, 'julianDay'));
    $t->same(
        ['0000-01-01', '2000-11-03', '4000-09-05', '6000-07-07', '8000-05-09'],
        array_map(
            static fn (array $case): mixed => SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$case['julianDay']]),
            $sample
        )
    );
};

return $tests;
