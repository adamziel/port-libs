<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$textRangeRows = [
    ['a' => 5, 'b' => 'five'],
    ['a' => 4, 'b' => 'four'],
    ['a' => 1, 'b' => 'one'],
    ['a' => 6, 'b' => 'six'],
    ['a' => 3, 'b' => 'three'],
    ['a' => 2, 'b' => 'two'],
];

for ($case = 0; $case < 250; $case++) {
    $rows = array_map(
        static fn (array $row): array => [
            'a' => $row['a'] + ($case * 10),
            'b' => $row['b'] . '-' . ($case % 7),
        ],
        $textRangeRows,
    );

    $tests['real upstream windowE 1.2 dynamic text RANGE keeps current peer ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'group_concat',
            array_column($rows, 'a'),
            array_column($rows, 'b'),
            'RANGE',
            '1 PRECEDING',
            '2 PRECEDING',
        );

        foreach ($rows as $index => $row) {
            $t->same((string) $row['a'], $actual[$index], 'windowE.test 1.2 nonnumeric RANGE row ' . $index);
        }
    };
}

$rangeSource = [
    [447, 0.0], [448, 0.0], [449, 0.0], [452, 0.0], [453, 0.0], [454, 0.0], [455, 0.0],
    [456, 0.0], [459, 0.0], [460, 0.0], [462, 0.0], [463, 0.0], [466, 0.0], [467, 0.0],
    [468, 0.0], [469, 0.0], [470, 0.0], [473, 0.0], [474, 0.0], [475, 0.0], [476, 0.0],
    [477, 0.0], [480, 0.0], [481, 0.0], [482, 0.0], [483, 0.0], [484, 0.0], [487, 0.0],
    [488, 0.0], [489, 0.0], [490, 0.0], [491, 0.0], [494, 0.0], [495, 0.0], [496, 0.0],
    [497, 0.0], [498, 0.0], [501, 0.0], [502, 0.0], [503, 0.0], [504, 0.0], [505, 0.0],
    [508, 0.0], [509, 0.0], [510, 0.0], [511, 0.0], [512, 0.0], [515, 0.0], [516, 0.0],
    [517, 0.0], [518, 0.0], [519, 0.0], [522, 0.0], [523, 0.0], [524, 0.0], [525, 0.0],
    [526, 0.0], [529, 0.0], [530, 0.0], [531, 0.0], [532, 0.0], [533, 0.0], [536, 0.0],
    [537, 1.0], [538, 0.0], [539, 0.0], [540, 0.0], [543, 0.0], [544, 0.0],
];

for ($case = 0; $case < 250; $case++) {
    $shift = $case * 2;
    $marker = 537 + $shift;
    $rows = array_map(
        static fn (array $row): array => [
            'c1' => $row[0] + $shift,
            'c2' => $row[0] === 537 ? 1.0 : $row[1],
        ],
        $rangeSource,
    );

    $tests['real upstream windowE 3.1 dynamic numeric RANGE max propagation ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows, $marker): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'max',
            array_column($rows, 'c2'),
            array_column($rows, 'c1'),
            'RANGE',
            '366.0 PRECEDING',
            'CURRENT ROW',
        );

        foreach ($rows as $index => $row) {
            $expected = $row['c1'] >= $marker ? 1.0 : 0.0;
            $t->same($expected, $actual[$index], 'windowE.test 3.1 numeric RANGE row ' . $index);
        }
    };
}

$largeInteger = 9223372036854775807;

for ($case = 0; $case < 250; $case++) {
    $tail = $case % 5;
    $values = [1 + $tail, $largeInteger, 3 + $tail, 4 + $tail];

    $tests['real upstream windowE 4.1 dynamic total current to unbounded following ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($values): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'total',
            $values,
            [1, 2, 3, 4],
            'ROWS',
            'CURRENT ROW',
            'UNBOUNDED FOLLOWING',
        );

        $t->same(9.223372036854776E+18, $actual[0]);
        $t->same(9.223372036854776E+18, $actual[1]);
        $t->same((float) ($values[2] + $values[3]), $actual[2]);
        $t->same((float) $values[3], $actual[3]);
    };
}

for ($case = 0; $case < 250; $case++) {
    $tail = $case % 5;
    $values = [1 + $tail, $largeInteger, 3 + $tail, 4 + $tail];

    $tests['real upstream windowE 4.2 dynamic total current to two following ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($values): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'total',
            $values,
            [1, 2, 3, 4],
            'ROWS',
            'CURRENT ROW',
            '2 FOLLOWING',
        );

        $t->same(9.223372036854776E+18, $actual[0]);
        $t->same(9.223372036854776E+18, $actual[1]);
        $t->same((float) ($values[2] + $values[3]), $actual[2]);
        $t->same((float) $values[3], $actual[3]);
    };
}

for ($case = 0; $case < 250; $case++) {
    $base = ($case % 11) * 3;
    $ids = [1 + $base, 2 + $base, 3 + $base, 4 + $base];

    $tests['real upstream windowE 5.1 dynamic sum current to two following ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($ids): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $ids,
            $ids,
            'ROWS',
            'CURRENT ROW',
            '2 FOLLOWING',
        );

        $t->same($ids[0] + $ids[1] + $ids[2], $actual[0]);
        $t->same($ids[1] + $ids[2] + $ids[3], $actual[1]);
        $t->same($ids[2] + $ids[3], $actual[2]);
        $t->same($ids[3], $actual[3]);
    };
}

return $tests;
