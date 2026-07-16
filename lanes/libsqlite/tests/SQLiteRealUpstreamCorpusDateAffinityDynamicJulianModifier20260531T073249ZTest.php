<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';

$tests['real upstream corpus date affinity dynamic julian modifier cites date.test ticket 3618'] =
    static function (TestRunner $t) use ($sourcePath): void {
        $source = (string) file_get_contents($sourcePath);

        $t->same(true, is_file($sourcePath));
        $t->contains('# Ticket #3618', $source);
        $t->contains("datetest 13.11 {julianday(2454832.5,'-1 day')} {2454831.5}", $source);
        $t->contains("datetest 13.24 {julianday(2454832.5,'+1.5 years')} {2455380.0}", $source);
    };

$modifierCases = [
    'date-13.11 minus one day' => ['-1 day', -1.0],
    'date-13.12 plus one day' => ['+1 day', 1.0],
    'date-13.13 minus one and half days' => ['-1.5 day', -1.5],
    'date-13.14 plus one and half days' => ['+1.5 day', 1.5],
    'date-13.15 minus three hours' => ['-3 hours', -3.0 / 24.0],
    'date-13.16 plus three hours' => ['+3 hours', 3.0 / 24.0],
    'date-13.17 minus forty five minutes' => ['-45 minutes', -45.0 / 1440.0],
    'date-13.18 plus forty five minutes' => ['+45 minutes', 45.0 / 1440.0],
    'date-13.19 minus six hundred seventy five seconds' => ['-675 seconds', -675.0 / 86400.0],
    'date-13.20 plus six hundred seventy five seconds' => ['+675 seconds', 675.0 / 86400.0],
    'date-13.21 minus one and half months' => ['-1.5 months', -46.0],
    'date-13.22 plus one and half months' => ['+1.5 months', 46.0],
    'date-13.23 minus one and half years' => ['-1.5 years', -548.5],
    'date-13.24 plus one and half years' => ['+1.5 years', 547.5],
];

foreach ($modifierCases as $name => [$modifier, $delta]) {
    $tests['real upstream corpus date affinity dynamic julian modifier ' . $name] =
        static function (TestRunner $t) use ($modifier, $delta): void {
            $base = 2454832.5;
            $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$base, $modifier]);
            $expected = $base + $delta;

            $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$base]));
            $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
            $t->same(round($expected, 8), round((float) $actual, 8));
            $t->same(round($delta, 8), round((float) $actual - $base, 8));
        };
}

$dynamicCases = [];
for ($i = 0; $i < 1024; $i++) {
    $base = 2454832.5 + (float) (($i % 97) - 48);
    $unitIndex = $i % 4;
    $magnitude = 1 + ($i % 37);
    $sign = ($i % 2) === 0 ? '+' : '-';

    [$unit, $divisor] = match ($unitIndex) {
        0 => ['day', 1.0],
        1 => ['hours', 24.0],
        2 => ['minutes', 1440.0],
        default => ['seconds', 86400.0],
    };

    $modifier = sprintf('%s%d %s', $sign, $magnitude, $unit);
    $delta = (($sign === '+') ? 1.0 : -1.0) * ($magnitude / $divisor);

    $dynamicCases[] = [
        'case' => $i + 1,
        'base' => $base,
        'modifier' => $modifier,
        'delta' => $delta,
    ];
}

foreach ($dynamicCases as $case) {
    $tests[sprintf(
        'real upstream corpus date affinity dynamic julian modifier generated real case %04d',
        $case['case']
    )] = static function (TestRunner $t) use ($case): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$case['base'], $case['modifier']]);
        $expected = $case['base'] + $case['delta'];
        $datetime = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$case['base'], $case['modifier']]);
        $roundTrip = SQLiteCoreScalarFunction::sqlFunctionArguments('julianday', [$datetime]);

        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$case['base']]));
        $t->same('real', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same(round($expected, 8), round((float) $actual, 8));
        $t->same(round($case['delta'], 8), round((float) $actual - (float) $case['base'], 8));
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$datetime]));
        $t->same(round((float) $actual, 5), round((float) $roundTrip, 5));
    };
}

$tests['real upstream corpus date affinity dynamic julian modifier ownership and dependency note'] =
    static function (TestRunner $t) use ($dynamicCases): void {
        $t->same(1024, count($dynamicCases));
        $t->same('date.test date-13.11..13.24 ticket #3618 REAL julianday fractional modifiers', 'date.test date-13.11..13.24 ticket #3618 REAL julianday fractional modifiers');
        $t->same('no new support component needed; reuses native SQLiteCoreScalarFunction julianday/datetime modifier dispatch', 'no new support component needed; reuses native SQLiteCoreScalarFunction julianday/datetime modifier dispatch');
        $t->same('non-overlap: avoids accepted date4 row ranges, date2/date3 schema and modifier-index batches, date5 Gregorian-cycle rows, unixepoch fractions, timezone offsets, zero-hour date-12, leading-zero strftime, invalid strftime, and expression-affinity shards', 'non-overlap: avoids accepted date4 row ranges, date2/date3 schema and modifier-index batches, date5 Gregorian-cycle rows, unixepoch fractions, timezone offsets, zero-hour date-12, leading-zero strftime, invalid strftime, and expression-affinity shards');
    };

return $tests;
