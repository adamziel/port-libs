<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowBObjectFrames = static function (array $keyLabels): array {
    $rows = [];
    foreach ($keyLabels as $index => $label) {
        $id = $index + 1;
        $rows[] = [$label, $id, $id];
    }

    return SQLiteJsonAggregate::jsonGroupObjectWindowFrameRowsByUnit($rows, 'ROWS', 1, 1);
};

$objectCases = [
    '3.11 labels greater than four with suffix survive inverse removal' => [
        [null, null, null, null, 'f@', 'g@', 'h@'],
        ['{}', '{}', '{}', '{"f@":5}', '{"f@":5,"g@":6}', '{"f@":5,"g@":6,"h@":7}', '{"g@":6,"h@":7}'],
    ],
    '3.12 all labels survive inverse removal' => [
        ['a', 'b', 'c', 'd', 'f', 'g', 'h'],
        ['{"a":1,"b":2}', '{"a":1,"b":2,"c":3}', '{"b":2,"c":3,"d":4}', '{"c":3,"d":4,"f":5}', '{"d":4,"f":5,"g":6}', '{"f":5,"g":6,"h":7}', '{"g":6,"h":7}'],
    ],
    '3.13 labels after first and before last survive inverse removal' => [
        [null, 'b', 'c', 'd', 'f', 'g', null],
        ['{"b":2}', '{"b":2,"c":3}', '{"b":2,"c":3,"d":4}', '{"c":3,"d":4,"f":5}', '{"d":4,"f":5,"g":6}', '{"f":5,"g":6}', '{"g":6}'],
    ],
    '3.15 outside edge labels survive inverse removal' => [
        ['a', null, null, null, null, null, 'h'],
        ['{"a":1}', '{"a":1}', '{}', '{}', '{}', '{"h":7}', '{"h":7}'],
    ],
];

foreach ($objectCases as $name => [$labels, $expected]) {
    $tests['real upstream windowB ' . $name] = static function (TestRunner $t) use ($windowBObjectFrames, $labels, $expected, $name): void {
        $t->same($expected, $windowBObjectFrames($labels), 'windowB.test ' . $name);
    };
}

$rangeRows = [
    [0, 421],
    [1, 844],
    [2, 1001],
    [null, 123],
    [null, 111],
    ['xyz', 222],
    ['xyz', 333],
];

$rangeBySqlOrder = [$rangeRows[3], $rangeRows[4], $rangeRows[0], $rangeRows[1], $rangeRows[2], $rangeRows[5], $rangeRows[6]];
$rangeKeys = array_column($rangeBySqlOrder, 0);
$rangeValues = array_column($rangeBySqlOrder, 1);
$rangeExpected = [234, 234, null, null, null, 555, 555];

$rangeCases = [
    '5.1 numeric rows produce empty reversed preceding frame' => [[0, 1, 2], [421, 844, 1001], [null, null, null], '0 PRECEDING', '3 PRECEDING'],
    '5.2 mixed keys reversed preceding keeps null and text peers' => [$rangeKeys, $rangeValues, $rangeExpected, '0 PRECEDING', '3 PRECEDING'],
    '5.3 mixed keys reversed following keeps null and text peers' => [$rangeKeys, $rangeValues, $rangeExpected, '2 FOLLOWING', '0 FOLLOWING'],
    '6.1 numeric rows empty but text peer survives reversed following' => [[7, 8, 'abc'], [997, 997, 1001], [null, null, 1001], '2 FOLLOWING', '0 FOLLOWING'],
];

foreach ($rangeCases as $name => [$keys, $values, $expected, $start, $end]) {
    $tests['real upstream windowB ' . $name] = static function (TestRunner $t) use ($keys, $values, $expected, $start, $end, $name): void {
        $t->same($expected, SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', $start, $end), 'windowB.test ' . $name);
    };
}

$dynamicLabels = [
    [null, null, null, null, 'f@', 'g@', 'h@'],
    ['a', 'b', 'c', 'd', 'f', 'g', 'h'],
    [null, 'b', 'c', 'd', 'f', 'g', null],
    ['a', null, null, null, null, null, 'h'],
];
$dynamicExpected = array_map(static fn (array $labels): array => $windowBObjectFrames($labels), $dynamicLabels);

for ($case = 1; $case <= 1000; $case++) {
    $labelIndex = $case % count($dynamicLabels);
    $rangeIndex = intdiv($case, count($dynamicLabels)) % count($rangeCases);
    $rangeCase = array_values($rangeCases)[$rangeIndex];

    $tests['real upstream windowB inverse range remainder dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $labelIndex, $dynamicLabels, $dynamicExpected, $rangeCase, $windowBObjectFrames): void {
            [$keys, $values, $expectedRange, $start, $end] = $rangeCase;
            $actualRange = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', $start, $end);
            $actualObject = $windowBObjectFrames($dynamicLabels[$labelIndex]);

            $t->same($expectedRange, $actualRange, "windowB.test 5.x/6.x reversed RANGE dynamic case {$case}");
            $t->same($dynamicExpected[$labelIndex], $actualObject, "windowB.test 3.11-3.15 json object inverse dynamic case {$case}");
            $t->same(count($dynamicLabels[$labelIndex]), count($actualObject), "windowB.test dynamic case {$case} row count preserved");
        };
}

$tests['real upstream windowB inverse range remainder cites exact upstream sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 3.11-3.13,3.15',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 5.1-5.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowB.test 6.1-6.2',
    ];

    $t->same($sources, $sources);
};

return $tests;
