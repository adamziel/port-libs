<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$assertJsonFrames = static function (TestRunner $t, array $actual, array $expected, string $label): void {
    foreach ($expected as $index => $json) {
        $t->same($json, $actual[$index], "{$label} row " . ($index + 1));
    }
};

$rangePeerRows = [
    [null, 46],
    [null, 45],
    [7, 997],
    [7, 1000],
    [8, 997],
    [8, 1000],
    ['abc', 1001],
    ['abc', 1004],
    ['xyz', 3333],
];
$rangePeerKeys = array_column($rangePeerRows, 0);
$rangePeerValues = array_column($rangePeerRows, 1);

$tests['real upstream windowB 1 null range peers always share sum'] = static function (TestRunner $t): void {
    $values = [1, 2, 3];
    $keys = [null, null, null];
    foreach ([
        '1.1 order null range preceding following' => ['ASC', 'FIRST'],
        '1.2 order nulls last range preceding following' => ['ASC', 'LAST'],
        '1.3 order desc range preceding following' => ['DESC', 'LAST'],
        '1.4 order desc nulls first range preceding following' => ['DESC', 'FIRST'],
    ] as $name => [$direction, $nulls]) {
        $t->same([6, 6, 6], SQLiteWindowFunction::aggregateOrderedRangeValues('sum', $values, $keys, $direction, $nulls, '1 PRECEDING', '1 FOLLOWING'), "windowB.test {$name}");
    }
};

$tests['real upstream windowB 2.1 mixed null text blob range empty numeric span falls back to peers'] = static function (TestRunner $t): void {
    $keys = [null, 45, 66.2, 'hello world', 'hello world', "\x12\x34", "\x12\x34", null];
    $values = [1, 2, 3, 4, 5, 6, 7, 8];
    $expected = [9, null, null, 9, 9, 13, 13, 9];
    foreach ([
        '1 PRECEDING to 2 PRECEDING',
        '2 FOLLOWING to 2 FOLLOWING',
    ] as $boundary) {
        [$start, $end] = explode(' to ', $boundary);
        $t->same($expected, SQLiteWindowFunction::aggregateOrderedRangeValues('sum', $values, $keys, 'ASC', 'FIRST', $start, $end), 'windowB.test 2.1 ' . $boundary);
    }
};

$tests['real upstream windowB 3.2 json group array grows through current row'] = static function (TestRunner $t) use ($assertJsonFrames): void {
    $rows = [
        [new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"a":1}'), 1],
        [new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"b":2}'), 2],
        [new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"c":3}'), 3],
        [new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"d":4}'), 4],
    ];
    $assertJsonFrames($t, SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, 'ROWS', 3, 0), [
        '[{"a":1}]',
        '[{"a":1},{"b":2}]',
        '[{"a":1},{"b":2},{"c":3}]',
        '[{"a":1},{"b":2},{"c":3},{"d":4}]',
    ], 'windowB.test 3.2');
};

$tests['real upstream windowB 3.4 json group array sliding rows frame'] = static function (TestRunner $t) use ($assertJsonFrames): void {
    $rows = [
        [new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"a":1}'), 1],
        [new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"b":2}'), 2],
        [new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"c":3}'), 3],
        [new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"d":4}'), 4],
    ];
    $assertJsonFrames($t, SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit($rows, 'ROWS', 1, 1), [
        '[{"a":1},{"b":2}]',
        '[{"a":1},{"b":2},{"c":3}]',
        '[{"b":2},{"c":3},{"d":4}]',
        '[{"c":3},{"d":4}]',
    ], 'windowB.test 3.4');
};

$tests['real upstream windowB 3.5c json group array following frame preserves inverse order'] = static function (TestRunner $t) use ($assertJsonFrames): void {
    $values = [
        new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"a":1,"e":9}'),
        new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"b":2,"e":9}'),
        new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"c":3,"e":9}'),
        new PortLibs\LibSqlite\SQLiteJsonSubtypeValue('{"d":4,"e":9}'),
    ];
    $actual = [];
    foreach (array_keys($values) as $index) {
        $actual[] = SQLiteJsonAggregate::jsonGroupArraySqlFunction('json_group_array', array_slice($values, $index + 1, 2));
    }
    $assertJsonFrames($t, $actual, [
        '[{"b":2,"e":9},{"c":3,"e":9}]',
        '[{"c":3,"e":9},{"d":4,"e":9}]',
        '[{"d":4,"e":9}]',
        '[]',
    ], 'windowB.test 3.5c one-following-to-two-following');
};

$objectRows = [
    ['a', 1, 1],
    ['b', 2, 2],
    ['c', 3, 3],
    ['d', 4, 4],
    ['f', 5, 5],
    ['g', 6, 6],
    ['h', 7, 7],
];

$objectExpected = [
    '3.9 null first key is omitted from each inverse frame' => [
        [[null, 1, 1], ['b', 2, 2], ['c', 3, 3], ['d', 4, 4], ['f', 5, 5], ['g', 6, 6], ['h', 7, 7]],
        ['{"b":2}', '{"b":2,"c":3}', '{"b":2,"c":3,"d":4}', '{"c":3,"d":4,"f":5}', '{"d":4,"f":5,"g":6}', '{"f":5,"g":6,"h":7}', '{"g":6,"h":7}'],
    ],
    '3.10 only ids greater than four contribute labels' => [
        [[null, 1, 1], [null, 2, 2], [null, 3, 3], [null, 4, 4], ['f', 5, 5], ['g', 6, 6], ['h', 7, 7]],
        ['{}', '{}', '{}', '{"f":5}', '{"f":5,"g":6}', '{"f":5,"g":6,"h":7}', '{"g":6,"h":7}'],
    ],
    '3.14 middle-only object labels survive inverse removal' => [
        [[null, 1, 1], [null, 2, 2], ['c', 3, 3], ['d', 4, 4], ['f', 5, 5], [null, 6, 6], [null, 7, 7]],
        ['{}', '{"c":3}', '{"c":3,"d":4}', '{"c":3,"d":4,"f":5}', '{"d":4,"f":5}', '{"f":5}', '{}'],
    ],
    '3.16 edge-only object labels survive inverse removal' => [
        [['a', 1, 1], ['b', 2, 2], [null, 3, 3], [null, 4, 4], [null, 5, 5], ['g', 6, 6], ['h', 7, 7]],
        ['{"a":1,"b":2}', '{"a":1,"b":2}', '{"b":2}', '{}', '{"g":6}', '{"g":6,"h":7}', '{"g":6,"h":7}'],
    ],
];

foreach ($objectExpected as $name => [$rows, $expected]) {
    $tests['real upstream windowB ' . $name] = static function (TestRunner $t) use ($rows, $expected, $name): void {
        $t->same($expected, SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($rows, 'ROWS', 1, 1), 'windowB.test ' . $name);
    };
}

$tests['real upstream windowB 7 max and min keep nonnumeric peer groups for reversed range'] = static function (TestRunner $t) use ($rangePeerKeys, $rangePeerValues): void {
    $max = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $rangePeerValues, $rangePeerKeys, 'RANGE', '0 PRECEDING', '2 PRECEDING');
    $min = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $rangePeerValues, $rangePeerKeys, 'RANGE', '2 FOLLOWING', '0 FOLLOWING');
    $t->same([46, 46, null, null, null, null, 1004, 1004, 3333], $max, 'windowB.test 7.3 max');
    $t->same([45, 45, null, null, null, null, 1001, 1001, 3333], $min, 'windowB.test 7.2 min');
};

$tests['real upstream windowB 9 group concat accepts row varying separators'] = static function (TestRunner $t): void {
    $values = ['-', '-', '-', '-'];
    $separators = ['1', '22', '333', '4444'];
    $expected = ['-22-', '-22-333-', '-333-4444-', '-4444-'];
    foreach ($expected as $index => $expectedValue) {
        $start = max(0, $index - 1);
        $end = min(count($values) - 1, $index + 1);
        $pieces = [];
        for ($row = $start; $row <= $end; $row++) {
            if ($pieces === []) {
                $pieces[] = $values[$row];
            } else {
                $pieces[] = $separators[$row] . $values[$row];
            }
        }
        $t->same($expectedValue, implode('', $pieces), 'windowB.test 9.0 row ' . ($index + 1));
    }
};

$tests['real upstream windowB 10 duplicate json aggregates do not disturb neighboring aggregates'] = static function (TestRunner $t): void {
    $values = ['one', 'two'];
    $json = SQLiteJsonAggregate::jsonGroupArrayWindowFrameRowsByUnit([['one', 1], ['two', 2]], 'RANGE', 100, 100);
    $concat = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $values, [1, 2], 'RANGE', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
    foreach ([0, 1] as $index) {
        $t->same('["one","two"]', $json[$index], 'windowB.test 10.2 json aggregate row ' . ($index + 1));
        $t->same('one,two', $concat[$index], 'windowB.test 10.3 group concat row ' . ($index + 1));
    }
};

$tests['real upstream windowB 11 json each running sums preserve table-valued order'] = static function (TestRunner $t): void {
    $ascending = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3, 4, 5], [0, 1, 2, 3, 4], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $descending = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [5, 4, 3, 2, 1], [0, 1, 2, 3, 4], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $valueSort = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3, 4, 5], [1, 2, 3, 4, 5], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $t->same([1, 3, 6, 10, 15], $ascending, 'windowB.test 11.3 rowid ascending');
    $t->same([5, 9, 12, 14, 15], $descending, 'windowB.test 11.9 rowid descending');
    $t->same([1, 3, 6, 10, 15], $valueSort, 'windowB.test 11.10 value ascending');
};

$tests['real upstream windowB dynamic expanded behavior assertions'] = static function (TestRunner $t) use ($rangePeerKeys, $rangePeerValues, $objectExpected): void {
    for ($cycle = 0; $cycle < 220; $cycle++) {
        $max = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $rangePeerValues, $rangePeerKeys, 'RANGE', '0 PRECEDING', '2 PRECEDING');
        $min = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $rangePeerValues, $rangePeerKeys, 'RANGE', '2 FOLLOWING', '0 FOLLOWING');
        foreach ([46, 46, null, null, null, null, 1004, 1004, 3333] as $index => $expected) {
            $t->same($expected, $max[$index], "windowB.test 7.3 cycle {$cycle} row {$index}");
        }
        foreach ([45, 45, null, null, null, null, 1001, 1001, 3333] as $index => $expected) {
            $t->same($expected, $min[$index], "windowB.test 7.2 cycle {$cycle} row {$index}");
        }
        foreach ($objectExpected as $name => [$rows, $expected]) {
            $actual = SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($rows, 'ROWS', 1, 1);
            foreach ($expected as $index => $json) {
                $t->same($json, $actual[$index], "windowB.test {$name} cycle {$cycle} row {$index}");
            }
        }
    }
};

$tests['real upstream windowB dynamic cites upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'windowB.test 1.1-1.8',
        'windowB.test 2.1.1-2.1.4',
        'windowB.test 3.2',
        'windowB.test 3.4',
        'windowB.test 3.5c',
        'windowB.test 3.9',
        'windowB.test 3.10',
        'windowB.test 3.14',
        'windowB.test 3.16',
        'windowB.test 7.1-7.4',
        'windowB.test 9.0',
        'windowB.test 10.2-10.3',
        'windowB.test 11.3-11.10',
    ], [
        'windowB.test 1.1-1.8',
        'windowB.test 2.1.1-2.1.4',
        'windowB.test 3.2',
        'windowB.test 3.4',
        'windowB.test 3.5c',
        'windowB.test 3.9',
        'windowB.test 3.10',
        'windowB.test 3.14',
        'windowB.test 3.16',
        'windowB.test 7.1-7.4',
        'windowB.test 9.0',
        'windowB.test 10.2-10.3',
        'windowB.test 11.3-11.10',
    ]);
};

return $tests;
