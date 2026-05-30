<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$letters = range('a', 'j');
$ntileExpected = [
    1 => [1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
    2 => [1, 1, 1, 1, 1, 2, 2, 2, 2, 2],
    3 => [1, 1, 1, 1, 2, 2, 2, 3, 3, 3],
    4 => [1, 1, 1, 2, 2, 2, 3, 3, 4, 4],
    5 => [1, 1, 2, 2, 3, 3, 4, 4, 5, 5],
    6 => [1, 1, 2, 2, 3, 3, 4, 4, 5, 6],
    7 => [1, 1, 2, 2, 3, 3, 4, 5, 6, 7],
    8 => [1, 1, 2, 2, 3, 4, 5, 6, 7, 8],
    9 => [1, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    10 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    11 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    12 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    13 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    14 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    15 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    16 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    17 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    18 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    19 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
];

foreach ($ntileExpected as $bucketCount => $expectedBuckets) {
    $tests['real upstream window4 ntile ' . $bucketCount . ' distributes generated t3 rows'] = static function (TestRunner $t) use ($letters, $bucketCount, $expectedBuckets): void {
        $actual = SQLiteWindowFunction::ntile($letters, $bucketCount);
        foreach ($letters as $index => $letter) {
            $t->same(chr(ord('a') + $index), $letter, "window4.test 1.{$bucketCount} row label {$index}");
            $t->same($expectedBuckets[$index], $actual[$index], "window4.test 1.{$bucketCount} {$letter}");
        }
    };
}

$t4Ids = range(1, 10);
$t4Text = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
$t4Nth = [9, 3, 2, 10, 5, 1, 1, 2, 10, 4];

$window4Cases = [
    '2.1 dynamic nth_value b by c' => [
        static fn (): array => SQLiteWindowFunction::nthValueByRow($t4Text, $t4Nth, $t4Ids),
        [null, null, 'B', null, 'E', 'A', 'A', 'B', null, 'D'],
    ],
    '2.2.1 lead default' => [
        static fn (): array => SQLiteWindowFunction::lead($t4Text),
        ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null],
    ],
    '2.2.2 lead offset two' => [
        static fn (): array => SQLiteWindowFunction::lead($t4Text, 2),
        ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null, null],
    ],
    '2.2.3 lead offset three default text' => [
        static fn (): array => SQLiteWindowFunction::lead($t4Text, 3, 'abc'),
        ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'abc', 'abc', 'abc'],
    ],
    '2.3.1 lag default' => [
        static fn (): array => SQLiteWindowFunction::lag($t4Text),
        [null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
    ],
    '2.3.2 lag offset two' => [
        static fn (): array => SQLiteWindowFunction::lag($t4Text, 2),
        [null, null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
    ],
    '2.3.3 lag offset three default text' => [
        static fn (): array => SQLiteWindowFunction::lag($t4Text, 3, 'abc'),
        ['abc', 'abc', 'abc', 'A', 'B', 'C', 'D', 'E', 'F', 'G'],
    ],
    '2.4.1 following group concat' => [
        static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $t4Text, $t4Ids, 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'),
        ['A,B,C,D,E,F,G,H,I,J', 'B,C,D,E,F,G,H,I,J', 'C,D,E,F,G,H,I,J', 'D,E,F,G,H,I,J', 'E,F,G,H,I,J', 'F,G,H,I,J', 'G,H,I,J', 'H,I,J', 'I,J', 'J'],
    ],
];

foreach ($window4Cases as $name => [$actual, $expected]) {
    $tests['real upstream window4 ' . $name] = static function (TestRunner $t) use ($actual, $expected, $name): void {
        $values = $actual();
        foreach ($expected as $index => $expectedValue) {
            $t->same($expectedValue, $values[$index], "window4.test {$name} row {$index}");
        }
    };
}

$t5Ids = [1, 3, 5, 2, 4];
$t5ValuesInOrderByB = ['one', 'three', 'five', 'two', 'four'];
$t5DynamicNthInOrderByB = [5, 3, 1, 4, 2];
$tests['real upstream window4 3.1 nth_value over non unique text order'] = static function (TestRunner $t) use ($t5ValuesInOrderByB, $t5DynamicNthInOrderByB): void {
    $actual = SQLiteWindowFunction::nthValueByRow($t5ValuesInOrderByB, $t5DynamicNthInOrderByB, ['A', 'A', 'A', 'B', 'B'], 'GROUPS');
    $expected = [null, 'five', 'one', 'two', 'three'];
    foreach ($expected as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'window4.test 3.1 output row ' . $index);
    }
};

$tests['real upstream window4 3.2 nth_value restarts by partition'] = static function (TestRunner $t): void {
    $aPartition = SQLiteWindowFunction::nthValueByRow(['one', 'three', 'five'], [5, 3, 1]);
    $bPartition = SQLiteWindowFunction::nthValueByRow(['two', 'four'], [4, 2]);
    $actual = [$aPartition[0], $aPartition[1], $aPartition[2], $bPartition[0], $bPartition[1]];
    $expected = [null, null, 'one', null, 'four'];
    foreach ($expected as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'window4.test 3.2 output row ' . $index);
    }
};

$tests['real upstream window4 3.3 opposing named windows count rows'] = static function (TestRunner $t): void {
    $forward = SQLiteWindowFunction::aggregateFrameBetweenValues('count', [1, 1, 1, 1, 1], [1, 2, 3, 4, 5], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $reverse = SQLiteWindowFunction::aggregateFrameBetweenValues('count', [1, 1, 1, 1, 1], [1, 2, 3, 4, 5], 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    $expectedForward = [1, 2, 3, 4, 5];
    $expectedReverse = [5, 4, 3, 2, 1];
    foreach ($expectedForward as $index => $expectedValue) {
        $t->same($expectedValue, $forward[$index], 'window4.test 3.3 ascending count row ' . $index);
        $t->same($expectedReverse[$index], $reverse[$index], 'window4.test 3.3 descending count row ' . $index);
    }
};

$tests['real upstream window4 3.4 filtered max preserves prior even rows'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', [1, 2, 3, 4, 5], [1, 2, 3, 4, 5], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', [0, 1, 0, 1, 0]);
    $expected = [null, 2, 2, 4, 4];
    foreach ($expected as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'window4.test 3.4 output row ' . $index);
    }
};

$boundaryCases = [
    '3.5.1 one preceding to two preceding empty' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '1 PRECEDING', '2 PRECEDING', [null, null, null, null, null]],
    '3.5.2 one preceding to one preceding' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '1 PRECEDING', '1 PRECEDING', [null, 'one', 'two', 'three', 'four']],
    '3.5.3 zero preceding to zero preceding' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '0 PRECEDING', '0 PRECEDING', ['one', 'two', 'three', 'four', 'five']],
    '3.6.1 two following to one following empty' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '2 FOLLOWING', '1 FOLLOWING', [null, null, null, null, null]],
    '3.6.2 one following to one following' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '1 FOLLOWING', '1 FOLLOWING', ['two', 'three', 'four', 'five', null]],
    '3.6.3 zero following to zero following' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '0 FOLLOWING', '0 FOLLOWING', ['one', 'two', 'three', 'four', 'five']],
];

foreach ($boundaryCases as $name => [$function, $values, $unit, $start, $end, $expected]) {
    $tests['real upstream window4 ' . $name] = static function (TestRunner $t) use ($function, $values, $unit, $start, $end, $expected, $name): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, range(1, count($values)), $unit, $start, $end);
        foreach ($expected as $index => $expectedValue) {
            $t->same($expectedValue, $actual[$index], "window4.test {$name} row {$index}");
        }
    };
}

$tests['real upstream windowE 3.1 numeric range preceding carries distant max'] = static function (TestRunner $t): void {
    $ids = [
        447, 448, 449, 452, 453, 454, 455, 456, 459, 460, 462, 463, 466, 467, 468, 469, 470, 473, 474, 475,
        476, 477, 480, 481, 482, 483, 484, 487, 488, 489, 490, 491, 494, 495, 496, 497, 498, 501, 502, 503,
        504, 505, 508, 509, 510, 511, 512, 515, 516, 517, 518, 519, 522, 523, 524, 525, 526, 529, 530, 531,
        532, 533, 536, 537, 538, 539, 540, 543, 544,
    ];
    $values = array_fill(0, count($ids), 0.0);
    $values[array_search(537, $ids, true)] = 1.0;
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $ids, 'RANGE', '366.0 PRECEDING', 'CURRENT ROW');
    foreach ($ids as $index => $id) {
        $t->same($id >= 537 ? 1.0 : 0.0, $actual[$index], 'windowE.test 3.1 id ' . $id);
    }
};

$windowETotalRows = [1, 9223372036854775807, 3, 4];
$windowETotalExpected = [9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0];
foreach ([
    '4.1 unbounded following total' => ['UNBOUNDED FOLLOWING', $windowETotalExpected],
    '4.2 two following total' => ['2 FOLLOWING', $windowETotalExpected],
] as $name => [$end, $expected]) {
    $tests['real upstream windowE ' . $name] = static function (TestRunner $t) use ($windowETotalRows, $end, $expected, $name): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $windowETotalRows, range(1, 4), 'ROWS', 'CURRENT ROW', $end);
        foreach ($expected as $index => $expectedValue) {
            $t->same($expectedValue, $actual[$index], "windowE.test {$name} row {$index}");
        }
    };
}

$tests['real upstream windowE 5.1 integer sum rows current to two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3, 4], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    foreach ([6, 9, 7, 4] as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'windowE.test 5.1 row ' . $index);
    }
};

$tests['real upstream windowE 5.2 mixed integer real sum rows current to two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [-1, 9223372036854775807, 1, 0.5], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    foreach ([9223372036854775807, 9.223372036854776E+18, 1.5, 0.5] as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'windowE.test 5.2 row ' . $index);
    }
};

$tests['real upstream window dynamic rejects row count mismatch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow(['a'], [1, 2], [1]));
};

$tests['real upstream window dynamic rejects non integer nth value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow(['a'], [1.5], [1]));
};

$tests['real upstream window dynamic rejects non positive nth value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow(['a'], [0], [1]));
};

return $tests;
