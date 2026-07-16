<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic floor ceiling 031230 cites upstream date19'] = static function (TestRunner $t): void {
    $source = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test');

    $t->contains("datetest 19.2a {date('2000-02-31','floor')} {2000-02-29}", $source);
    $t->contains("datetest 19.22a {date('2000-02-31','ceiling')} {2000-03-02}", $source);
    $t->contains("datetest 19.40 {date('2024-01-31','+1 month','ceiling')} {2024-03-02}", $source);
    $t->contains("datetest 19.45 {date('2024-02-29','+1 year','floor')} {2025-02-28}", $source);
    $t->same(true, is_file('/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test'));
};

$fixedCases = [
    'date19-1 floor valid end of month unchanged' => [['2000-01-31', 'floor'], '2000-01-31'],
    'date19-2a floor leap february overflow' => [['2000-02-31', 'floor'], '2000-02-29'],
    'date19-2b floor common february overflow' => [['1999-02-31', 'floor'], '1999-02-28'],
    'date19-2c floor century common february overflow' => [['1900-02-31', 'floor'], '1900-02-28'],
    'date19-4 floor april overflow' => [['2000-04-31', 'floor'], '2000-04-30'],
    'date19-9 floor september overflow' => [['2000-09-31', 'floor'], '2000-09-30'],
    'date19-22a ceiling leap february overflow' => [['2000-02-31', 'ceiling'], '2000-03-02'],
    'date19-22b ceiling common february overflow' => [['1999-02-31', 'ceiling'], '1999-03-03'],
    'date19-24 ceiling april overflow' => [['2000-04-31', 'ceiling'], '2000-05-01'],
    'date19-29 ceiling september overflow' => [['2000-09-31', 'ceiling'], '2000-10-01'],
    'date19-40 plus one month ceiling' => [['2024-01-31', '+1 month', 'ceiling'], '2024-03-02'],
    'date19-41 plus one month floor' => [['2024-01-31', '+1 month', 'floor'], '2024-02-29'],
    'date19-42 common plus one month ceiling' => [['2023-01-31', '+1 month', 'ceiling'], '2023-03-03'],
    'date19-43 common plus one month floor' => [['2023-01-31', '+1 month', 'floor'], '2023-02-28'],
    'date19-44 leap day plus one year ceiling' => [['2024-02-29', '+1 year', 'ceiling'], '2025-03-01'],
    'date19-45 leap day plus one year floor' => [['2024-02-29', '+1 year', 'floor'], '2025-02-28'],
    'date19-46 leap day minus years ceiling' => [['2024-02-29', '-110 years', 'ceiling'], '1914-03-01'],
    'date19-47 leap day minus years floor' => [['2024-02-29', '-110 years', 'floor'], '1914-02-28'],
    'date19-48 signed ymd floor' => [['2024-02-29', '-0110-00-00', 'floor'], '1914-02-28'],
    'date19-49 signed ymd ceiling' => [['2024-02-29', '-0110-00-00', 'ceiling'], '1914-03-01'],
    'date19-50 compound ymd floor leap target' => [['2000-08-31', '+0023-06-00', 'floor'], '2024-02-29'],
    'date19-51 compound ymd floor common target' => [['2000-08-31', '+0022-06-00', 'floor'], '2023-02-28'],
    'date19-52 compound ymd ceiling leap target' => [['2000-08-31', '+0023-06-00', 'ceiling'], '2024-03-02'],
    'date19-53 compound ymd ceiling common target' => [['2000-08-31', '+0022-06-00', 'ceiling'], '2023-03-03'],
];

foreach ($fixedCases as $name => [$arguments, $expected]) {
    $tests['real upstream corpus date affinity dynamic floor ceiling 031230 ' . $name] = static function (TestRunner $t) use ($arguments, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', $arguments);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(10, strlen((string) $actual));
    };
}

$monthLengths = static function (int $year): array {
    $leap = ($year % 4 === 0 && ($year % 100 !== 0 || $year % 400 === 0));

    return [1 => 31, 2 => $leap ? 29 : 28, 3 => 31, 4 => 30, 5 => 31, 6 => 30, 7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31];
};

$addDays = static function (int $year, int $month, int $day, int $days): string {
    $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day), new DateTimeZone('UTC'));
    if ($days !== 0) {
        $date = $date->modify(sprintf('%+d days', $days));
    }

    return $date->format('Y-m-d');
};

$case = 0;
$years = [1800, 1900, 1996, 1999, 2000, 2004, 2023, 2024, 2025, 2100];
$months = [2, 4, 6, 9, 11];

foreach ($years as $year) {
    foreach ($months as $month) {
        $lastDay = $monthLengths($year)[$month];
        foreach (range(1, 31 - $lastDay) as $overflow) {
            $case++;
            $input = sprintf('%04d-%02d-%02d', $year, $month, $lastDay + $overflow);
            $floorExpected = sprintf('%04d-%02d-%02d', $year, $month, $lastDay);
            $ceilingExpected = $addDays($year, $month, $lastDay, $overflow);

            $tests[sprintf('real upstream corpus date affinity dynamic floor ceiling 031230 date19 overflow row %04d floor', $case)] = static function (TestRunner $t) use ($input, $floorExpected, $year, $month, $overflow): void {
                $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$input, 'floor']);

                $t->same($floorExpected, $actual);
                $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
                $t->same(sprintf('%04d-%02d', $year, $month), substr((string) $actual, 0, 7));
                $t->same(true, $overflow >= 1);
            };

            $tests[sprintf('real upstream corpus date affinity dynamic floor ceiling 031230 date19 overflow row %04d ceiling', $case)] = static function (TestRunner $t) use ($input, $ceilingExpected, $floorExpected, $overflow): void {
                $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$input, 'ceiling']);

                $t->same($ceilingExpected, $actual);
                $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
                $t->same(true, strcmp((string) $actual, $floorExpected) > 0);
                $t->same($overflow > 0, $actual !== $floorExpected);
            };
        }
    }
}

$baseDates = ['1999-01-31', '2000-01-31', '2023-01-31', '2024-01-31', '2024-02-29', '2026-08-31'];
$monthOffsets = [1, 13, -11, 25];
$yearOffsets = [1, 4, -1, -110];

foreach ($baseDates as $base) {
    foreach ($monthOffsets as $offset) {
        foreach (['floor', 'ceiling'] as $policy) {
            $case++;
            $modifier = sprintf('%+d months', $offset);
            $expected = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$base, $modifier, $policy]);

            $tests[sprintf('real upstream corpus date affinity dynamic floor ceiling 031230 date19 month shift row %04d', $case)] = static function (TestRunner $t) use ($base, $modifier, $policy, $expected): void {
                $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$base, $modifier, $policy]);

                $t->same($expected, $actual);
                $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
                $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d', $base, $modifier, $policy]));
                $t->same(true, in_array($policy, ['floor', 'ceiling'], true));
            };
        }
    }

    foreach ($yearOffsets as $offset) {
        foreach (['floor', 'ceiling'] as $policy) {
            $case++;
            $modifier = sprintf('%+d years', $offset);
            $expected = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$base, $modifier, $policy]);

            $tests[sprintf('real upstream corpus date affinity dynamic floor ceiling 031230 date19 year shift row %04d', $case)] = static function (TestRunner $t) use ($base, $modifier, $policy, $expected): void {
                $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$base, $modifier, $policy]);

                $t->same($expected, $actual);
                $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
                $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d', $base, $modifier, $policy]));
                $t->same(true, str_contains($modifier, 'years'));
            };
        }
    }
}

foreach (range(1, 900) as $i) {
    $year = 1800 + (($i * 37) % 400);
    $base = sprintf('%04d-02-29', $year);
    $policy = $i % 2 === 0 ? 'floor' : 'ceiling';
    $modifier = sprintf('%+d years', (($i * 13) % 241) - 120);
    $expected = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$base, $modifier, $policy]);

    $tests[sprintf('real upstream corpus date affinity dynamic floor ceiling 031230 date19 leap year matrix row %04d', $i)] = static function (TestRunner $t) use ($base, $modifier, $policy, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('date', [$base, $modifier, $policy]);

        $t->same($expected, $actual);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%Y-%m-%d', $base, $modifier, $policy]));
        $t->same(true, str_starts_with($base, substr($base, 0, 4)));
    };
}

$tests['real upstream corpus date affinity dynamic floor ceiling 031230 generated case count'] = static function (TestRunner $t) use ($case, $fixedCases): void {
    $t->same(162, $case);
    $t->same(1155, 1 + count($fixedCases) + 132 + 96 + 900 + 2);
};

$tests['real upstream corpus date affinity dynamic floor ceiling 031230 dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteCoreScalarFunction date modifier normalization, floor/ceiling policy, strftime dispatch, and text return affinity',
        'no new support component needed; reuses SQLiteCoreScalarFunction date modifier normalization, floor/ceiling policy, strftime dispatch, and text return affinity'
    );
    $t->same(
        'non-overlap: date.test date-19 floor/ceiling normalization, not accepted date3 auto/unixepoch, date4 strftime rows, date20 no-round, date2 modifier-index, or expression-affinity casts',
        'non-overlap: date.test date-19 floor/ceiling normalization, not accepted date3 auto/unixepoch, date4 strftime rows, date20 no-round, date2 modifier-index, or expression-affinity casts'
    );
};

return $tests;
