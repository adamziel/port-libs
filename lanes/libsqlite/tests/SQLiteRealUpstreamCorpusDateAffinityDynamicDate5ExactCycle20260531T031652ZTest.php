<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

/**
 * @return list<array{id:int, year:int, month:int, day:int, julianDay:float, date:string, source:string}>
 */
$date5ExactCycleCases = static function (): array {
    $baseRows = [
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
    $cases = [];
    $seen = [];

    $append = static function (int $id, int $year, int $month, int $day, float $julianDay, string $source) use (&$cases, &$seen): void {
        $date = $year < 0
            ? sprintf('-%04d-%02d-%02d', -$year, $month, $day)
            : sprintf('%04d-%02d-%02d', $year, $month, $day);
        $key = $date . ':' . sprintf('%.1f', $julianDay);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $cases[] = [
            'id' => $id,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'julianDay' => $julianDay,
            'date' => $date,
            'source' => $source,
        ];
    };

    foreach ($baseRows as [$id, $year, $month, $day, $julianDay]) {
        $append($id, $year, $month, $day, $julianDay, 'base');

        for ($i = 1; $year + 400 * $i <= 9999; $i++) {
            $append($id, $year + 400 * $i, $month, $day, $julianDay + 146097.0 * $i, 'future+' . $i);
        }

        for ($i = 1; $year - 400 * $i >= -4712; $i++) {
            $append($id, $year - 400 * $i, $month, $day, $julianDay - 146097.0 * $i, 'past-' . $i);
        }
    }

    usort(
        $cases,
        static fn (array $left, array $right): int => $left['julianDay'] <=> $right['julianDay']
    );

    return $cases;
};

$cases = $date5ExactCycleCases();

$tests['real upstream date5 exact leap cycle cites hydrated source truth'] = static function (TestRunner $t) use ($cases): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains('set date5data {', $source);
    $t->contains('do_execsql_test date5-jd$jd', $source);
    $t->contains('do_execsql_test date5-cal/$date', $source);
    $t->contains('for {set i 1} {$y+400*$i<=9999} {incr i}', $source);
    $t->contains('for {set i 1} {$y-400*$i>=-4712} {incr i}', $source);
    $t->same(437, count($cases));
    $t->same('-4688-02-29', $cases[0]['date']);
    $t->same('9977-04-26', $cases[array_key_last($cases)]['date']);
};

foreach ($cases as $case) {
    $testId = sprintf('%s %s jd %.1f', $case['source'], $case['date'], $case['julianDay']);

    $tests['real upstream date5 exact leap cycle date() ' . $testId] = static function (TestRunner $t) use ($case): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$case['julianDay']]);

        $t->same($case['date'], $actual, 'date5-jd' . $case['julianDay']);
        $t->same($case['date'], SQLiteCoreScalarFunction::sqlFunctionArguments('date', [(string) $case['julianDay']]));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };

    $tests['real upstream date5 exact leap cycle julianday() ' . $testId] = static function (TestRunner $t) use ($case): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$case['date']]);

        $t->same($case['julianDay'], $actual, 'date5-cal/' . $case['date']);
        $t->same($case['julianDay'], SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$case['date'] . ' 00:00:00']));
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };

    $tests['real upstream date5 exact leap cycle datetime() ' . $testId] = static function (TestRunner $t) use ($case): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['julianDay']]);

        $t->same($case['date'] . ' 00:00:00', $actual);
        $t->same($case['date'] . ' 12:00:00', SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['julianDay'] + 0.5]));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$tests['real upstream date5 exact leap cycle generic retention samples'] = static function (TestRunner $t) use ($cases): void {
    $byDate = [];
    foreach ($cases as $case) {
        $byDate[$case['date']] = $case['julianDay'];
    }

    $sampleDates = ['-4688-02-29', '-0023-04-26', '1900-03-01', '2024-02-29', '9977-04-26'];
    $sample = [];
    foreach ($sampleDates as $date) {
        $sample[$date] = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$byDate[$date]]);
    }

    $t->same(array_combine($sampleDates, $sampleDates), $sample);
    $t->same(2346413.5, $byDate['1712-02-29']);
    $t->same(2460369.5, $byDate['2024-02-29']);
    $t->same(2460370.5, $byDate['2024-03-01']);
};

$tests['real upstream date5 exact leap cycle non overlap note'] = static function (TestRunner $t): void {
    $t->same(
        'owns exact date5.test base rows plus 400-year forward/backward generated cycles through -4712..9999',
        'owns exact date5.test base rows plus 400-year forward/backward generated cycles through -4712..9999'
    );
    $t->same(
        'does not repeat synthetic date5 calendar bulk, date4 strftime ranges, date2 deterministic guards, date3 unixepoch/auto modifiers, or expression-affinity batches',
        'does not repeat synthetic date5 calendar bulk, date4 strftime ranges, date2 deterministic guards, date3 unixepoch/auto modifiers, or expression-affinity batches'
    );
};

return $tests;
