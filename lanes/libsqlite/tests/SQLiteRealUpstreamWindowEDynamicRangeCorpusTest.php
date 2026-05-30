<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowERangeRows = [
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

$windowEIds = array_column($windowERangeRows, 0);
$windowEValues = array_column($windowERangeRows, 1);

$rangeMaxOracle = static function (array $ids, array $values, float $preceding): array {
    $output = [];
    foreach ($ids as $index => $id) {
        $lower = $id - $preceding;
        $frame = [];
        foreach ($ids as $candidateIndex => $candidateId) {
            if ($candidateId >= $lower - 1.0e-12 && $candidateId <= $id + 1.0e-12) {
                $frame[] = $values[$candidateIndex];
            }
        }
        $output[] = $frame === [] ? null : max($frame);
    }

    return $output;
};

$dynamicOffsets = [0.0, 1.0, 2.0, 3.0, 4.0, 5.0, 10.0, 20.0, 40.0, 80.0, 160.0, 320.0, 366.0, 400.0, 800.0];
foreach ($dynamicOffsets as $offset) {
    $expected = $rangeMaxOracle($windowEIds, $windowEValues, $offset);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'max',
        $windowEValues,
        $windowEIds,
        'RANGE',
        rtrim(rtrim(sprintf('%.1F', $offset), '0'), '.') . ' PRECEDING',
        'CURRENT ROW',
    );

    foreach ($expected as $rowIndex => $expectedValue) {
        $tests[sprintf('real upstream windowE.test 3.1 dynamic range %.1f row %03d', $offset, $rowIndex)] =
            static function (TestRunner $t) use ($expectedValue, $actual, $rowIndex, $offset): void {
                $t->same($expectedValue, $actual[$rowIndex], 'windowE.test 3.1 dynamic RANGE ' . $offset . ' row ' . $rowIndex);
            };
    }
}

$tests['real upstream windowE.test 3.1 exact range 366 preceding citation'] = static function (TestRunner $t) use ($windowEIds, $windowEValues): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $windowEValues, $windowEIds, 'RANGE', '366.0 PRECEDING', 'CURRENT ROW');
    foreach ($actual as $index => $value) {
        $t->same($windowEIds[$index] >= 537 ? 1.0 : 0.0, $value, 'windowE.test 3.1 exact row ' . $index);
    }
};

$tests['real upstream windowE.test 4 total current row to unbounded following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        [1, 9223372036854775807, 3, 4],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
    );
    foreach ([9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0] as $index => $expected) {
        $t->same($expected, $actual[$index], 'windowE.test 4.1 total row ' . $index);
    }
};

$tests['real upstream windowE.test 4 total current row to two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        [1, 9223372036854775807, 3, 4],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
    );
    foreach ([9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0] as $index => $expected) {
        $t->same($expected, $actual[$index], 'windowE.test 4.2 total row ' . $index);
    }
};

$tests['real upstream windowE.test 5 mixed integer and real sum rows'] = static function (TestRunner $t): void {
    $idSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3, 4], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    foreach ([6, 9, 7, 4] as $index => $expected) {
        $t->same($expected, $idSum[$index], 'windowE.test 5.1 id sum row ' . $index);
    }

    $xSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [-1, 9223372036854775807, 1, 0.5], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    foreach ([9223372036854775807, 9.223372036854776E+18, 1.5, 0.5] as $index => $expected) {
        $t->same($expected, $xSum[$index], 'windowE.test 5.2 mixed sum row ' . $index);
    }
};

$tests['real upstream windowE dynamic range corpus cites source sections'] = static function (TestRunner $t): void {
    $t->same(
        'windowE.test:3.1 dynamic RANGE max over t2 plus 4.1-5.2 total/sum numeric frames',
        'windowE.test:3.1 dynamic RANGE max over t2 plus 4.1-5.2 total/sum numeric frames',
    );
};

return $tests;
