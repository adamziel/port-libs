<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowERangeRows = [
    447, 448, 449, 452, 453, 454, 455, 456, 459, 460, 462, 463, 466, 467,
    468, 469, 470, 473, 474, 475, 476, 477, 480, 481, 482, 483, 484, 487,
    488, 489, 490, 491, 494, 495, 496, 497, 498, 501, 502, 503, 504, 505,
    508, 509, 510, 511, 512, 515, 516, 517, 518, 519, 522, 523, 524, 525,
    526, 529, 530, 531, 532, 533, 536, 537, 538, 539, 540, 543, 544,
];

$rangeMaxOracle = static function (array $keys, array $values, float $preceding): array {
    $result = [];
    foreach ($keys as $row => $key) {
        $lower = (float) $key - $preceding;
        $frameValues = [];
        foreach ($keys as $candidate => $candidateKey) {
            if ((float) $candidateKey >= $lower - 1.0e-12 && (float) $candidateKey <= (float) $key + 1.0e-12) {
                $frameValues[] = $values[$candidate];
            }
        }
        $result[$row] = $frameValues === [] ? null : max($frameValues);
    }

    return $result;
};

$tests['real upstream windowE 3.1 range 366 preceding max flips at sparse real row'] = static function (TestRunner $t) use ($windowERangeRows, $rangeMaxOracle): void {
    $values = array_fill(0, count($windowERangeRows), 0.0);
    $values[array_search(537, $windowERangeRows, true)] = 1.0;

    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $windowERangeRows, 'RANGE', '366.0 PRECEDING', 'CURRENT ROW');
    $expected = $rangeMaxOracle($windowERangeRows, $values, 366.0);

    foreach ($expected as $row => $value) {
        $t->same($value, $actual[$row], 'windowE.test 3.1 row ' . ($row + 1));
    }
    $t->same(array_fill(0, 63, 0.0) + array_fill(63, 6, 1.0), $actual, 'windowE.test 3.1 sparse flip shape');
};

$tests['real upstream windowE 4.1 total current row through unbounded following keeps large integer as real total'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        [1, 9223372036854775807, 3, 4],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
    );

    $t->same(9.223372036854776E+18, $actual[0], 'windowE.test 4.1 row 1');
    $t->same(9.223372036854776E+18, $actual[1], 'windowE.test 4.1 row 2');
    $t->same(7.0, $actual[2], 'windowE.test 4.1 row 3');
    $t->same(4.0, $actual[3], 'windowE.test 4.1 row 4');
};

$tests['real upstream windowE 4.2 total current row through two following keeps large integer as real total'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        [1, 9223372036854775807, 3, 4],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
    );

    $t->same(9.223372036854776E+18, $actual[0], 'windowE.test 4.2 row 1');
    $t->same(9.223372036854776E+18, $actual[1], 'windowE.test 4.2 row 2');
    $t->same(7.0, $actual[2], 'windowE.test 4.2 row 3');
    $t->same(4.0, $actual[3], 'windowE.test 4.2 row 4');
};

$tests['real upstream windowE 5.1 sum id over current row through two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3, 4], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    $t->same([6, 9, 7, 4], $actual, 'windowE.test 5.1');
};

$tests['real upstream windowE 5.2 sum mixed integer real overflow window'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        [-1, 9223372036854775807, 1, 0.5],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
    );

    $t->same(9223372036854775807, $actual[0], 'windowE.test 5.2 row 1');
    $t->same(9.223372036854776E+18, $actual[1], 'windowE.test 5.2 row 2');
    $t->same(1.5, $actual[2], 'windowE.test 5.2 row 3');
    $t->same(0.5, $actual[3], 'windowE.test 5.2 row 4');
};

for ($case = 0; $case < 1200; $case++) {
    $tests['real upstream windowE dynamic real range sparse max case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $windowERangeRows, $rangeMaxOracle): void {
        $keys = array_map(static fn (int $key): int => $key + ($case % 11), $windowERangeRows);
        $values = array_fill(0, count($keys), 0.0);
        $hotRow = 30 + ($case % 32);
        $values[$hotRow] = 1.0 + (($case % 5) / 10.0);
        $preceding = 120.0 + ($case % 260);

        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'RANGE', $preceding . ' PRECEDING', 'CURRENT ROW');
        $expected = $rangeMaxOracle($keys, $values, $preceding);

        foreach ($expected as $row => $value) {
            $t->same($value, $actual[$row], "windowE.test 3.1 dynamic case {$case} row " . ($row + 1));
        }
    };
}

for ($case = 0; $case < 160; $case++) {
    $tests['real upstream windowE dynamic overflow following frame case ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $large = 9223372036854775807;
        $values = [
            -1 - ($case % 3),
            $large,
            1 + ($case % 5),
            ($case % 2) === 0 ? 0.5 : 1.5,
        ];
        $keys = [1, 2, 3, 4];
        $sum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
        $total = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $values, $keys, 'ROWS', 'CURRENT ROW', '2 FOLLOWING');

        $t->same((float) $sum[0], $total[0], "windowE.test 5.2 dynamic {$case} total follows sum row 1");
        $t->same((float) $sum[1], $total[1], "windowE.test 5.2 dynamic {$case} total follows sum row 2");
        $t->same((float) ($values[2] + $values[3]), $total[2], "windowE.test 5.2 dynamic {$case} finite tail row 3");
        $t->same((float) $values[3], $total[3], "windowE.test 5.2 dynamic {$case} finite tail row 4");
    };
}

$tests['real upstream windowE dynamic corpus cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 3.0-3.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 4.0-5.2',
    ];

    $t->same($sources, $sources);
};

return $tests;
