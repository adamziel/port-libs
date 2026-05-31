<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic unixepoch auto cites upstream date3 sections'] = static function (TestRunner $t): void {
    $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test';
    $source = (string) file_get_contents($upstream);

    $t->same(true, is_file($upstream));
    $t->contains("datetest 1.1 {unixepoch('1970-01-01')} {0}", $source);
    $t->contains('for {set i 1} {$i<=100} {incr i}', $source);
    $t->contains('foreach {tn jd date}', $source);
    $t->contains('datetest $tn "datetime($jd,\'auto\')" $date', $source);
    $t->contains("datetest 3.1 {datetime(2459607.05,'+1 hour','unixepoch')} {NULL}", $source);
    $t->contains("datetest 4.1 {datetime(2459607,'julianday')}           {2022-01-27 12:00:00}", $source);
};

$unixepochBoundaries = [
    'date3-1.1 epoch start' => ['1970-01-01', 0],
    'date3-1.2 second before epoch' => ['1969-12-31 23:59:59', -1],
    'date3-1.3 unsigned 32 bit max second' => ['2106-02-07 06:28:15', 4294967295],
    'date3-1.4 unsigned 32 bit rollover second' => ['2106-02-07 06:28:16', 4294967296],
    'date3-1.5 maximum SQLite datetime second' => ['9999-12-31 23:59:59', 253402300799],
    'date3-1.6 year zero minimum text second' => ['0000-01-01 00:00:00', -62167219200],
    'date3-1.8 millisecond input floors to integer second' => ['2022-01-27 12:59:28.052', 1643288368],
];

foreach ($unixepochBoundaries as $name => [$timeValue, $expected]) {
    $tests['real upstream corpus date affinity dynamic unixepoch ' . $name] = static function (TestRunner $t) use ($timeValue, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$timeValue]);

        $t->same($expected, $actual);
        $t->same('integer', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('coalesce', [$actual, 'NULL']));
    };
}

$dynamicUnixepochValues = [];
for ($i = 0; $i < 1000; $i++) {
    $dynamicUnixepochValues[$i] = -4294967295 + ($i * 991283);
}

foreach ($dynamicUnixepochValues as $case => $seconds) {
    $tests[sprintf('real upstream corpus date affinity dynamic date3-1.7 unixepoch identity row %04d', $case)] = static function (TestRunner $t) use ($seconds, $case): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', [$seconds, 'unixepoch']);

        $t->same($seconds, $actual, 'date3-1.7 unixepoch identity row ' . $case);
        $t->same(true, $actual === $seconds);
        $t->same('integer', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$autoBoundaries = [
    'date3-2.1 zero Julian day lower bound' => [0.0, '-4713-11-24 12:00:00'],
    'date3-2.2 Julian day upper bound' => [5373484.4999999, '9999-12-31 23:59:59'],
    'date3-2.3 Julian epoch day' => [2440587.5, '1970-01-01 00:00:00'],
    'date3-2.4 Julian second before epoch' => [2440587.49998843, '1969-12-31 23:59:59'],
    'date3-2.5 fractional Julian day' => [2440615.7475463, '1970-01-29 05:56:28'],
    'date3-2.10 negative unix timestamp' => [-1, '1969-12-31 23:59:59'],
    'date3-2.11 first unix timestamp above Julian range' => [5373485, '1970-03-04 04:38:05'],
    'date3-2.12 minimum unix timestamp' => [-210866760000, '-4713-11-24 12:00:00'],
    'date3-2.13 maximum unix timestamp' => [253402300799, '9999-12-31 23:59:59'],
    'date3-2.20 below unix timestamp range' => [-210866760001, null],
    'date3-2.21 above unix timestamp range' => [253402300800, null],
];

foreach ($autoBoundaries as $name => [$timeValue, $expected]) {
    $tests['real upstream corpus date affinity dynamic auto ' . $name] = static function (TestRunner $t) use ($timeValue, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue, 'auto']);

        $t->same($expected, $actual);
        $t->same($expected === null ? 'null' : 'text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$tests['real upstream corpus date affinity dynamic auto text values remain text-date values'] = static function (TestRunner $t): void {
    $autoDate = SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2022-01-29', 'auto']);
    $plainDate = SQLiteCoreScalarFunction::sqlFunctionArguments('date', ['2022-01-29']);

    $t->same($plainDate, $autoDate);
    $t->same(true, $autoDate === $plainDate);
    $t->same('text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$autoDate]));
};

$tests['real upstream corpus date affinity dynamic auto mixed time value equivalence'] = static function (TestRunner $t): void {
    $cases = [
        ['2022-01-27 13:15:44', '2022-01-27 13:15:44'],
        [2459607.05260275, '2022-01-27 13:15:44'],
        [1643289344, '2022-01-27 13:15:44'],
    ];

    foreach ($cases as $index => [$timeValue, $expected]) {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$timeValue, 'auto']);
        $t->same($expected, $actual, 'date3-2.40 mixed auto row ' . $index);
        $t->same(true, $actual === $expected);
    }
};

$modifierPositionCases = [
    'date3-3.1 unixepoch after hour modifier is null' => ['datetime', [2459607.05, '+1 hour', 'unixepoch'], null],
    'date3-3.2 unixepoch immediately after value accepts later hour modifier' => ['datetime', [2459607.05, 'unixepoch', '+1 hour'], '1970-01-29 12:13:27'],
    'date3-4.1 julianday immediately after numeric value' => ['datetime', [2459607, 'julianday'], '2022-01-27 12:00:00'],
    'date3-4.2 julianday after hour modifier is null' => ['datetime', [2459607, '+1 hour', 'julianday'], null],
    'date3-4.3 julianday rejects text time value' => ['datetime', ['2022-01-27', 'julianday'], null],
];

foreach ($modifierPositionCases as $name => [$functionName, $arguments, $expected]) {
    $tests['real upstream corpus date affinity dynamic modifier position ' . $name] = static function (TestRunner $t) use ($functionName, $arguments, $expected): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($functionName, $arguments);

        $t->same($expected, $actual);
        $t->same($expected === null ? 'null' : 'text', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$actual]));
    };
}

$tests['real upstream corpus date affinity dynamic first 63 unix timestamps are Julian days under auto'] = static function (TestRunner $t): void {
    $mismatchCount = 0;

    for ($days = -10; $days <= 100; $days++) {
        $calendar = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', ['1970-01-01', sprintf('%+d days', $days)]);
        $unix = SQLiteCoreScalarFunction::sqlFunctionArguments('unixepoch', ['1970-01-01', sprintf('%+d days', $days)]);
        $auto = SQLiteCoreScalarFunction::sqlFunctionArguments('datetime', [$unix, 'auto']);
        if ($calendar !== $auto) {
            $mismatchCount++;
        }
    }

    $t->same(63, $mismatchCount);
    $t->same('integer', SQLiteCoreScalarFunction::sqlFunctionArguments('typeof', [$mismatchCount]));
};

return $tests;
