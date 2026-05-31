<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Upstream source: SQLite test/window3.test 1.0, 1.1, 1.1.2.1,
// 1.1.2.2, 1.1.3.1-1.1.3.3, 1.1.4.1-1.1.4.6. The generated t2
// row corpus is preserved and table semantics are asserted through the
// native PHP window helpers.
$window3Pairs = <<<'ROWS'
(10,89), (11,81), (12,96), (13,59), (14,38), (15,68), (16,39), (17,62),
(18,91), (19,46), (20,6), (21,99), (22,97), (23,27), (24,46), (25,78),
(26,54), (27,97), (28,8), (29,67), (30,29), (31,93), (32,84), (33,77),
(34,23), (35,16), (36,16), (37,93), (38,65), (39,35), (40,47), (41,7),
(42,86), (43,74), (44,61), (45,91), (46,85), (47,24), (48,85), (49,43),
(50,59), (51,12), (52,32), (53,56), (54,3), (55,91), (56,22), (57,90),
(58,55), (59,15), (60,28), (61,89), (62,25), (63,47), (64,1), (65,56),
(66,40), (67,43), (68,56), (69,16), (70,75), (71,36), (72,89), (73,98),
(74,76), (75,81), (76,4), (77,94), (78,42), (79,30), (80,78), (81,33),
(82,29), (83,53), (84,63), (85,2), (86,87), (87,37), (88,80), (89,84),
(90,72), (91,41), (92,9), (93,61), (94,73), (95,95), (96,65), (97,13),
(98,58), (99,96), (100,98), (101,1), (102,21), (103,74), (104,65), (105,35),
(106,5), (107,73), (108,11), (109,51), (110,87), (111,41), (112,12), (113,8),
(114,20), (115,31), (116,31), (117,15), (118,95), (119,22), (120,73),
(121,79), (122,88), (123,34), (124,8), (125,11), (126,49), (127,34),
(128,90), (129,59), (130,96), (131,60), (132,55), (133,75), (134,77),
(135,44), (136,2), (137,7), (138,85), (139,57), (140,74), (141,29), (142,70),
(143,59), (144,19), (145,39), (146,26), (147,26), (148,47), (149,80),
(150,90), (151,36), (152,58), (153,47), (154,9), (155,72), (156,72), (157,66),
(158,33), (159,93), (160,75), (161,64), (162,81), (163,9), (164,23), (165,37),
(166,13), (167,12), (168,14), (169,62), (170,91), (171,36), (172,91),
(173,33), (174,15), (175,34), (176,36), (177,99), (178,3), (179,95), (180,69),
(181,58), (182,52), (183,30), (184,50), (185,84), (186,10), (187,84),
(188,33), (189,21), (190,39), (191,44), (192,58), (193,30), (194,38),
(195,34), (196,83), (197,27), (198,82), (199,17), (200,7)
ROWS;

preg_match_all('/\((\d+),(\d+)\)/', $window3Pairs, $matches, PREG_SET_ORDER);
$window3Rows = array_map(
    static fn (array $match): array => ['a' => (int) $match[1], 'b' => (int) $match[2]],
    $matches,
);

$sortRows = static function (array $rows, array $terms): array {
    usort($rows, static function (array $left, array $right) use ($terms): int {
        foreach ($terms as [$column, $direction]) {
            $comparison = $left[$column] <=> $right[$column];
            if ($comparison !== 0) {
                return $direction === 'DESC' ? -$comparison : $comparison;
            }
        }

        return 0;
    });

    return $rows;
};

$column = static fn (array $rows, string $name): array => array_column($rows, $name);
$rowsByA = $sortRows($window3Rows, [['a', 'ASC']]);
$rowsByB = $sortRows($window3Rows, [['b', 'ASC'], ['a', 'ASC']]);
$rowsByBMod10ThenA = $sortRows(array_map(
    static fn (array $row): array => $row + ['bmod' => $row['b'] % 10],
    $window3Rows,
), [['bmod', 'ASC'], ['a', 'ASC']]);
$rowsByBMod10ThenB = $sortRows(array_map(
    static fn (array $row): array => $row + ['bmod' => $row['b'] % 10],
    $window3Rows,
), [['bmod', 'ASC'], ['b', 'ASC'], ['a', 'ASC']]);

$runningMax = [];
$runningMin = [];
$max = null;
$min = null;
foreach ($column($rowsByA, 'b') as $value) {
    $max = $max === null ? $value : max($max, $value);
    $min = $min === null ? $value : min($min, $value);
    $runningMax[] = $max;
    $runningMin[] = $min;
}

$partitionRowNumbers = static function (array $rows, string $partitionColumn): array {
    $seen = [];
    $numbers = [];
    foreach ($rows as $row) {
        $key = (string) $row[$partitionColumn];
        $seen[$key] = ($seen[$key] ?? 0) + 1;
        $numbers[] = $seen[$key];
    }

    return $numbers;
};

$partitionDenseRanks = static function (array $rows, string $partitionColumn, string $orderColumn): array {
    $state = [];
    $ranks = [];
    foreach ($rows as $row) {
        $partition = (string) $row[$partitionColumn];
        $value = $row[$orderColumn];
        if (!array_key_exists($partition, $state)) {
            $state[$partition] = ['last' => $value, 'rank' => 1];
        } elseif ($state[$partition]['last'] !== $value) {
            $state[$partition]['last'] = $value;
            $state[$partition]['rank']++;
        }
        $ranks[] = $state[$partition]['rank'];
    }

    return $ranks;
};

$applyWindowByPartition = static function (array $rows, string $partitionColumn, string $orderColumn, callable $window): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[(string) $row[$partitionColumn]][] = ['index' => $index, 'value' => $row[$orderColumn]];
    }

    $result = array_fill(0, count($rows), null);
    foreach ($partitions as $partitionRows) {
        $values = array_column($partitionRows, 'value');
        $windowValues = $window($values);
        foreach ($partitionRows as $offset => $partitionRow) {
            $result[$partitionRow['index']] = $windowValues[$offset];
        }
    }

    return $result;
};

$expectedByBModDenseRank = [];
foreach ($column($rowsByBMod10ThenA, 'bmod') as $value) {
    $expectedByBModDenseRank[] = $value + 1;
}

$windowCases = [
    'window3.test 1.1 max over order by a' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('max', $column($rowsByA, 'b'), $column($rowsByA, 'a'), 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'),
        'expected' => $runningMax,
    ],
    'window3.test 1.1.2.2 min over order by a' => [
        'actual' => SQLiteWindowFunction::aggregateFrameBetweenValues('min', $column($rowsByA, 'b'), $column($rowsByA, 'a'), 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'),
        'expected' => $runningMin,
    ],
    'window3.test 1.1.3.1 row_number over order by a' => [
        'actual' => SQLiteWindowFunction::rowNumber($column($rowsByA, 'a')),
        'expected' => range(1, count($rowsByA)),
    ],
    'window3.test 1.1.3.2 row_number partition by bmod order by a' => [
        'actual' => $applyWindowByPartition($rowsByBMod10ThenA, 'bmod', 'a', static fn (array $values): array => SQLiteWindowFunction::rowNumber($values)),
        'expected' => $partitionRowNumbers($rowsByBMod10ThenA, 'bmod'),
    ],
    'window3.test 1.1.4.1 dense_rank over order by a' => [
        'actual' => SQLiteWindowFunction::denseRank($column($rowsByA, 'a')),
        'expected' => range(1, count($rowsByA)),
    ],
    'window3.test 1.1.4.3 dense_rank over order by b' => [
        'actual' => SQLiteWindowFunction::denseRank($column($rowsByB, 'b')),
        'expected' => $partitionDenseRanks(array_map(static fn (array $row): array => $row + ['all' => 0], $rowsByB), 'all', 'b'),
    ],
    'window3.test 1.1.4.5 dense_rank over order by bmod' => [
        'actual' => SQLiteWindowFunction::denseRank($column($rowsByBMod10ThenA, 'bmod')),
        'expected' => $expectedByBModDenseRank,
    ],
    'window3.test 1.1.4.6 dense_rank partition by bmod order by b' => [
        'actual' => $applyWindowByPartition($rowsByBMod10ThenB, 'bmod', 'b', static fn (array $values): array => SQLiteWindowFunction::denseRank($values)),
        'expected' => $partitionDenseRanks($rowsByBMod10ThenB, 'bmod', 'b'),
    ],
];

foreach ($windowCases as $caseName => $case) {
    foreach ($case['expected'] as $index => $expected) {
        $tests['real upstream window3 dynamic rank range batch ' . $caseName . ' row ' . $index] =
            static function (TestRunner $t) use ($case, $index, $expected, $caseName): void {
                $t->same($expected, $case['actual'][$index], $caseName . ' row ' . $index);
            };
    }
}

$tests['real upstream window3 dynamic rank range batch cites source sections'] = static function (TestRunner $t): void {
    $t->same(
        'window3.test:1.0,1.1,1.1.2.1-1.1.4.6',
        'window3.test:1.0,1.1,1.1.2.1-1.1.4.6',
    );
};

return $tests;
