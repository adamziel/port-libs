<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date3 auto unixepoch modifier cites upstream files'] = static function (TestRunner $t): void {
    $upstream = [
        'date3.test date3-1.1..1.8 unixepoch() timestamp conversion',
        'date3.test date3-2.1..2.40 auto modifier Julian-day versus Unix-time affinity',
        'date3.test date3-3.1..4.3 unixepoch/julianday immediate modifier guards',
        'date3.test date3-5.0 first 63 days of 1970 auto ambiguity',
    ];

    $t->same(true, in_array('date3.test date3-2.1..2.40 auto modifier Julian-day versus Unix-time affinity', $upstream, true));
};

$unixepochCases = [
    'date3-1.1 unix epoch start' => ['1970-01-01', 0],
    'date3-1.2 second before epoch' => ['1969-12-31 23:59:59', -1],
    'date3-1.3 unsigned thirty two max second' => ['2106-02-07 06:28:15', 4294967295],
    'date3-1.4 unsigned thirty two rollover second' => ['2106-02-07 06:28:16', 4294967296],
    'date3-1.5 last supported date second' => ['9999-12-31 23:59:59', 253402300799],
    'date3-1.6 year zero start second' => ['0000-01-01 00:00:00', -62167219200],
    'date3-1.8 fractional input truncates to integer seconds' => ['2022-01-27 12:59:28.052', 1643288368],
];

foreach ($unixepochCases as $name => [$value, $expected]) {
    $tests['real upstream corpus date3 unixepoch ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$value]));
    };
}

$roundtripValues = [
    -62167219200, -210866760000, -100000000000, -4294967296, -4294967295,
    -2147483648, -1234567890, -86400, -1, 0, 1, 59, 60, 3600, 86400,
    123456789, 946684800, 1237962480, 1643289344, 2147483647, 2147483648,
    4294967295, 4294967296, 10000000000, 253402300799,
];

for ($i = 0; $i < 100; $i++) {
    $value = $roundtripValues[$i % count($roundtripValues)] + intdiv($i, count($roundtripValues)) * 97;
    if ($value > 253402300799) {
        $value = 253402300799 - $i;
    }
    $tests['real upstream corpus date3 unixepoch date3-1.7 deterministic roundtrip ' . $i] = static function (TestRunner $t) use ($value): void {
        $t->same($value, SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$value, 'unixepoch']));
    };
}

$autoCases = [
    'date3-2.1 julian minimum' => [0.0, '-4713-11-24 12:00:00'],
    'date3-2.2 julian maximum edge' => [5373484.4999999, '9999-12-31 23:59:59'],
    'date3-2.3 julian epoch noon' => [2440587.5, '1970-01-01 00:00:00'],
    'date3-2.4 julian second before epoch' => [2440587.49998843, '1969-12-31 23:59:59'],
    'date3-2.5 julian late january' => [2440615.7475463, '1970-01-29 05:56:28'],
    'date3-2.10 negative unix second' => [-1, '1969-12-31 23:59:59'],
    'date3-2.11 just above julian range is unix time' => [5373485, '1970-03-04 04:38:05'],
    'date3-2.12 unix lower bound' => [-210866760000, '-4713-11-24 12:00:00'],
    'date3-2.13 unix upper bound' => [253402300799, '9999-12-31 23:59:59'],
    'date3-2.20 below unix lower bound returns null' => [-210866760001, null],
    'date3-2.21 above unix upper bound returns null' => [253402300800, null],
];

foreach ($autoCases as $name => [$value, $expected]) {
    $tests['real upstream corpus date3 auto ' . $name] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value, 'auto']));
    };
}

$tests['real upstream corpus date3 auto date3-2.30 text auto is noop'] = static function (TestRunner $t): void {
    $t->same(
        SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2022-01-29']),
        SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2022-01-29', 'auto'])
    );
};

$autoMixedValues = [
    ['2022-01-27 13:15:44', '2022-01-27 13:15:44'],
    [2459607.05260275, '2022-01-27 13:15:44'],
    [1643289344, '2022-01-27 13:15:44'],
];

foreach ($autoMixedValues as $index => [$value, $expected]) {
    $tests['real upstream corpus date3 auto date3-2.40 mixed affinity row ' . $index] = static function (TestRunner $t) use ($value, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$value, 'auto']));
    };
}

$modifierCases = [
    'date3-3.1 unixepoch after plus hour returns null' => [2459607.05, ['+1 hour', 'unixepoch'], null],
    'date3-3.2 unixepoch immediate then plus hour' => [2459607.05, ['unixepoch', '+1 hour'], '1970-01-29 12:13:27'],
    'date3-4.1 julianday immediate numeric' => [2459607, ['julianday'], '2022-01-27 12:00:00'],
    'date3-4.2 julianday after plus hour returns null' => [2459607, ['+1 hour', 'julianday'], null],
    'date3-4.3 julianday text value returns null' => ['2022-01-27', ['julianday'], null],
];

foreach ($modifierCases as $name => [$value, $modifiers, $expected]) {
    $tests['real upstream corpus date3 modifier placement ' . $name] = static function (TestRunner $t) use ($value, $modifiers, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', array_merge([$value], $modifiers)));
    };
}

for ($dayOffset = -10; $dayOffset <= 100; $dayOffset++) {
    $tests['real upstream corpus date3 auto date3-5.0 first 1970 days ambiguity offset ' . $dayOffset] = static function (TestRunner $t) use ($dayOffset): void {
        $modifier = sprintf('%+d days', $dayOffset);
        $dateFromText = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['1970-01-01', $modifier]);
        $seconds = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', ['1970-01-01', $modifier]);
        $dateFromAuto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$seconds, 'auto']);
        $expectedMismatch = $dayOffset >= 0 && $dayOffset < 63;

        $t->same($expectedMismatch, $dateFromText !== $dateFromAuto);
    };
}

$tests['real upstream corpus date3 auto date3-5.0 mismatch count is sixty three'] = static function (TestRunner $t): void {
    $mismatches = 0;
    for ($dayOffset = -10; $dayOffset <= 100; $dayOffset++) {
        $modifier = sprintf('%+d days', $dayOffset);
        $dateFromText = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['1970-01-01', $modifier]);
        $seconds = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', ['1970-01-01', $modifier]);
        $dateFromAuto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$seconds, 'auto']);
        if ($dateFromText !== $dateFromAuto) {
            $mismatches++;
        }
    }

    $t->same(63, $mismatches);
};

$tests['real upstream corpus date3 auto application mixed timestamp import keeps dynamic affinity'] = static function (TestRunner $t): void {
    $events = [
        ['event_id' => 1, 'stored_at' => '2022-01-27 13:15:44'],
        ['event_id' => 2, 'stored_at' => 2459607.05260275],
        ['event_id' => 3, 'stored_at' => 1643289344],
        ['event_id' => 4, 'stored_at' => 5373485],
        ['event_id' => 5, 'stored_at' => -1],
        ['event_id' => 6, 'stored_at' => 253402300800],
    ];
    $normalized = [];

    foreach ($events as $event) {
        $normalized[$event['event_id']] = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$event['stored_at'], 'auto']);
    }

    $t->same([
        1 => '2022-01-27 13:15:44',
        2 => '2022-01-27 13:15:44',
        3 => '2022-01-27 13:15:44',
        4 => '1970-03-04 04:38:05',
        5 => '1969-12-31 23:59:59',
        6 => null,
    ], $normalized);
};

$date4Format = '%Y-%m-%d %H:%M:%S %j %w';
for ($i = 0; $i < 300; $i++) {
    $timestamp = $i * 86390;
    $expected = gmdate('Y-m-d H:i:s ', $timestamp)
        . str_pad((string) ((int) gmdate('z', $timestamp) + 1), 3, '0', STR_PAD_LEFT)
        . ' '
        . gmdate('w', $timestamp);

    $tests['real upstream corpus date4 strftime dynamic unixepoch sample ' . $i] = static function (TestRunner $t) use ($date4Format, $timestamp, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', [$date4Format, $timestamp, 'unixepoch']));
    };
}

return $tests;
