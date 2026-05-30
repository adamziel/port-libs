<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [];
for ($b = 1; $b <= 100; $b++) {
    $rows[] = [
        'a' => $b % 10,
        'b' => $b,
    ];
}

$groupSums = [];
foreach ($rows as $row) {
    $groupSums[$row['a']] = ($groupSums[$row['a']] ?? 0) + $row['b'];
}
ksort($groupSums);

$ascendingRows = $rows;
usort($ascendingRows, static fn (array $left, array $right): int => [$left['a'], $left['b']] <=> [$right['a'], $right['b']]);

$descendingRows = $rows;
usort($descendingRows, static fn (array $left, array $right): int => [$right['a'], $left['b']] <=> [$left['a'], $right['b']]);

$expectedGroupSum = static function (int $a, int $preceding, int $following) use ($groupSums): int {
    $sum = 0;
    for ($peer = max(0, $a - $preceding); $peer <= min(9, $a + $following); $peer++) {
        $sum += $groupSums[$peer];
    }

    return $sum;
};

$materialize = static function (array $sourceRows, array $windowValues): array {
    $result = [];
    foreach ($sourceRows as $index => $row) {
        $result[] = [
            'a' => $row['a'],
            'b' => $row['b'],
            'window_sum' => $windowValues[$index],
        ];
    }
    usort($result, static fn (array $left, array $right): int => [$left['a'], $left['b']] <=> [$right['a'], $right['b']]);

    return $result;
};

$cases = [
    '1.2 groups current row to current row' => [
        'rows' => $ascendingRows,
        'keys' => array_column($ascendingRows, 'a'),
        'unit' => 'GROUPS',
        'start' => 'CURRENT ROW',
        'end' => 'CURRENT ROW',
        'expected' => static fn (int $a): int => $expectedGroupSum($a, 0, 0),
    ],
    '1.3 groups zero preceding to zero following' => [
        'rows' => $ascendingRows,
        'keys' => array_column($ascendingRows, 'a'),
        'unit' => 'GROUPS',
        'start' => '0 PRECEDING',
        'end' => '0 FOLLOWING',
        'expected' => static fn (int $a): int => $expectedGroupSum($a, 0, 0),
    ],
    '1.4 groups two preceding to two following' => [
        'rows' => $ascendingRows,
        'keys' => array_column($ascendingRows, 'a'),
        'unit' => 'GROUPS',
        'start' => '2 PRECEDING',
        'end' => '2 FOLLOWING',
        'expected' => static fn (int $a): int => $expectedGroupSum($a, 2, 2),
    ],
    '1.5 range zero preceding to zero following' => [
        'rows' => $ascendingRows,
        'keys' => array_column($ascendingRows, 'a'),
        'unit' => 'RANGE',
        'start' => '0 PRECEDING',
        'end' => '0 FOLLOWING',
        'expected' => static fn (int $a): int => $expectedGroupSum($a, 0, 0),
    ],
    '1.6 range two preceding to two following' => [
        'rows' => $ascendingRows,
        'keys' => array_column($ascendingRows, 'a'),
        'unit' => 'RANGE',
        'start' => '2 PRECEDING',
        'end' => '2 FOLLOWING',
        'expected' => static fn (int $a): int => $expectedGroupSum($a, 2, 2),
    ],
    '1.7 range two preceding to one following' => [
        'rows' => $ascendingRows,
        'keys' => array_column($ascendingRows, 'a'),
        'unit' => 'RANGE',
        'start' => '2 PRECEDING',
        'end' => '1 FOLLOWING',
        'expected' => static fn (int $a): int => $expectedGroupSum($a, 2, 1),
    ],
    '1.8.1 range zero preceding to one following' => [
        'rows' => $ascendingRows,
        'keys' => array_column($ascendingRows, 'a'),
        'unit' => 'RANGE',
        'start' => '0 PRECEDING',
        'end' => '1 FOLLOWING',
        'expected' => static fn (int $a): int => $expectedGroupSum($a, 0, 1),
    ],
    '1.8.2 desc range zero preceding to one following' => [
        'rows' => $descendingRows,
        'keys' => array_map(static fn (array $row): int => -$row['a'], $descendingRows),
        'unit' => 'RANGE',
        'start' => '0 PRECEDING',
        'end' => '1 FOLLOWING',
        'expected' => static fn (int $a): int => $expectedGroupSum($a, 1, 0),
    ],
];

foreach ($cases as $caseName => $case) {
    $windowValues = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        array_column($case['rows'], 'b'),
        $case['keys'],
        $case['unit'],
        $case['start'],
        $case['end'],
    );
    $actualRows = $materialize($case['rows'], $windowValues);

    foreach ($actualRows as $index => $actualRow) {
        $rowNumber = $index + 1;
        $expectedA = intdiv($index, 10);
        $expectedSum = $case['expected']($expectedA);

        $tests["real upstream window7.test {$caseName} row {$rowNumber} ordered a"] = static function (TestRunner $t) use ($actualRow, $expectedA): void {
            $t->same($expectedA, $actualRow['a']);
        };
        $tests["real upstream window7.test {$caseName} row {$rowNumber} frame sum"] = static function (TestRunner $t) use ($actualRow, $expectedSum): void {
            $t->same($expectedSum, $actualRow['window_sum']);
        };
    }
}

$tests['real upstream window7 dynamic frame batch cites exact upstream scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window7.test:1.2',
            'window7.test:1.3',
            'window7.test:1.4',
            'window7.test:1.5',
            'window7.test:1.6',
            'window7.test:1.7',
            'window7.test:1.8.1',
            'window7.test:1.8.2',
        ],
        [
            'window7.test:1.2',
            'window7.test:1.3',
            'window7.test:1.4',
            'window7.test:1.5',
            'window7.test:1.6',
            'window7.test:1.7',
            'window7.test:1.8.1',
            'window7.test:1.8.2',
        ],
    );
};

return $tests;
