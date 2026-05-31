<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$unixepochCases = [
    'date3-1.1 unix epoch start' => ['1970-01-01', 0],
    'date3-1.2 second before unix epoch' => ['1969-12-31 23:59:59', -1],
    'date3-1.3 unsigned 32 bit max second' => ['2106-02-07 06:28:15', 4294967295],
    'date3-1.4 unsigned 32 bit rollover second' => ['2106-02-07 06:28:16', 4294967296],
    'date3-1.5 sqlite max calendar second' => ['9999-12-31 23:59:59', 253402300799],
    'date3-1.6 sqlite zero year start' => ['0000-01-01 00:00:00', -62167219200],
    'date3-1.8 millisecond input returns integer second' => ['2022-01-27 12:59:28.052', 1643288368],
];

foreach ($unixepochCases as $name => [$value, $expected]) {
    $tests['real upstream corpus date3 dynamic ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$value]));
    };
}

// Source truth: SQLite upstream test/date3.test date3-1.7 randomized loop.
// The upstream property is unixepoch(X,'unixepoch') == X for integer Unix
// timestamps. Use a deterministic sequence so the PHP corpus is reproducible.
for ($index = 1; $index <= 1000; $index++) {
    $value = (($index * 2654435761) % 68719476735) - 4294967295;
    $tests['real upstream corpus date3 dynamic date3-1.7.' . $index . ' unixepoch integer identity'] = static function (TestRunner $t) use ($value): void {
        $t->same($value, SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$value, 'unixepoch']));
    };
}

$autoCases = [
    'date3-2.1 lower julian boundary' => [0.0, '-4713-11-24 12:00:00'],
    'date3-2.2 upper julian boundary' => [5373484.4999999, '9999-12-31 23:59:59'],
    'date3-2.3 unix epoch as julian day' => [2440587.5, '1970-01-01 00:00:00'],
    'date3-2.4 second before unix epoch as julian day' => [2440587.49998843, '1969-12-31 23:59:59'],
    'date3-2.5 late january julian day' => [2440615.7475463, '1970-01-29 05:56:28'],
    'date3-2.10 negative unix timestamp' => [-1, '1969-12-31 23:59:59'],
    'date3-2.11 first unix-timestamp value above julian range' => [5373485, '1970-03-04 04:38:05'],
    'date3-2.12 minimum accepted unix timestamp' => [-210866760000, '-4713-11-24 12:00:00'],
    'date3-2.13 maximum accepted unix timestamp' => [253402300799, '9999-12-31 23:59:59'],
    'date3-2.20 below accepted unix timestamp range' => [-210866760001, null],
    'date3-2.21 above accepted unix timestamp range' => [253402300800, null],
];

foreach ($autoCases as $name => [$value, $expected]) {
    $tests['real upstream corpus date3 dynamic ' . $name . ' auto modifier'] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value, 'auto']));
    };
}

$tests['real upstream corpus date3 dynamic date3-2.30 auto text modifier no-op'] = static function (TestRunner $t): void {
    $withAuto = SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2022-01-29', 'auto']);
    $withoutAuto = SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2022-01-29']);

    $t->same($withoutAuto, $withAuto);
};

$autoMixedRows = [
    ['2022-01-27 13:15:44', '2022-01-27 13:15:44'],
    [2459607.05260275, '2022-01-27 13:15:44'],
    [1643289344, '2022-01-27 13:15:44'],
];

foreach ($autoMixedRows as $offset => [$timeValue, $expected]) {
    $tests['real upstream corpus date3 dynamic date3-2.40 mixed auto row ' . ($offset + 1)] = static function (TestRunner $t) use ($timeValue, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue, 'auto']));
    };
}

$modifierOrderCases = [
    'date3-3.1 unixepoch after hour modifier rejected' => [[2459607.05, '+1 hour', 'unixepoch'], null],
    'date3-3.2 unixepoch immediately after numeric value' => [[2459607.05, 'unixepoch', '+1 hour'], '1970-01-29 12:13:27'],
    'date3-4.1 julianday immediately after numeric value' => [[2459607, 'julianday'], '2022-01-27 12:00:00'],
    'date3-4.2 julianday after hour modifier rejected' => [[2459607, '+1 hour', 'julianday'], null],
    'date3-4.3 julianday after text value rejected' => [['2022-01-27', 'julianday'], null],
];

foreach ($modifierOrderCases as $name => [$arguments, $expected]) {
    $tests['real upstream corpus date3 dynamic ' . $name] = static function (TestRunner $t) use ($arguments, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', $arguments));
    };
}

// Source truth: SQLite upstream test/date3.test date3-5.0 first-63-days
// ambiguity. The "auto" modifier treats early-1970 unix timestamps as Julian
// day values, so the first 63 day offsets do not round-trip as unix time.
for ($dayOffset = -10; $dayOffset <= 100; $dayOffset++) {
    $expectedMismatch = $dayOffset >= 0 && $dayOffset <= 62;
    $tests['real upstream corpus date3 dynamic date3-5.0 first 63 days ambiguity offset ' . $dayOffset] = static function (TestRunner $t) use ($dayOffset, $expectedMismatch): void {
        $date = sqliteRealUpstreamDate3DynamicDateFromUnixDayOffset($dayOffset);
        $unix = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$date]);
        $auto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$unix, 'auto']);

        $t->same($expectedMismatch, $auto !== $date);
    };
}

$tests['real upstream corpus date3 dynamic application retention mixed timestamp affinity'] = static function (TestRunner $t): void {
    $events = [
        ['key_name' => 'job.imported', 'time_value' => '2022-01-27 13:15:44'],
        ['key_name' => 'job.julian', 'time_value' => 2459607.05260275],
        ['key_name' => 'job.unix', 'time_value' => 1643289344],
        ['key_name' => 'job.early', 'time_value' => 31 * 86400],
    ];
    $normalized = [];

    foreach ($events as $event) {
        $normalized[$event['key_name']] = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$event['time_value'], 'auto']);
    }

    $t->same([
        'job.imported' => '2022-01-27 13:15:44',
        'job.julian' => '2022-01-27 13:15:44',
        'job.unix' => '2022-01-27 13:15:44',
        'job.early' => '2621-02-09 12:00:00',
    ], $normalized);
};

function sqliteRealUpstreamDate3DynamicDateFromUnixDayOffset(int $dayOffset): string
{
    $seconds = $dayOffset * 86400;

    return (new DateTimeImmutable('@' . (string) $seconds))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s');
}

return $tests;
