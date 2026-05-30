<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$timediffCases = [
    'same instant' => ['2026-05-27 18:42:34', '2026-05-27 18:42:34', '+0000-00-00 00:00:00.000'],
    'one second forward' => ['2026-05-27 18:42:35', '2026-05-27 18:42:34', '+0000-00-00 00:00:01.000'],
    'one second backward' => ['2026-05-27 18:42:34', '2026-05-27 18:42:35', '-0000-00-00 00:00:01.000'],
    'minute boundary forward' => ['2026-05-27 18:43:00', '2026-05-27 18:42:34', '+0000-00-00 00:00:26.000'],
    'hour boundary forward' => ['2026-05-27 19:00:00', '2026-05-27 18:42:34', '+0000-00-00 00:17:26.000'],
    'day boundary forward' => ['2026-05-28 00:00:00', '2026-05-27 18:42:34', '+0000-00-00 05:17:26.000'],
    'month boundary forward' => ['2026-06-01 00:00:00', '2026-05-27 18:42:34', '+0000-00-04 05:17:26.000'],
    'year boundary forward' => ['2027-01-01 00:00:00', '2026-05-27 18:42:34', '+0000-07-04 05:17:26.000'],
    'leap day to next year' => ['2025-02-28', '2024-02-29', '+0000-11-30 00:00:00.000'],
    'next year to leap day' => ['2024-02-29', '2025-02-28', '-0000-11-30 00:00:00.000'],
    'month end shorter target' => ['2024-03-31', '2024-02-29', '+0000-01-02 00:00:00.000'],
    'month end longer target' => ['2024-02-29', '2024-03-31', '-0000-01-02 00:00:00.000'],
    'whole year' => ['2025-05-27 18:42:34', '2024-05-27 18:42:34', '+0001-00-00 00:00:00.000'],
    'negative whole year' => ['2024-05-27 18:42:34', '2025-05-27 18:42:34', '-0001-00-00 00:00:00.000'],
    'whole month' => ['2026-06-27 18:42:34', '2026-05-27 18:42:34', '+0000-01-00 00:00:00.000'],
    'negative whole month' => ['2026-05-27 18:42:34', '2026-06-27 18:42:34', '-0000-01-00 00:00:00.000'],
    'whole day' => ['2026-05-28 18:42:34', '2026-05-27 18:42:34', '+0000-00-01 00:00:00.000'],
    'negative whole day' => ['2026-05-27 18:42:34', '2026-05-28 18:42:34', '-0000-00-01 00:00:00.000'],
    'whole hour' => ['2026-05-27 19:42:34', '2026-05-27 18:42:34', '+0000-00-00 01:00:00.000'],
    'negative whole hour' => ['2026-05-27 18:42:34', '2026-05-27 19:42:34', '-0000-00-00 01:00:00.000'],
    'whole minute' => ['2026-05-27 18:43:34', '2026-05-27 18:42:34', '+0000-00-00 00:01:00.000'],
    'negative whole minute' => ['2026-05-27 18:42:34', '2026-05-27 18:43:34', '-0000-00-00 00:01:00.000'],
    'date only defaults midnight' => ['2026-05-28', '2026-05-27', '+0000-00-01 00:00:00.000'],
    'datetime versus date' => ['2026-05-28 06:30:00', '2026-05-28', '+0000-00-00 06:30:00.000'],
    'space separated date time' => ['2026-05-28 06:30', '2026-05-28 05:15', '+0000-00-00 01:15:00.000'],
    't separated date time' => ['2026-05-28T06:30:00', '2026-05-28T05:15:00', '+0000-00-00 01:15:00.000'],
    'z suffix accepted' => ['2026-05-28T06:30:00Z', '2026-05-28T05:15:00Z', '+0000-00-00 01:15:00.000'],
    'fractional seconds preserved in diff output' => ['2026-05-28 06:30:00.900', '2026-05-28 06:30:00.100', '+0000-00-00 00:00:00.800'],
    'numeric julian day large span forward' => [2460460.5, 2460459.5, '+0000-00-01 00:00:00.000'],
    'numeric julian day large span backward' => [2460459.5, 2460460.5, '-0000-00-01 00:00:00.000'],
    'julian day numeric forward' => [2440588.5, 2440587.5, '+0000-00-01 00:00:00.000'],
    'julian day numeric backward' => [2440587.5, 2440588.5, '-0000-00-01 00:00:00.000'],
    'mixed julian day and text' => [2440588.5, '1970-01-01 00:00:00', '+0000-00-01 00:00:00.000'],
    'application cron daily interval' => ['2026-05-28 04:00:00', '2026-05-27 04:00:00', '+0000-00-01 00:00:00.000'],
    'application monthly archive interval' => ['2026-06-01', '2026-05-01', '+0000-01-00 00:00:00.000'],
    'application import elapsed label' => ['2026-05-27 18:42:34', '2026-05-27 18:40:04', '+0000-00-00 00:02:30.000'],
    'application reverse import elapsed label' => ['2026-05-27 18:40:04', '2026-05-27 18:42:34', '-0000-00-00 00:02:30.000'],
    'application yearly cleanup interval' => ['2027-01-01', '2026-01-01', '+0001-00-00 00:00:00.000'],
    'application leap cleanup interval' => ['2024-03-01', '2024-02-28', '+0000-00-02 00:00:00.000'],
    'application option expiration drift' => ['2026-05-27 18:42:34', '2026-05-26 16:12:34', '+0000-00-01 02:30:00.000'],
];

foreach ($timediffCases as $name => [$left, $right, $expected]) {
    $tests['upstream timediff corpus ' . $name] = static function (TestRunner $t) use ($left, $right, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', [$left, $right]));
    };
}

$tests['upstream timediff corpus null left propagates'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', [null, '2026-05-27 18:42:34']));
};

$tests['upstream timediff corpus null right propagates'] = static function (TestRunner $t): void {
    $t->same(null, SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['2026-05-27 18:42:34', null]));
};

$tests['upstream timediff corpus rejects too few arguments'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['2026-05-27 18:42:34']));
};

$tests['upstream timediff corpus rejects too many arguments'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['2026-05-27', '2026-05-26', '+1 day']));
};

$tests['upstream timediff corpus rejects malformed left value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['not-a-date', '2026-05-27']));
};

$tests['upstream timediff corpus rejects malformed right value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['2026-05-27', 'not-a-date']));
};

$tests['upstream timediff corpus application schedule summary'] = static function (TestRunner $t): void {
    $summary = [
        'daily' => SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['2026-05-28 04:00:00', '2026-05-27 04:00:00']),
        'elapsed' => SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['2026-05-27 18:42:34', '2026-05-27 18:40:04']),
        'late' => SQLiteCoreScalarFunction::sqlFunctionArguments('timediff', ['2026-05-27 18:40:04', '2026-05-27 18:42:34']),
    ];

    $t->same([
        'daily' => '+0000-00-01 00:00:00.000',
        'elapsed' => '+0000-00-00 00:02:30.000',
        'late' => '-0000-00-00 00:02:30.000',
    ], $summary);
};

return $tests;
