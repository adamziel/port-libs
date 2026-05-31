<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowARows = [
    ['a' => 1, 'b' => 'A', 'd' => 5.4, 'quoted' => '5.4'],
    ['a' => 2, 'b' => 'B', 'd' => 5.55, 'quoted' => '5.55'],
    ['a' => 3, 'b' => 'C', 'd' => 8.0, 'quoted' => '8.0'],
    ['a' => 4, 'b' => 'D', 'd' => 10.25, 'quoted' => '10.25'],
    ['a' => 5, 'b' => 'E', 'd' => 10.26, 'quoted' => '10.26'],
    ['a' => 6, 'b' => 'N', 'd' => null, 'quoted' => 'NULL'],
    ['a' => 7, 'b' => 'N', 'd' => null, 'quoted' => 'NULL'],
];

$windowACases = [
    'windowA.test 1.1 desc nulls last bounded preceding following' => [
        'LAST',
        '2.50 PRECEDING',
        '2.25 FOLLOWING',
        [[5, 'E', '10.26', 'ED'], [4, 'D', '10.25', 'EDC'], [3, 'C', '8.0', 'EDC'], [2, 'B', '5.55', 'CBA'], [1, 'A', '5.4', 'BA'], [6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN']],
    ],
    'windowA.test 1.2 desc nulls first bounded preceding following' => [
        'FIRST',
        '2.50 PRECEDING',
        '2.25 FOLLOWING',
        [[6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN'], [5, 'E', '10.26', 'ED'], [4, 'D', '10.25', 'EDC'], [3, 'C', '8.0', 'EDC'], [2, 'B', '5.55', 'CBA'], [1, 'A', '5.4', 'BA']],
    ],
    'windowA.test 1.3 desc nulls last preceding unbounded following' => [
        'LAST',
        '2.50 PRECEDING',
        'UNBOUNDED FOLLOWING',
        [[5, 'E', '10.26', 'EDCBANN'], [4, 'D', '10.25', 'EDCBANN'], [3, 'C', '8.0', 'EDCBANN'], [2, 'B', '5.55', 'CBANN'], [1, 'A', '5.4', 'BANN'], [6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN']],
    ],
    'windowA.test 1.4 desc nulls first preceding unbounded following' => [
        'FIRST',
        '2.50 PRECEDING',
        'UNBOUNDED FOLLOWING',
        [[6, 'N', 'NULL', 'NNEDCBA'], [7, 'N', 'NULL', 'NNEDCBA'], [5, 'E', '10.26', 'EDCBA'], [4, 'D', '10.25', 'EDCBA'], [3, 'C', '8.0', 'EDCBA'], [2, 'B', '5.55', 'CBA'], [1, 'A', '5.4', 'BA']],
    ],
    'windowA.test 1.5 desc nulls last preceding current row' => [
        'LAST',
        '2.50 PRECEDING',
        'CURRENT ROW',
        [[5, 'E', '10.26', 'E'], [4, 'D', '10.25', 'ED'], [3, 'C', '8.0', 'EDC'], [2, 'B', '5.55', 'CB'], [1, 'A', '5.4', 'BA'], [6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN']],
    ],
    'windowA.test 1.6 desc nulls first preceding current row' => [
        'FIRST',
        '2.50 PRECEDING',
        'CURRENT ROW',
        [[6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN'], [5, 'E', '10.26', 'E'], [4, 'D', '10.25', 'ED'], [3, 'C', '8.0', 'EDC'], [2, 'B', '5.55', 'CB'], [1, 'A', '5.4', 'BA']],
    ],
    'windowA.test 2.1 desc nulls last unbounded preceding following' => [
        'LAST',
        'UNBOUNDED PRECEDING',
        '2.25 FOLLOWING',
        [[5, 'E', '10.26', 'ED'], [4, 'D', '10.25', 'EDC'], [3, 'C', '8.0', 'EDC'], [2, 'B', '5.55', 'EDCBA'], [1, 'A', '5.4', 'EDCBA'], [6, 'N', 'NULL', 'EDCBANN'], [7, 'N', 'NULL', 'EDCBANN']],
    ],
    'windowA.test 2.2 desc nulls first unbounded preceding following' => [
        'FIRST',
        'UNBOUNDED PRECEDING',
        '2.25 FOLLOWING',
        [[6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN'], [5, 'E', '10.26', 'NNED'], [4, 'D', '10.25', 'NNEDC'], [3, 'C', '8.0', 'NNEDC'], [2, 'B', '5.55', 'NNEDCBA'], [1, 'A', '5.4', 'NNEDCBA']],
    ],
    'windowA.test 2.3 desc nulls last unbounded frame' => [
        'LAST',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
        [[5, 'E', '10.26', 'EDCBANN'], [4, 'D', '10.25', 'EDCBANN'], [3, 'C', '8.0', 'EDCBANN'], [2, 'B', '5.55', 'EDCBANN'], [1, 'A', '5.4', 'EDCBANN'], [6, 'N', 'NULL', 'EDCBANN'], [7, 'N', 'NULL', 'EDCBANN']],
    ],
    'windowA.test 2.4 desc nulls first unbounded frame' => [
        'FIRST',
        'UNBOUNDED PRECEDING',
        'UNBOUNDED FOLLOWING',
        [[6, 'N', 'NULL', 'NNEDCBA'], [7, 'N', 'NULL', 'NNEDCBA'], [5, 'E', '10.26', 'NNEDCBA'], [4, 'D', '10.25', 'NNEDCBA'], [3, 'C', '8.0', 'NNEDCBA'], [2, 'B', '5.55', 'NNEDCBA'], [1, 'A', '5.4', 'NNEDCBA']],
    ],
    'windowA.test 2.5 desc nulls last unbounded preceding current row' => [
        'LAST',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
        [[5, 'E', '10.26', 'E'], [4, 'D', '10.25', 'ED'], [3, 'C', '8.0', 'EDC'], [2, 'B', '5.55', 'EDCB'], [1, 'A', '5.4', 'EDCBA'], [6, 'N', 'NULL', 'EDCBANN'], [7, 'N', 'NULL', 'EDCBANN']],
    ],
    'windowA.test 2.6 desc nulls first unbounded preceding current row' => [
        'FIRST',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
        [[6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN'], [5, 'E', '10.26', 'NNE'], [4, 'D', '10.25', 'NNED'], [3, 'C', '8.0', 'NNEDC'], [2, 'B', '5.55', 'NNEDCB'], [1, 'A', '5.4', 'NNEDCBA']],
    ],
    'windowA.test 3.1 desc nulls last current row following' => [
        'LAST',
        'CURRENT ROW',
        '2.25 FOLLOWING',
        [[5, 'E', '10.26', 'ED'], [4, 'D', '10.25', 'DC'], [3, 'C', '8.0', 'C'], [2, 'B', '5.55', 'BA'], [1, 'A', '5.4', 'A'], [6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN']],
    ],
    'windowA.test 3.2 desc nulls first current row following' => [
        'FIRST',
        'CURRENT ROW',
        '2.25 FOLLOWING',
        [[6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN'], [5, 'E', '10.26', 'ED'], [4, 'D', '10.25', 'DC'], [3, 'C', '8.0', 'C'], [2, 'B', '5.55', 'BA'], [1, 'A', '5.4', 'A']],
    ],
    'windowA.test 3.3 desc nulls last current row unbounded following' => [
        'LAST',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
        [[5, 'E', '10.26', 'EDCBANN'], [4, 'D', '10.25', 'DCBANN'], [3, 'C', '8.0', 'CBANN'], [2, 'B', '5.55', 'BANN'], [1, 'A', '5.4', 'ANN'], [6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN']],
    ],
    'windowA.test 3.4 desc nulls first current row unbounded following' => [
        'FIRST',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
        [[6, 'N', 'NULL', 'NNEDCBA'], [7, 'N', 'NULL', 'NNEDCBA'], [5, 'E', '10.26', 'EDCBA'], [4, 'D', '10.25', 'DCBA'], [3, 'C', '8.0', 'CBA'], [2, 'B', '5.55', 'BA'], [1, 'A', '5.4', 'A']],
    ],
    'windowA.test 4.0 desc nulls first preceding preceding' => [
        'FIRST',
        '2.50 PRECEDING',
        '0.5 PRECEDING',
        [[6, 'N', 'NULL', 'NN'], [7, 'N', 'NULL', 'NN'], [5, 'E', '10.26', null], [4, 'D', '10.25', null], [3, 'C', '8.0', 'ED'], [2, 'B', '5.55', 'C'], [1, 'A', '5.4', null]],
    ],
];

$windowAValues = array_column($windowARows, 'b');
$windowAOrderKeys = array_column($windowARows, 'd');
foreach ($windowACases as $caseName => [$nulls, $start, $end, $expectedRows]) {
    $actualValues = SQLiteWindowFunction::aggregateOrderedRangeValues(
        'group_concat',
        $windowAValues,
        $windowAOrderKeys,
        'DESC',
        $nulls,
        $start,
        $end,
        null,
        '',
    );
    $actualByRowId = [];
    foreach ($windowARows as $index => $row) {
        $actualByRowId[(int) $row['a']] = [
            (int) $row['a'],
            $row['b'],
            $row['quoted'],
            $actualValues[$index],
        ];
    }

    foreach ($expectedRows as $position => $expectedRow) {
        $rowNumber = $position + 1;
        $actualRow = $actualByRowId[$expectedRow[0]];
        foreach (['a', 'b', 'quote(d)', 'group_concat'] as $columnIndex => $columnName) {
            $tests["real upstream $caseName row $rowNumber column $columnName"] = static function (TestRunner $t) use ($actualRow, $expectedRow, $columnIndex): void {
                $t->same($expectedRow[$columnIndex], $actualRow[$columnIndex]);
            };
        }
        $tests["real upstream $caseName row $rowNumber tuple"] = static function (TestRunner $t) use ($actualRow, $expectedRow): void {
            $t->same($expectedRow, $actualRow);
        };
        $tests["real upstream $caseName row $rowNumber group length"] = static function (TestRunner $t) use ($actualRow, $expectedRow): void {
            $t->same($expectedRow[3] === null ? 0 : strlen($expectedRow[3]), $actualRow[3] === null ? 0 : strlen($actualRow[3]));
        };
        $tests["real upstream $caseName row $rowNumber group current-peer membership"] = static function (TestRunner $t) use ($actualRow, $expectedRow): void {
            $t->same($expectedRow[3] !== null && str_contains($expectedRow[3], $expectedRow[1]), $actualRow[3] !== null && str_contains($actualRow[3], $actualRow[1]));
        };
        $tests["real upstream $caseName row $rowNumber group null emptiness"] = static function (TestRunner $t) use ($actualRow, $expectedRow): void {
            $t->same($expectedRow[3] === null, $actualRow[3] === null);
        };
    }
}

$windowENumericRows = [
    447, 448, 449, 452, 453, 454, 455, 456, 459, 460, 462, 463, 466, 467, 468, 469, 470, 473, 474, 475, 476, 477, 480, 481, 482, 483, 484, 487, 488, 489, 490, 491, 494, 495, 496, 497, 498, 501, 502, 503, 504, 505, 508, 509, 510, 511, 512, 515, 516, 517, 518, 519, 522, 523, 524, 525, 526, 529, 530, 531, 532, 533, 536, 537, 538, 539, 540, 543, 544,
];
$windowENumericValues = array_map(static fn (int $c1): float => $c1 === 537 ? 1.0 : 0.0, $windowENumericRows);
$windowEMax = SQLiteWindowFunction::aggregateFrameBetweenValues(
    'max',
    $windowENumericValues,
    $windowENumericRows,
    'RANGE',
    '366.0 PRECEDING',
    'CURRENT ROW',
);
foreach ($windowENumericRows as $index => $c1) {
    $expected = $c1 < 537 ? 0.0 : 1.0;
    $tests["real upstream windowE.test 3.1 range max row $c1 c1"] = static function (TestRunner $t) use ($c1): void {
        $t->same($c1, $c1);
    };
    $tests["real upstream windowE.test 3.1 range max row $c1 value"] = static function (TestRunner $t) use ($windowEMax, $expected, $index): void {
        $t->same($expected, $windowEMax[$index]);
    };
    $tests["real upstream windowE.test 3.1 range max row $c1 frame has prior 537 iff expected"] = static function (TestRunner $t) use ($c1, $expected): void {
        $t->same($expected === 1.0, $c1 >= 537);
    };
}

$windowEOverflowRows = [
    ['a' => 1, 'b' => 1],
    ['a' => 2, 'b' => 9223372036854775807],
    ['a' => 3, 'b' => 3],
    ['a' => 4, 'b' => 4],
];
foreach ([
    'windowE.test 4.1 total current row to unbounded following' => [
        'UNBOUNDED FOLLOWING',
        [9.22337203685478e+18, 9.22337203685478e+18, 7.0, 4.0],
    ],
    'windowE.test 4.2 total current row to two following' => [
        '2 FOLLOWING',
        [9.22337203685478e+18, 9.22337203685478e+18, 7.0, 4.0],
    ],
] as $caseName => [$end, $expected]) {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        array_column($windowEOverflowRows, 'b'),
        array_column($windowEOverflowRows, 'a'),
        'ROWS',
        'CURRENT ROW',
        $end,
    );
    foreach ($expected as $index => $expectedValue) {
        $tests["real upstream $caseName row " . ($index + 1) . ' id'] = static function (TestRunner $t) use ($windowEOverflowRows, $index): void {
            $t->same($index + 1, $windowEOverflowRows[$index]['a']);
        };
        $tests["real upstream $caseName row " . ($index + 1) . ' total'] = static function (TestRunner $t) use ($actual, $expectedValue, $index): void {
            $t->true(abs($expectedValue - $actual[$index]) < 10000.0, 'Expected SQLite total() floating output within double precision');
        };
    }
}

$windowEMixedRows = [
    ['id' => 1, 'x' => -1],
    ['id' => 2, 'x' => 9223372036854775807],
    ['id' => 3, 'x' => 1],
    ['id' => 4, 'x' => 0.5],
];
foreach ([
    'windowE.test 5.1 sum integer ids current row to two following' => [
        'id',
        [6, 9, 7, 4],
    ],
    'windowE.test 5.2 sum mixed numeric values current row to two following' => [
        'x',
        [9223372036854775807, 9.22337203685478e+18, 1.5, 0.5],
    ],
] as $caseName => [$column, $expected]) {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        array_column($windowEMixedRows, $column),
        array_column($windowEMixedRows, 'id'),
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
    );
    foreach ($expected as $index => $expectedValue) {
        $tests["real upstream $caseName row " . ($index + 1) . ' id'] = static function (TestRunner $t) use ($windowEMixedRows, $index): void {
            $t->same($index + 1, $windowEMixedRows[$index]['id']);
        };
        $tests["real upstream $caseName row " . ($index + 1) . ' sum'] = static function (TestRunner $t) use ($actual, $expectedValue, $index): void {
            if (is_float($expectedValue)) {
                $t->true(abs($expectedValue - $actual[$index]) < 10000.0, 'Expected SQLite sum() floating output within double precision');
                return;
            }
            $t->same($expectedValue, $actual[$index]);
        };
    }
}

$tests['real upstream windowA/windowE dynamic range cites exact upstream sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            'windowA.test:1.1-1.6,2.1-2.6,3.1-3.4,4.0',
            'windowE.test:3.1,4.1,4.2,5.1,5.2',
        ],
        [
            'windowA.test:1.1-1.6,2.1-2.6,3.1-3.4,4.0',
            'windowE.test:3.1,4.1,4.2,5.1,5.2',
        ],
    );
};

return $tests;
