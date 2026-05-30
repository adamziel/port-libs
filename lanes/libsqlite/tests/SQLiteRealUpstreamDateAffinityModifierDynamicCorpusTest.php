<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCoreScalarFunction;

$tests = [];

$tests['real upstream corpus date affinity dynamic cites upstream files'] = static function (TestRunner $t): void {
    $upstream = [
        'date.test date-2.2c-0..399 strftime fractional unixepoch milliseconds',
        'date.test date-2.3..2.51 weekday/start/modifier validation',
        'affinity2.test affinity2-100..150 insert affinity storage classes',
        'affinity2.test affinity2-200..300 comparison affinity operator cases',
        'affinity2.test affinity2-500..507 unary blob/text numeric comparison ticket d99f1ffe836c591ac57f',
    ];

    $t->same(true, in_array('date.test date-2.2c-0..399 strftime fractional unixepoch milliseconds', $upstream, true));
    $t->same(true, in_array('affinity2.test affinity2-200..300 comparison affinity operator cases', $upstream, true));
};

for ($i = 0; $i < 400; $i++) {
    $timestamp = sprintf('1237962480.%03d', $i);
    $expected = sprintf('06:28:00.%03d', $i);

    $tests['real upstream corpus date date-2.2c fractional unixepoch millisecond ' . $i] = static function (TestRunner $t) use ($timestamp, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ['%H:%M:%f', $timestamp, 'unixepoch']));
    };
}

$dateModifierCases = [
    'date-2.3 weekday sunday' => ['date', ['2003-10-22', 'weekday 0'], '2003-10-26'],
    'date-2.4 weekday monday' => ['date', ['2003-10-22', 'weekday 1'], '2003-10-27'],
    'date-2.4a weekday extra spaces' => ['date', ['2003-10-22', 'weekday  1'], '2003-10-27'],
    'date-2.4b weekday suffix rejected' => ['date', ['2003-10-22', 'weekday  1x'], null],
    'date-2.4c negative weekday rejected' => ['date', ['2003-10-22', 'weekday  -1'], null],
    'date-2.4d misspelled weekday rejected' => ['date', ['2003-10-22', 'weakday  1x'], null],
    'date-2.4e empty weekday rejected' => ['date', ['2003-10-22', 'weekday '], null],
    'date-2.5 weekday tuesday' => ['date', ['2003-10-22', 'weekday 2'], '2003-10-28'],
    'date-2.6 weekday same day' => ['date', ['2003-10-22', 'weekday 3'], '2003-10-22'],
    'date-2.7 weekday thursday' => ['date', ['2003-10-22', 'weekday 4'], '2003-10-23'],
    'date-2.8 weekday friday' => ['date', ['2003-10-22', 'weekday 5'], '2003-10-24'],
    'date-2.9 weekday saturday' => ['date', ['2003-10-22', 'weekday 6'], '2003-10-25'],
    'date-2.10 weekday seven rejected' => ['date', ['2003-10-22', 'weekday 7'], null],
    'date-2.11 fractional weekday rejected' => ['date', ['2003-10-22', 'weekday 5.5'], null],
    'date-2.12 datetime weekday keeps time' => ['datetime', ['2003-10-22 12:34', 'weekday 0'], '2003-10-26 12:34:00'],
    'date-2.13 start of month' => ['datetime', ['2003-10-22 12:34', 'start of month'], '2003-10-01 00:00:00'],
    'date-2.14 start of year' => ['datetime', ['2003-10-22 12:34', 'start of year'], '2003-01-01 00:00:00'],
    'date-2.15 start of day' => ['datetime', ['2003-10-22 12:34', 'start of day'], '2003-10-22 00:00:00'],
    'date-2.15a incomplete start rejected' => ['datetime', ['2003-10-22 12:34', 'start of'], null],
    'date-2.15b bogus start rejected' => ['datetime', ['2003-10-22 12:34', 'start of bogus'], null],
    'date-2.16 time truncates fractional seconds' => ['time', ['12:34:56.43'], '12:34:56'],
    'date-2.17 one day' => ['datetime', ['2003-10-22 12:34', '1 day'], '2003-10-23 12:34:00'],
    'date-2.18 plus one day' => ['datetime', ['2003-10-22 12:34', '+1 day'], '2003-10-23 12:34:00'],
    'date-2.19 plus fractional day' => ['datetime', ['2003-10-22 12:34', '+1.25 day'], '2003-10-23 18:34:00'],
    'date-2.20 minus one day' => ['datetime', ['2003-10-22 12:34', '-1.0 day'], '2003-10-21 12:34:00'],
    'date-2.21 one month' => ['datetime', ['2003-10-22 12:34', '1 month'], '2003-11-22 12:34:00'],
    'date-2.22 eleven months' => ['datetime', ['2003-10-22 12:34', '11 month'], '2004-09-22 12:34:00'],
    'date-2.23 negative thirteen months' => ['datetime', ['2003-10-22 12:34', '-13 month'], '2002-09-22 12:34:00'],
    'date-2.24 fractional months' => ['datetime', ['2003-10-22 12:34', '1.5 months'], '2003-12-07 12:34:00'],
    'date-2.25 minus years' => ['datetime', ['2003-10-22 12:34', '-5 years'], '1998-10-22 12:34:00'],
    'date-2.26 fractional minutes' => ['datetime', ['2003-10-22 12:34', '+10.5 minutes'], '2003-10-22 12:44:30'],
    'date-2.27 negative fractional hours' => ['datetime', ['2003-10-22 12:34', '-1.25 hours'], '2003-10-22 11:19:00'],
    'date-2.28 fractional seconds truncates' => ['datetime', ['2003-10-22 12:34', '11.25 seconds'], '2003-10-22 12:34:11'],
    'date-2.29 bogus unit rejected' => ['datetime', ['2003-10-22 12:24', '+5 bogus'], null],
    'date-2.30 plus punctuation rejected' => ['datetime', ['2003-10-22 12:24', '+++'], null],
    'date-2.31 scientific unsupported unit rejected' => ['datetime', ['2003-10-22 12:24', '+12.3e4 femtoseconds'], null],
    'date-2.32 microsecond shorthand rejected' => ['datetime', ['2003-10-22 12:24', '+12.3e4 uS'], null],
    'date-2.33 unknown unit length three rejected' => ['datetime', ['2003-10-22 12:24', '+1 abc'], null],
    'date-2.34 unknown unit length four rejected' => ['datetime', ['2003-10-22 12:24', '+1 abcd'], null],
    'date-2.35 unknown unit length five rejected' => ['datetime', ['2003-10-22 12:24', '+1 abcde'], null],
    'date-2.36 unknown unit length six rejected' => ['datetime', ['2003-10-22 12:24', '+1 abcdef'], null],
    'date-2.37 unknown unit length seven rejected' => ['datetime', ['2003-10-22 12:24', '+1 abcdefg'], null],
    'date-2.38 unknown unit length eight rejected' => ['datetime', ['2003-10-22 12:24', '+1 abcdefgh'], null],
    'date-2.39 unknown unit length nine rejected' => ['datetime', ['2003-10-22 12:24', '+1 abcdefghi'], null],
    'date-2.41 seconds singular' => ['datetime', ['2003-10-22 12:24', '23 seconds'], '2003-10-22 12:24:23'],
    'date-2.42 second overflow minutes' => ['datetime', ['2003-10-22 12:24', '345 second'], '2003-10-22 12:29:45'],
    'date-2.43 padded second four' => ['datetime', ['2003-10-22 12:24', '4 second'], '2003-10-22 12:24:04'],
    'date-2.44 padded second fifty six' => ['datetime', ['2003-10-22 12:24', '56 second'], '2003-10-22 12:24:56'],
    'date-2.45 sixty seconds' => ['datetime', ['2003-10-22 12:24', '60 second'], '2003-10-22 12:25:00'],
    'date-2.46 seventy seconds' => ['datetime', ['2003-10-22 12:24', '70 second'], '2003-10-22 12:25:10'],
    'date-2.47 fractional seconds truncates down' => ['datetime', ['2003-10-22 12:24', '8.6 seconds'], '2003-10-22 12:24:08'],
    'date-2.48 fractional seconds truncates down two' => ['datetime', ['2003-10-22 12:24', '9.4 second'], '2003-10-22 12:24:09'],
    'date-2.49 zero padded zero second' => ['datetime', ['2003-10-22 12:24', '0000 second'], '2003-10-22 12:24:00'],
    'date-2.50 zero padded one second' => ['datetime', ['2003-10-22 12:24', '0001 second'], '2003-10-22 12:24:01'],
    'date-2.51 nonsense rejected' => ['datetime', ['2003-10-22 12:24', 'nonsense'], null],
];

foreach ($dateModifierCases as $name => [$function, $arguments, $expected]) {
    $tests['real upstream corpus date modifier ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $t->same($expected, SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments));
    };
}

$affinityRows = [
    ['rowid' => 1, 'xi' => 1, 'xr' => 1.0, 'xb' => 1, 'xn' => 1, 'xt' => '1'],
    ['rowid' => 2, 'xi' => 2, 'xr' => 2.0, 'xb' => '2', 'xn' => 2, 'xt' => '2'],
    ['rowid' => 3, 'xi' => 3, 'xr' => 3.0, 'xb' => '03', 'xn' => 3, 'xt' => '03'],
];

$affinityStorageExpectations = [
    'affinity2-110 integer column storage' => ['xi', [['1', 'integer'], ['2', 'integer'], ['3', 'integer']]],
    'affinity2-120 real column storage' => ['xr', [['1', 'real'], ['2', 'real'], ['3', 'real']]],
    'affinity2-130 blob column storage' => ['xb', [['1', 'integer'], ['2', 'text'], ['03', 'text']]],
    'affinity2-140 numeric column storage' => ['xn', [['1', 'integer'], ['2', 'integer'], ['3', 'integer']]],
    'affinity2-150 text column storage' => ['xt', [['1', 'text'], ['2', 'text'], ['03', 'text']]],
];

foreach ($affinityStorageExpectations as $name => [$column, $expected]) {
    $tests['real upstream corpus affinity storage ' . $name] = static function (TestRunner $t) use ($affinityRows, $column, $expected): void {
        $actual = array_map(static fn (array $row): array => [(string) $row[$column], SQLiteAffinityComparison::storageClass($row[$column])], $affinityRows);
        $t->same($expected, $actual);
    };
}

$affinityComparisonCases = [];
foreach ($affinityRows as $row) {
    $affinityComparisonCases['affinity2-200 row ' . $row['rowid'] . ' xi equals xt'] = [$row['xi'], $row['xt'], 'INTEGER', 'TEXT', true];
    $affinityComparisonCases['affinity2-200 row ' . $row['rowid'] . ' xi equals xb'] = [$row['xi'], $row['xb'], 'INTEGER', 'BLOB', true];
    $affinityComparisonCases['affinity2-200 row ' . $row['rowid'] . ' xi equals unary plus xt'] = [$row['xi'], $row['xt'], 'INTEGER', 'NONE', true];
    $affinityComparisonCases['affinity2-210 row ' . $row['rowid'] . ' xr equals xt'] = [$row['xr'], $row['xt'], 'REAL', 'TEXT', true];
    $affinityComparisonCases['affinity2-210 row ' . $row['rowid'] . ' xr equals xb'] = [$row['xr'], $row['xb'], 'REAL', 'BLOB', true];
    $affinityComparisonCases['affinity2-210 row ' . $row['rowid'] . ' xr equals unary plus xt'] = [$row['xr'], $row['xt'], 'REAL', 'NONE', true];
    $affinityComparisonCases['affinity2-220 row ' . $row['rowid'] . ' xn equals xt'] = [$row['xn'], $row['xt'], 'NUMERIC', 'TEXT', true];
    $affinityComparisonCases['affinity2-220 row ' . $row['rowid'] . ' xn equals xb'] = [$row['xn'], $row['xb'], 'NUMERIC', 'BLOB', true];
    $affinityComparisonCases['affinity2-220 row ' . $row['rowid'] . ' xn equals unary plus xt'] = [$row['xn'], $row['xt'], 'NUMERIC', 'NONE', true];
    $affinityComparisonCases['affinity2-300 row ' . $row['rowid'] . ' xt equals unary plus xi'] = [$row['xt'], $row['xi'], 'TEXT', 'NONE', $row['rowid'] !== 3];
    $affinityComparisonCases['affinity2-300 row ' . $row['rowid'] . ' xt equals xi'] = [$row['xt'], $row['xi'], 'TEXT', 'INTEGER', true];
    $affinityComparisonCases['affinity2-300 row ' . $row['rowid'] . ' xt equals xb'] = [$row['xt'], $row['xb'], 'NONE', 'NONE', $row['rowid'] !== 1];
}

foreach ($affinityComparisonCases as $name => [$left, $right, $leftAffinity, $rightAffinity, $expected]) {
    $tests['real upstream corpus affinity comparison ' . $name] = static function (TestRunner $t) use ($left, $right, $leftAffinity, $rightAffinity, $expected): void {
        $t->same($expected, SQLiteAffinityComparison::equals($left, $right, $leftAffinity, $rightAffinity));
    };
}

$unaryTicketCases = [
    'affinity2-500 negative blob quote comparison' => [0, '-1', true],
    'affinity2-502 plus minus plus blob quote comparison' => [0, '-1', true],
    'affinity2-504 negative text quote comparison' => [0, '-1', true],
    'affinity2-506 plus minus plus text quote comparison' => [0, '-1', true],
];

foreach ($unaryTicketCases as $name => [$left, $storedText, $expected]) {
    $tests['real upstream corpus affinity unary ticket ' . $name] = static function (TestRunner $t) use ($left, $storedText, $expected): void {
        $comparison = SQLiteAffinityComparison::compare($left, $storedText, 'NONE', 'TEXT', 'BINARY');
        $t->same($expected, $comparison !== null && $comparison >= 0);
    };
}

for ($i = 0; $i < 100; $i++) {
    $value = (string) ($i % 10);
    $integer = $i % 10;

    $tests['real upstream corpus affinity dynamic text numeric equality sample ' . $i] = static function (TestRunner $t) use ($integer, $value): void {
        $t->true(SQLiteAffinityComparison::equals($integer, str_pad($value, 2, '0', STR_PAD_LEFT), 'INTEGER', 'TEXT'));
    };
}

return $tests;
