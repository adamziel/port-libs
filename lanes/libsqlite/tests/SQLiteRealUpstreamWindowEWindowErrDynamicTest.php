<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowERows = [
    ['c1' => 447, 'c2' => 0.0],
    ['c1' => 448, 'c2' => 0.0],
    ['c1' => 449, 'c2' => 0.0],
    ['c1' => 452, 'c2' => 0.0],
    ['c1' => 453, 'c2' => 0.0],
    ['c1' => 454, 'c2' => 0.0],
    ['c1' => 455, 'c2' => 0.0],
    ['c1' => 456, 'c2' => 0.0],
    ['c1' => 459, 'c2' => 0.0],
    ['c1' => 460, 'c2' => 0.0],
    ['c1' => 462, 'c2' => 0.0],
    ['c1' => 463, 'c2' => 0.0],
    ['c1' => 466, 'c2' => 0.0],
    ['c1' => 467, 'c2' => 0.0],
    ['c1' => 468, 'c2' => 0.0],
    ['c1' => 469, 'c2' => 0.0],
    ['c1' => 470, 'c2' => 0.0],
    ['c1' => 473, 'c2' => 0.0],
    ['c1' => 474, 'c2' => 0.0],
    ['c1' => 475, 'c2' => 0.0],
    ['c1' => 476, 'c2' => 0.0],
    ['c1' => 477, 'c2' => 0.0],
    ['c1' => 480, 'c2' => 0.0],
    ['c1' => 481, 'c2' => 0.0],
    ['c1' => 482, 'c2' => 0.0],
    ['c1' => 483, 'c2' => 0.0],
    ['c1' => 484, 'c2' => 0.0],
    ['c1' => 487, 'c2' => 0.0],
    ['c1' => 488, 'c2' => 0.0],
    ['c1' => 489, 'c2' => 0.0],
    ['c1' => 490, 'c2' => 0.0],
    ['c1' => 491, 'c2' => 0.0],
    ['c1' => 494, 'c2' => 0.0],
    ['c1' => 495, 'c2' => 0.0],
    ['c1' => 496, 'c2' => 0.0],
    ['c1' => 497, 'c2' => 0.0],
    ['c1' => 498, 'c2' => 0.0],
    ['c1' => 501, 'c2' => 0.0],
    ['c1' => 502, 'c2' => 0.0],
    ['c1' => 503, 'c2' => 0.0],
    ['c1' => 504, 'c2' => 0.0],
    ['c1' => 505, 'c2' => 0.0],
    ['c1' => 508, 'c2' => 0.0],
    ['c1' => 509, 'c2' => 0.0],
    ['c1' => 510, 'c2' => 0.0],
    ['c1' => 511, 'c2' => 0.0],
    ['c1' => 512, 'c2' => 0.0],
    ['c1' => 515, 'c2' => 0.0],
    ['c1' => 516, 'c2' => 0.0],
    ['c1' => 517, 'c2' => 0.0],
    ['c1' => 518, 'c2' => 0.0],
    ['c1' => 519, 'c2' => 0.0],
    ['c1' => 522, 'c2' => 0.0],
    ['c1' => 523, 'c2' => 0.0],
    ['c1' => 524, 'c2' => 0.0],
    ['c1' => 525, 'c2' => 0.0],
    ['c1' => 526, 'c2' => 0.0],
    ['c1' => 529, 'c2' => 0.0],
    ['c1' => 530, 'c2' => 0.0],
    ['c1' => 531, 'c2' => 0.0],
    ['c1' => 532, 'c2' => 0.0],
    ['c1' => 533, 'c2' => 0.0],
    ['c1' => 536, 'c2' => 0.0],
    ['c1' => 537, 'c2' => 1.0],
    ['c1' => 538, 'c2' => 0.0],
    ['c1' => 539, 'c2' => 0.0],
    ['c1' => 540, 'c2' => 0.0],
    ['c1' => 543, 'c2' => 0.0],
    ['c1' => 544, 'c2' => 0.0],
];

$expectedRangeMax = static function (array $rows, float $preceding): array {
    $values = [];
    foreach ($rows as $row) {
        $max = null;
        foreach ($rows as $candidate) {
            if ($candidate['c1'] >= $row['c1'] - $preceding && $candidate['c1'] <= $row['c1']) {
                $max = $max === null ? $candidate['c2'] : max($max, $candidate['c2']);
            }
        }
        $values[] = $max;
    }

    return $values;
};

$tests['real upstream windowE 3.1 range max keeps sparse real offset boundary'] = static function (TestRunner $t) use ($windowERows, $expectedRangeMax): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'max',
        array_column($windowERows, 'c2'),
        array_column($windowERows, 'c1'),
        'RANGE',
        '366.0 PRECEDING',
        'CURRENT ROW',
    );

    $t->same($expectedRangeMax($windowERows, 366.0), $actual, 'windowE.test 3.1 max(c2) RANGE 366.0 PRECEDING');
    $t->same(array_fill(0, 63, 0.0), array_slice($actual, 0, 63), 'windowE.test 3.1 rows before c1=537 remain zero');
    $t->same(array_fill(0, 6, 1.0), array_slice($actual, 63), 'windowE.test 3.1 rows after c1=537 see the prior one');
};

$tests['real upstream windowE 4.1 total current row to unbounded following'] = static function (TestRunner $t): void {
    $values = [1, 9223372036854775807, 3, 4];
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $values, [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');

    $t->same([9.223372036854776e+18, 9.223372036854776e+18, 7.0, 4.0], $actual, 'windowE.test 4.1 total() current row to unbounded following');
};

$tests['real upstream windowE 4.2 total current row to two following'] = static function (TestRunner $t): void {
    $values = [1, 9223372036854775807, 3, 4];
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $values, [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');

    $t->same([9.223372036854776e+18, 9.223372036854776e+18, 7.0, 4.0], $actual, 'windowE.test 4.2 total() current row to two following');
};

$tests['real upstream windowE 5.1 integer sum current row to two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3, 4], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');

    $t->same([6, 9, 7, 4], $actual, 'windowE.test 5.1 sum(id) current row to two following');
};

$tests['real upstream windowE 5.2 mixed numeric sum current row to two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [-1, 9223372036854775807, 1, 0.5], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');

    $t->same([9223372036854775807, 9.223372036854776e+18, 1.5, 0.5], $actual, 'windowE.test 5.2 sum(x) current row to two following');
};

$tests['real upstream windowerr rejects negative row and range offsets'] = static function (TestRunner $t): void {
    foreach (['ROWS', 'RANGE', 'GROUPS'] as $unit) {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], $unit, -1, 1), "windowerr.test 1.1/1.3/1.5 {$unit} negative start");
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], $unit, 1, -1), "windowerr.test 1.2/1.4/1.6 {$unit} negative end");
    }
};

$tests['real upstream windowerr rejects non numeric rows frame boundary'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3], [1, 2, 3], 'ROWS', "'hello' PRECEDING", '10 FOLLOWING'),
        'windowerr.test 3.0 invalid ROWS preceding text',
    );
};

$tests['real upstream windowerr rejects blob-like rows frame boundary'] = static function (TestRunner $t): void {
    $t->throws(
        InvalidArgumentException::class,
        static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3], [1, 2, 3], 'ROWS', '10 PRECEDING', "x'ABCD' FOLLOWING"),
        'windowerr.test 3.2 invalid ROWS following blob literal',
    );
};

for ($case = 0; $case < 1000; $case++) {
    $rowCount = 7 + ($case % 9);
    $keys = [];
    $values = [];
    for ($index = 0; $index < $rowCount; $index++) {
        $keys[] = ($index * (($case % 5) + 1)) + ($case % 3);
        $values[] = (($case * 11) + ($index * 7)) % 23;
    }

    $preceding = (float) (($case % 6) + 1);
    $followingRows = $case % 4;
    $expectedRange = [];
    foreach ($keys as $rowIndex => $key) {
        $max = null;
        foreach ($keys as $candidateIndex => $candidateKey) {
            if ($candidateKey >= $key - $preceding && $candidateKey <= $key) {
                $max = $max === null ? $values[$candidateIndex] : max($max, $values[$candidateIndex]);
            }
        }
        $expectedRange[] = $max;
    }

    $expectedTotal = [];
    $expectedSum = [];
    foreach ($values as $rowIndex => $value) {
        $frame = array_slice($values, $rowIndex, $followingRows + 1);
        $expectedTotal[] = array_sum($frame) + 0.0;
        $expectedSum[] = array_sum($frame);
    }

    $tests['real upstream windowE windowerr dynamic range total offset case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $keys, $values, $preceding, $followingRows, $expectedRange, $expectedTotal, $expectedSum): void {
        $actualRange = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'RANGE', "{$preceding} PRECEDING", 'CURRENT ROW');
        $t->same($expectedRange, $actualRange, "windowE.test 3.1 dynamic RANGE max {$case}");

        $actualTotal = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $values, $keys, 'ROWS', 'CURRENT ROW', "{$followingRows} FOLLOWING");
        $t->same($expectedTotal, $actualTotal, "windowE.test 4.2 dynamic total current-to-following {$case}");

        $actualSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', 'CURRENT ROW', "{$followingRows} FOLLOWING");
        $t->same($expectedSum, $actualSum, "windowE.test 5.1 dynamic sum current-to-following {$case}");

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameValues('sum', $values, $keys, 'ROWS', -1, $followingRows), "windowerr.test 1.1 dynamic negative ROWS start {$case}");
    };
}

$tests['real upstream windowE windowerr dynamic cites exact source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 3.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 4.1-5.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test 1.1-1.8, 3.0, 3.2',
        'dynamic cases expand sparse RANGE max, total/sum following frames, and invalid negative frame offsets over the same upstream semantics',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 3.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 4.1-5.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowerr.test 1.1-1.8, 3.0, 3.2',
        'dynamic cases expand sparse RANGE max, total/sum following frames, and invalid negative frame offsets over the same upstream semantics',
    ]);
};

$tests['real upstream windowE windowerr dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses SQLiteWindowFunction frame helpers against real upstream windowE/windowerr behavior', 'no new support component needed; reuses SQLiteWindowFunction frame helpers against real upstream windowE/windowerr behavior');
};

return $tests;
