<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window2Rows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
];

$byColumn = static fn (array $rows, string $column): array => array_map(static fn (array $row): mixed => $row[$column], $rows);

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

$partitionRows = static function (array $rows, callable $partition, array $orderTerms) use ($sortRows): array {
    $partitions = [];
    foreach ($rows as $row) {
        $partitionKey = (string) $partition($row);
        $row['__partition'] = $partitionKey;
        $partitions[$partitionKey][] = $row;
    }
    ksort($partitions, SORT_STRING);

    $ordered = [];
    foreach ($partitions as $partitionRows) {
        array_push($ordered, ...$sortRows($partitionRows, $orderTerms));
    }

    return $ordered;
};

$sumPairs = static function (array $rows, array $frameValues, string $idColumn = 'a'): array {
    $pairs = [];
    foreach ($rows as $index => $row) {
        $pairs[] = $row[$idColumn];
        $pairs[] = $frameValues[$index];
    }

    return $pairs;
};

$windowFrameValues = static function (array $case) use ($byColumn): array {
    $rows = $case['rows'];
    $orderColumn = $case['order'] ?? 'd';
    $partitions = [];
    foreach ($rows as $row) {
        $partitions[$row['__partition'] ?? '__all'][] = $row;
    }

    $result = [];
    foreach ($partitions as $partitionRows) {
        array_push(
            $result,
            ...SQLiteWindowFunction::aggregateFrameBetweenValues(
                'sum',
                $byColumn($partitionRows, 'd'),
                $byColumn($partitionRows, $orderColumn),
                $case['unit'],
                $case['start'],
                $case['end'],
            ),
        );
    }

    return $result;
};

$assertSequence = static function (TestRunner $t, array $expected, array $actual, string $label): void {
    $t->same(count($expected), count($actual), $label . ' cell count');
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index], $label . ' cell ' . $index);
    }
};

$window2Cases = [
    'window2 1.1 partition text order running sum' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['c', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => 'CURRENT ROW',
        'expected' => ['four', 4, 'six', 10, 'two', 12, 'five', 5, 'one', 6, 'three', 9],
        'id' => 'c',
        'order' => 'c',
    ],
    'window2 1.2 unpartitioned total sum' => [
        'rows' => $window2Rows,
        'unit' => 'ROWS',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [1, 21, 2, 21, 3, 21, 4, 21, 5, 21, 6, 21],
    ],
    'window2 1.3 partition total sum' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['a', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [2, 12, 4, 12, 6, 12, 1, 9, 3, 9, 5, 9],
    ],
    'window2 2.1 rows 1000 preceding 1 following' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1000 PRECEDING',
        'end' => '1 FOLLOWING',
        'expected' => [1, 3, 2, 6, 3, 10, 4, 15, 5, 21, 6, 21],
    ],
    'window2 2.2 rows full wide frame' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1000 PRECEDING',
        'end' => '1000 FOLLOWING',
        'expected' => [1, 21, 2, 21, 3, 21, 4, 21, 5, 21, 6, 21],
    ],
    'window2 2.3 rows previous through tail' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 PRECEDING',
        'end' => '1000 FOLLOWING',
        'expected' => [1, 21, 2, 21, 3, 20, 4, 18, 5, 15, 6, 11],
    ],
    'window2 2.4 rows one preceding one following' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 PRECEDING',
        'end' => '1 FOLLOWING',
        'expected' => [1, 3, 2, 6, 3, 9, 4, 12, 5, 15, 6, 11],
    ],
    'window2 2.5 rows one preceding current' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 PRECEDING',
        'end' => '0 FOLLOWING',
        'expected' => [1, 1, 2, 3, 3, 5, 4, 7, 5, 9, 6, 11],
    ],
    'window2 2.6 partition rows one preceding one following' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 PRECEDING',
        'end' => '1 FOLLOWING',
        'expected' => [2, 6, 4, 12, 6, 10, 1, 4, 3, 9, 5, 8],
    ],
    'window2 2.7 partition current row' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '0 PRECEDING',
        'end' => '0 FOLLOWING',
        'expected' => [2, 2, 4, 4, 6, 6, 1, 1, 3, 3, 5, 5],
    ],
    'window2 2.8 rows current through two following' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'CURRENT ROW',
        'end' => '2 FOLLOWING',
        'expected' => [1, 6, 2, 9, 3, 12, 4, 15, 5, 11, 6, 6],
    ],
    'window2 2.9 rows unbounded preceding through two following' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => '2 FOLLOWING',
        'expected' => [1, 6, 2, 10, 3, 15, 4, 21, 5, 21, 6, 21],
    ],
    'window2 2.11 rows two preceding current' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '2 PRECEDING',
        'end' => 'CURRENT ROW',
        'expected' => [1, 1, 2, 3, 3, 6, 4, 9, 5, 12, 6, 15],
    ],
    'window2 2.13 rows two preceding through tail' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '2 PRECEDING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [1, 21, 2, 21, 3, 21, 4, 20, 5, 18, 6, 15],
    ],
    'window2 2.14 rows three preceding through one preceding' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '3 PRECEDING',
        'end' => '1 PRECEDING',
        'expected' => [1, null, 2, 1, 3, 3, 4, 6, 5, 9, 6, 12],
    ],
    'window2 2.15 partition one preceding through current' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 PRECEDING',
        'end' => '0 PRECEDING',
        'expected' => [2, 2, 4, 6, 6, 10, 1, 1, 3, 4, 5, 8],
    ],
    'window2 2.16 partition exactly one preceding' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 PRECEDING',
        'end' => '1 PRECEDING',
        'expected' => [2, null, 4, 2, 6, 4, 1, null, 3, 1, 5, 3],
    ],
    'window2 2.17 partition empty reversed preceding frame' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 PRECEDING',
        'end' => '2 PRECEDING',
        'expected' => [2, null, 4, null, 6, null, 1, null, 3, null, 5, null],
    ],
    'window2 2.18 partition unbounded preceding through two preceding' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => '2 PRECEDING',
        'expected' => [2, null, 4, null, 6, 2, 1, null, 3, null, 5, 1],
    ],
    'window2 2.19 partition one following through three following' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 FOLLOWING',
        'end' => '3 FOLLOWING',
        'expected' => [2, 10, 4, 6, 6, null, 1, 8, 3, 5, 5, null],
    ],
    'window2 2.20 rows one following through two following' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 FOLLOWING',
        'end' => '2 FOLLOWING',
        'expected' => [1, 5, 2, 7, 3, 9, 4, 11, 5, 6, 6, null],
    ],
    'window2 2.21 rows one following through tail' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 FOLLOWING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [1, 20, 2, 18, 3, 15, 4, 11, 5, 6, 6, null],
    ],
    'window2 2.22 partition one following through tail' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => '1 FOLLOWING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [2, 10, 4, 6, 6, null, 1, 8, 3, 5, 5, null],
    ],
    'window2 2.23 rows current through tail' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'CURRENT ROW',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [1, 21, 2, 20, 3, 18, 4, 15, 5, 11, 6, 6],
    ],
    'window2 2.24 partition expression current through tail' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): int => $row['a'] % 2, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'CURRENT ROW',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [2, 12, 4, 10, 6, 6, 1, 9, 3, 8, 5, 5],
    ],
    'window2 2.25 rows full unbounded' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [1, 21, 2, 21, 3, 21, 4, 21, 5, 21, 6, 21],
    ],
    'window2 2.26 partition full unbounded' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [2, 12, 4, 12, 6, 12, 1, 9, 3, 9, 5, 9],
    ],
    'window2 2.27 rows current current' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'CURRENT ROW',
        'end' => 'CURRENT ROW',
        'expected' => [1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6],
    ],
    'window2 2.28 partition current current' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'CURRENT ROW',
        'end' => 'CURRENT ROW',
        'expected' => [2, 2, 4, 4, 6, 6, 1, 1, 3, 3, 5, 5],
    ],
    'window2 2.29 range numeric current through tail' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'RANGE',
        'start' => 'CURRENT ROW',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [1, 21, 2, 20, 3, 18, 4, 15, 5, 11, 6, 6],
    ],
    'window2 3.1 partition range current through tail' => [
        'rows' => $partitionRows($window2Rows, static fn (array $row): string => $row['b'], [['d', 'ASC']]),
        'unit' => 'RANGE',
        'start' => 'CURRENT ROW',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [2, 12, 4, 10, 6, 6, 1, 9, 3, 8, 5, 5],
    ],
    'window2 3.3 rows explicit full frame' => [
        'rows' => $sortRows($window2Rows, [['d', 'ASC']]),
        'unit' => 'ROWS',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expected' => [1, 21, 2, 21, 3, 21, 4, 21, 5, 21, 6, 21],
    ],
];

foreach ($window2Cases as $name => $case) {
    $tests['real upstream window functions dynamic ' . $name] = static function (TestRunner $t) use ($case, $sumPairs, $assertSequence, $windowFrameValues): void {
        $rows = $case['rows'];
        $actual = $sumPairs(
            $rows,
            $windowFrameValues($case),
            $case['id'] ?? 'a',
        );

        $assertSequence($t, $case['expected'], $actual, $case['unit'] . ' ' . $case['start'] . ' ' . $case['end']);
    };
}

$window3Rows = [
    ['a' => 10, 'b' => 89], ['a' => 11, 'b' => 81], ['a' => 12, 'b' => 96], ['a' => 13, 'b' => 59],
    ['a' => 14, 'b' => 38], ['a' => 15, 'b' => 68], ['a' => 16, 'b' => 39], ['a' => 17, 'b' => 62],
    ['a' => 18, 'b' => 91], ['a' => 19, 'b' => 46], ['a' => 20, 'b' => 6], ['a' => 21, 'b' => 99],
    ['a' => 22, 'b' => 97], ['a' => 23, 'b' => 27], ['a' => 24, 'b' => 46], ['a' => 25, 'b' => 78],
    ['a' => 26, 'b' => 54], ['a' => 27, 'b' => 97], ['a' => 28, 'b' => 8], ['a' => 29, 'b' => 67],
    ['a' => 30, 'b' => 29], ['a' => 31, 'b' => 93], ['a' => 32, 'b' => 84], ['a' => 33, 'b' => 77],
    ['a' => 34, 'b' => 23], ['a' => 35, 'b' => 16], ['a' => 36, 'b' => 16], ['a' => 37, 'b' => 93],
    ['a' => 38, 'b' => 65], ['a' => 39, 'b' => 35], ['a' => 40, 'b' => 47], ['a' => 41, 'b' => 7],
    ['a' => 42, 'b' => 86], ['a' => 43, 'b' => 74], ['a' => 44, 'b' => 61], ['a' => 45, 'b' => 91],
    ['a' => 46, 'b' => 85], ['a' => 47, 'b' => 24], ['a' => 48, 'b' => 85], ['a' => 49, 'b' => 43],
    ['a' => 50, 'b' => 59], ['a' => 51, 'b' => 12], ['a' => 52, 'b' => 32], ['a' => 53, 'b' => 56],
    ['a' => 54, 'b' => 3], ['a' => 55, 'b' => 91], ['a' => 56, 'b' => 22], ['a' => 57, 'b' => 90],
    ['a' => 58, 'b' => 55], ['a' => 59, 'b' => 15], ['a' => 60, 'b' => 28], ['a' => 61, 'b' => 89],
    ['a' => 62, 'b' => 25], ['a' => 63, 'b' => 47], ['a' => 64, 'b' => 1], ['a' => 65, 'b' => 56],
    ['a' => 66, 'b' => 40], ['a' => 67, 'b' => 43], ['a' => 68, 'b' => 56], ['a' => 69, 'b' => 16],
    ['a' => 70, 'b' => 75], ['a' => 71, 'b' => 36], ['a' => 72, 'b' => 89], ['a' => 73, 'b' => 98],
    ['a' => 74, 'b' => 76], ['a' => 75, 'b' => 81], ['a' => 76, 'b' => 4], ['a' => 77, 'b' => 94],
    ['a' => 78, 'b' => 42], ['a' => 79, 'b' => 30], ['a' => 80, 'b' => 78], ['a' => 81, 'b' => 33],
    ['a' => 82, 'b' => 29], ['a' => 83, 'b' => 53], ['a' => 84, 'b' => 63], ['a' => 85, 'b' => 2],
    ['a' => 86, 'b' => 87], ['a' => 87, 'b' => 37], ['a' => 88, 'b' => 80], ['a' => 89, 'b' => 84],
    ['a' => 90, 'b' => 72], ['a' => 91, 'b' => 41], ['a' => 92, 'b' => 9], ['a' => 93, 'b' => 61],
    ['a' => 94, 'b' => 73], ['a' => 95, 'b' => 95], ['a' => 96, 'b' => 65], ['a' => 97, 'b' => 13],
    ['a' => 98, 'b' => 58], ['a' => 99, 'b' => 96], ['a' => 100, 'b' => 98], ['a' => 101, 'b' => 1],
    ['a' => 102, 'b' => 21], ['a' => 103, 'b' => 74], ['a' => 104, 'b' => 65], ['a' => 105, 'b' => 35],
    ['a' => 106, 'b' => 5], ['a' => 107, 'b' => 73], ['a' => 108, 'b' => 11], ['a' => 109, 'b' => 51],
    ['a' => 110, 'b' => 87], ['a' => 111, 'b' => 41], ['a' => 112, 'b' => 12], ['a' => 113, 'b' => 8],
    ['a' => 114, 'b' => 20], ['a' => 115, 'b' => 31], ['a' => 116, 'b' => 31], ['a' => 117, 'b' => 15],
    ['a' => 118, 'b' => 95], ['a' => 119, 'b' => 22], ['a' => 120, 'b' => 73], ['a' => 121, 'b' => 79],
    ['a' => 122, 'b' => 88], ['a' => 123, 'b' => 34], ['a' => 124, 'b' => 8], ['a' => 125, 'b' => 11],
    ['a' => 126, 'b' => 49], ['a' => 127, 'b' => 34], ['a' => 128, 'b' => 90], ['a' => 129, 'b' => 59],
    ['a' => 130, 'b' => 96], ['a' => 131, 'b' => 60], ['a' => 132, 'b' => 55], ['a' => 133, 'b' => 75],
    ['a' => 134, 'b' => 77], ['a' => 135, 'b' => 44], ['a' => 136, 'b' => 2], ['a' => 137, 'b' => 7],
    ['a' => 138, 'b' => 85], ['a' => 139, 'b' => 57], ['a' => 140, 'b' => 74], ['a' => 141, 'b' => 29],
    ['a' => 142, 'b' => 70], ['a' => 143, 'b' => 59], ['a' => 144, 'b' => 19], ['a' => 145, 'b' => 39],
    ['a' => 146, 'b' => 26], ['a' => 147, 'b' => 26], ['a' => 148, 'b' => 47], ['a' => 149, 'b' => 80],
    ['a' => 150, 'b' => 90], ['a' => 151, 'b' => 36], ['a' => 152, 'b' => 58], ['a' => 153, 'b' => 47],
    ['a' => 154, 'b' => 9], ['a' => 155, 'b' => 72], ['a' => 156, 'b' => 72], ['a' => 157, 'b' => 66],
    ['a' => 158, 'b' => 33], ['a' => 159, 'b' => 93], ['a' => 160, 'b' => 75], ['a' => 161, 'b' => 64],
    ['a' => 162, 'b' => 81], ['a' => 163, 'b' => 9], ['a' => 164, 'b' => 23], ['a' => 165, 'b' => 37],
    ['a' => 166, 'b' => 13], ['a' => 167, 'b' => 12], ['a' => 168, 'b' => 14], ['a' => 169, 'b' => 62],
    ['a' => 170, 'b' => 91], ['a' => 171, 'b' => 36], ['a' => 172, 'b' => 91], ['a' => 173, 'b' => 33],
    ['a' => 174, 'b' => 15], ['a' => 175, 'b' => 34], ['a' => 176, 'b' => 36], ['a' => 177, 'b' => 99],
    ['a' => 178, 'b' => 3], ['a' => 179, 'b' => 95], ['a' => 180, 'b' => 69], ['a' => 181, 'b' => 58],
    ['a' => 182, 'b' => 52], ['a' => 183, 'b' => 30], ['a' => 184, 'b' => 50], ['a' => 185, 'b' => 84],
    ['a' => 186, 'b' => 10], ['a' => 187, 'b' => 84], ['a' => 188, 'b' => 33], ['a' => 189, 'b' => 21],
    ['a' => 190, 'b' => 39], ['a' => 191, 'b' => 44], ['a' => 192, 'b' => 58], ['a' => 193, 'b' => 30],
    ['a' => 194, 'b' => 38], ['a' => 195, 'b' => 34], ['a' => 196, 'b' => 83], ['a' => 197, 'b' => 27],
    ['a' => 198, 'b' => 82], ['a' => 199, 'b' => 17], ['a' => 200, 'b' => 7],
];

$window3ByA = $sortRows($window3Rows, [['a', 'ASC']]);
$window3ByB = $sortRows($window3Rows, [['b', 'ASC'], ['a', 'ASC']]);

$tests['real upstream window functions dynamic window3 1.1.3.1 row number by primary key'] = static function (TestRunner $t) use ($window3ByA, $byColumn): void {
    foreach (SQLiteWindowFunction::rowNumber($byColumn($window3ByA, 'a')) as $index => $rowNumber) {
        $t->same($index + 1, $rowNumber, 'window3 row_number a row ' . $index);
    }
};

$tests['real upstream window functions dynamic window3 1.1.4.1 dense rank by primary key'] = static function (TestRunner $t) use ($window3ByA, $byColumn): void {
    foreach (SQLiteWindowFunction::denseRank($byColumn($window3ByA, 'a')) as $index => $rank) {
        $t->same($index + 1, $rank, 'window3 dense_rank a row ' . $index);
    }
};

$tests['real upstream window functions dynamic window3 1.1.4.3 dense rank by b peer groups'] = static function (TestRunner $t) use ($window3ByB, $byColumn): void {
    $previous = null;
    $expectedRank = 0;
    foreach (SQLiteWindowFunction::denseRank($byColumn($window3ByB, 'b')) as $index => $rank) {
        $current = $window3ByB[$index]['b'];
        if ($current !== $previous) {
            $expectedRank++;
            $previous = $current;
        }
        $t->same($expectedRank, $rank, 'window3 dense_rank b row ' . $index);
    }
};

$tests['real upstream window functions dynamic window3 running max and min by primary key'] = static function (TestRunner $t) use ($window3ByA, $byColumn): void {
    $values = $byColumn($window3ByA, 'b');
    $runningMax = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $byColumn($window3ByA, 'a'), 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $runningMin = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $values, $byColumn($window3ByA, 'a'), 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $max = null;
    $min = null;
    foreach ($values as $index => $value) {
        $max = $max === null ? $value : max($max, $value);
        $min = $min === null ? $value : min($min, $value);
        $t->same($max, $runningMax[$index], 'window3 max by a row ' . $index);
        $t->same($min, $runningMin[$index], 'window3 min by a row ' . $index);
    }
};

$tests['real upstream window functions dynamic window2 value functions over row frames'] = static function (TestRunner $t) use ($window2Rows, $sortRows, $byColumn): void {
    $rows = $sortRows($window2Rows, [['d', 'ASC']]);
    $values = $byColumn($rows, 'c');
    $keys = $byColumn($rows, 'd');

    $first = SQLiteWindowFunction::valueFrameValues('first_value', $values, $keys, 'ROWS', 1, 1);
    $last = SQLiteWindowFunction::valueFrameValues('last_value', $values, $keys, 'ROWS', 1, 1);
    $nth = SQLiteWindowFunction::valueFrameValues('nth_value', $values, $keys, 'ROWS', 1, 1, 'NO OTHERS', 2);

    $expectedFirst = ['one', 'one', 'two', 'three', 'four', 'five'];
    $expectedLast = ['two', 'three', 'four', 'five', 'six', 'six'];
    $expectedNth = ['two', 'two', 'three', 'four', 'five', 'six'];

    foreach ($values as $index => $_value) {
        $t->same($expectedFirst[$index], $first[$index], 'first_value row ' . $index);
        $t->same($expectedLast[$index], $last[$index], 'last_value row ' . $index);
        $t->same($expectedNth[$index], $nth[$index], 'nth_value row ' . $index);
    }
};

return $tests;
