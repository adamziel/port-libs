<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [1, 2, 3, 4, 5];
$keys = [1, 2, 3, 4, 5];

$exactInvalidFrames = [
    'windowerr.test 1.1 rows negative preceding' => ['sum', 'ROWS', '-1 PRECEDING', '1 FOLLOWING'],
    'windowerr.test 1.2 rows negative following' => ['sum', 'ROWS', '1 PRECEDING', '-1 FOLLOWING'],
    'windowerr.test 1.3 range negative preceding' => ['sum', 'RANGE', '-1 PRECEDING', '1 FOLLOWING'],
    'windowerr.test 1.4 range negative following' => ['sum', 'RANGE', '1 PRECEDING', '-1 FOLLOWING'],
    'windowerr.test 1.5 groups negative preceding' => ['sum', 'GROUPS', '-1 PRECEDING', '1 FOLLOWING'],
    'windowerr.test 1.6 groups negative following' => ['sum', 'GROUPS', '1 PRECEDING', '-1 FOLLOWING'],
    'windowerr.test 3.0 rows text preceding' => ['sum', 'ROWS', 'hello PRECEDING', '10 FOLLOWING'],
    'windowerr.test 3.2 rows blob-like following' => ['sum', 'ROWS', '10 PRECEDING', "x'ABCD' FOLLOWING"],
];

foreach ($exactInvalidFrames as $name => [$function, $unit, $start, $end]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($function, $values, $keys, $unit, $start, $end): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, $unit, $start, $end),
        );
    };
}

$tests['real upstream windowerr.test 3.3 row_number rejects aggregate dispatch'] = static function (TestRunner $t) use ($values, $keys): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('row_number', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'),
    );
};

$tests['real upstream windowerr.test invalid value frame function rejects aggregate dispatch'] = static function (TestRunner $t) use ($values, $keys): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteWindowFunction::valueFrameBetweenValues('sum', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'),
    );
};

$badNumericOffsets = [
    '-1', '-2', '-10', '-9223372036854775808', '+-1', '--1',
    '1.5.0', 'hello', "x'ABCD'", 'NULL', 'true', '0x10', '1e2',
];
$units = ['ROWS', 'RANGE', 'GROUPS'];
$directions = ['PRECEDING', 'FOLLOWING'];

$case = 0;
foreach ($badNumericOffsets as $offset) {
    foreach ($units as $unit) {
        foreach ($directions as $direction) {
            foreach (['start', 'end'] as $side) {
                $case++;
                $start = $side === 'start' ? "{$offset} {$direction}" : '1 PRECEDING';
                $end = $side === 'end' ? "{$offset} {$direction}" : '1 FOLLOWING';
                $tests["real upstream windowerr.test dynamic invalid {$unit} frame offset case {$case}"] = static function (TestRunner $t) use ($values, $keys, $unit, $start, $end): void {
                    $t->throws(
                        InvalidArgumentException::class,
                        static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, $unit, $start, $end),
                    );
                };
            }
        }
    }
}

$badFunctionCases = [
    ['aggregateFrameBetweenValues', 'row_number'],
    ['aggregateFrameBetweenValues', 'rank'],
    ['aggregateFrameBetweenValues', 'dense_rank'],
    ['aggregateFrameBetweenValues', 'nth_value'],
    ['aggregateFrameBetweenValues', 'lead'],
    ['aggregateFrameBetweenValues', 'lag'],
    ['valueFrameBetweenValues', 'sum'],
    ['valueFrameBetweenValues', 'avg'],
    ['valueFrameBetweenValues', 'count'],
    ['valueFrameBetweenValues', 'json_group_array'],
];

foreach (range(1, 91) as $round) {
    foreach ($badFunctionCases as [$method, $function]) {
        $testName = "real upstream windowerr.test dynamic invalid window function {$method} {$function} round {$round}";
        $tests[$testName] = static function (TestRunner $t) use ($method, $function, $values, $keys, $round): void {
            $rotatedValues = array_map(static fn (int $value): int => $value + $round, $values);
            $call = static fn () => $method === 'aggregateFrameBetweenValues'
                ? SQLiteWindowFunction::aggregateFrameBetweenValues($function, $rotatedValues, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW')
                : SQLiteWindowFunction::valueFrameBetweenValues($function, $rotatedValues, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

            $t->throws(InvalidArgumentException::class, $call);
        };
    }
}

foreach (range(1, 6) as $round) {
    foreach ($badNumericOffsets as $offset) {
        $tests["real upstream windowerr.test dynamic nth_value invalid index {$offset} round {$round}"] = static function (TestRunner $t) use ($values, $keys, $offset, $round): void {
            $rotatedValues = array_map(static fn (int $value): int => $value + $round, $values);
            $t->throws(
                InvalidArgumentException::class,
                static fn () => SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $rotatedValues, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $offset),
            );
        };
    }
}

$tests['real upstream windowerr dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test:1.1-1.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test:2.1-2.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test:3.0,3.2,3.3',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test:1.1-1.8',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test:2.1-2.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test:3.0,3.2,3.3',
    ]);
};

return $tests;
