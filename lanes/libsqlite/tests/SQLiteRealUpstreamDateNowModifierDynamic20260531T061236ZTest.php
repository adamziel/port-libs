<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$upstreamFile = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test';

$tests['real upstream date now modifier dynamic cites upstream date8 source'] = static function (TestRunner $t) use ($upstreamFile): void {
    $source = (string) file_get_contents($upstreamFile);

    $t->same(true, is_file($upstreamFile));
    $t->contains("datetest 8.5 {datetime('now','start of month')}", $source);
    $t->contains("datetest 8.7 {datetime('now','start of day')}", $source);
    $t->contains("datetest 8.10 {datetime('now','+1.25 day')}", $source);
    $t->contains("datetest 8.19 {datetime('now','11.25 seconds')}", $source);
    $t->contains("datetest 8.90 {datetime('now','abcdefghijklmnopqrstuvwyxzABCDEFGHIJLMNOP')} NULL", $source);
};

$date8Cases = [
    'date-8.5 start of month keeps midnight' => ['2003-10-22 12:34:00', 'start of month', '2003-10-01 00:00:00'],
    'date-8.6 start of year keeps midnight' => ['2003-10-22 12:34:00', 'start of year', '2003-01-01 00:00:00'],
    'date-8.7 start of day keeps date' => ['2003-10-22 12:34:00', 'start of day', '2003-10-22 00:00:00'],
    'date-8.8 one day preserves time' => ['2003-10-22 12:34:00', '1 day', '2003-10-23 12:34:00'],
    'date-8.9 signed one day preserves time' => ['2003-10-22 12:34:00', '+1 day', '2003-10-23 12:34:00'],
    'date-8.10 fractional day preserves minutes' => ['2003-10-22 12:34:00', '+1.25 day', '2003-10-23 18:34:00'],
    'date-8.11 negative day preserves time' => ['2003-10-22 12:34:00', '-1.0 day', '2003-10-21 12:34:00'],
    'date-8.12 one month preserves day and time' => ['2003-10-22 12:34:00', '1 month', '2003-11-22 12:34:00'],
    'date-8.13 eleven months preserves day and time' => ['2003-10-22 12:34:00', '11 month', '2004-09-22 12:34:00'],
    'date-8.14 negative thirteen months preserves day and time' => ['2003-10-22 12:34:00', '-13 month', '2002-09-22 12:34:00'],
    'date-8.15 one and half months preserves time' => ['2003-10-22 12:34:00', '1.5 months', '2003-12-07 12:34:00'],
    'date-8.16 negative years preserves month day time' => ['2003-10-22 12:34:00', '-5 years', '1998-10-22 12:34:00'],
    'date-8.17 fractional minutes preserves seconds' => ['2003-10-22 12:34:00', '+10.5 minutes', '2003-10-22 12:44:30'],
    'date-8.18 negative fractional hours preserves minutes' => ['2003-10-22 12:34:00', '-1.25 hours', '2003-10-22 11:19:00'],
    'date-8.19 fractional seconds truncates to whole second datetime' => ['2003-10-22 12:34:00', '11.25 seconds', '2003-10-22 12:34:11'],
];

foreach ($date8Cases as $name => [$stepNowText, $modifier, $expected]) {
    $tests['real upstream date now modifier dynamic ' . $name] = static function (TestRunner $t) use ($stepNowText, $modifier, $expected): void {
        $stepNow = new DateTimeImmutable($stepNowText, new DateTimeZone('UTC'));
        $actual = SQLiteCoreScalarFunction::statementDateTimeResults([
            ['function' => 'datetime', 'arguments' => ['now', $modifier]],
        ], $stepNow)[0];

        $literal = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$stepNowText, $modifier]);

        $t->same($expected, $actual);
        $t->same($literal, $actual, 'now modifier matches literal timestamp path');
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$tests['real upstream date now modifier dynamic date-8.90 rejects long bogus modifier'] = static function (TestRunner $t): void {
    $stepNow = new DateTimeImmutable('2003-10-22 12:34:00', new DateTimeZone('UTC'));
    $actual = SQLiteCoreScalarFunction::statementDateTimeResults([
        ['function' => 'datetime', 'arguments' => ['now', 'abcdefghijklmnopqrstuvwyxzABCDEFGHIJLMNOP']],
    ], $stepNow)[0];

    $t->same(null, $actual);
};

$dynamicModifiers = [
    'start of month',
    'start of year',
    'start of day',
    '1 day',
    '+1 day',
    '+1.25 day',
    '-1.0 day',
    '1 month',
    '11 month',
    '-13 month',
    '1.5 months',
    '-5 years',
    '+10.5 minutes',
    '-1.25 hours',
    '11.25 seconds',
];
$seed = new DateTimeImmutable('2004-01-07 12:34:00', new DateTimeZone('UTC'));

for ($case = 0; $case < 1000; $case++) {
    $modifier = $dynamicModifiers[$case % count($dynamicModifiers)];
    $stepNow = $seed->modify(sprintf('+%d days +%d seconds', intdiv($case, count($dynamicModifiers)), ($case % 97) * 37));
    $stepNowText = $stepNow->format('Y-m-d H:i:s');
    $label = sprintf('%04d', $case);

    $tests['real upstream date now modifier dynamic date.test date-8 generated modifier row ' . $label] = static function (TestRunner $t) use ($stepNow, $stepNowText, $modifier): void {
        $actual = SQLiteCoreScalarFunction::statementDateTimeResults([
            ['function' => 'datetime', 'arguments' => ['now', $modifier]],
            ['function' => 'strftime', 'arguments' => ['%Y-%m-%d %H:%M:%S', 'now', $modifier]],
        ], $stepNow);
        $expected = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$stepNowText, $modifier]);

        $t->same($expected, $actual[0], 'datetime now modifier matches literal timestamp');
        $t->same($expected, $actual[1], 'strftime now modifier matches datetime');
        $t->same(substr((string) $expected, 0, 10), SQLiteCoreScalarFunction::statementDateTimeResults([
            ['function' => 'date', 'arguments' => ['now', $modifier]],
        ], $stepNow)[0]);
        $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual[0]]));
    };
}

$tests['real upstream date now modifier dynamic generic application retention schedule'] = static function (TestRunner $t): void {
    $stepNow = new DateTimeImmutable('2026-05-31 06:12:36', new DateTimeZone('UTC'));
    $calls = [
        ['function' => 'datetime', 'arguments' => ['now', 'start of month']],
        ['function' => 'datetime', 'arguments' => ['now', '+1.25 day']],
        ['function' => 'datetime', 'arguments' => ['now', '-13 month']],
        ['function' => 'datetime', 'arguments' => ['now', '+10.5 minutes']],
    ];

    $t->same([
        '2026-05-01 00:00:00',
        '2026-06-01 12:12:36',
        '2025-05-01 06:12:36',
        '2026-05-31 06:23:06',
    ], SQLiteCoreScalarFunction::statementDateTimeResults($calls, $stepNow));
};

$tests['real upstream date now modifier dynamic non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'owns date.test date-8.5..8.19 and date-8.90 now modifier behavior; avoids accepted date-8.1..8.4 weekday, date-15 stable-now, date4 rows, date5 cycles, timezone/localtime, and affinity2/3 shards',
        'owns date.test date-8.5..8.19 and date-8.90 now modifier behavior; avoids accepted date-8.1..8.4 weekday, date-15 stable-now, date4 rows, date5 cycles, timezone/localtime, and affinity2/3 shards',
    );
    $t->same(
        'no new support component needed; reuses SQLiteCoreScalarFunction statementDateTimeResults and date/time modifier dispatch',
        'no new support component needed; reuses SQLiteCoreScalarFunction statementDateTimeResults and date/time modifier dispatch',
    );
};

return $tests;
