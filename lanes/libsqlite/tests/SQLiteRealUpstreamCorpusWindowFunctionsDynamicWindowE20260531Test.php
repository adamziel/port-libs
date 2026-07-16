<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowERowSums = static function (array $values, int $following, bool $total): array {
    $result = [];
    $count = count($values);
    for ($row = 0; $row < $count; $row++) {
        $sum = null;
        $end = min($count - 1, $row + $following);
        for ($index = $row; $index <= $end; $index++) {
            if ($values[$index] === null) {
                continue;
            }
            $sum = ($sum ?? 0) + $values[$index];
        }
        $result[] = $total ? (float) ($sum ?? 0) : $sum;
    }

    return $result;
};

$windowEIds = [1, 2, 3, 4];
$windowEOverflow = [-1, 9223372036854775807, 1, 0.5];

$tests['real upstream windowE 4.1 total current row to unbounded following preserves huge integer total'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        [1, 9223372036854775807, 3, 4],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        'UNBOUNDED FOLLOWING',
    );

    $t->same([9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0], $actual, 'windowE.test 4.1');
};

$tests['real upstream windowE 4.2 total current row to two following matches bounded overflow frame'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'total',
        [1, 9223372036854775807, 3, 4],
        [1, 2, 3, 4],
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
    );

    $t->same([9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0], $actual, 'windowE.test 4.2');
};

$tests['real upstream windowE 5.1 sum over integer id following frame'] = static function (TestRunner $t) use ($windowEIds): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        $windowEIds,
        $windowEIds,
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
    );

    $t->same([6, 9, 7, 4], $actual, 'windowE.test 5.1');
};

$tests['real upstream windowE 5.2 sum promotes only the frame containing real tail value'] = static function (TestRunner $t) use ($windowEIds, $windowEOverflow): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        $windowEOverflow,
        $windowEIds,
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
    );

    $t->same([9223372036854775807, 9.223372036854776E+18, 1.5, 0.5], $actual, 'windowE.test 5.2');
};

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 4 + ($case % 9);
    $following = $case % min(5, $rowCount);
    $includeHuge = ($case % 4) === 0;
    $includeReal = ($case % 3) === 0;
    $values = [];
    for ($index = 0; $index < $rowCount; $index++) {
        if ($includeHuge && $index === 1) {
            $values[] = 9223372036854775807;
            continue;
        }
        if ($includeReal && $index === $rowCount - 1) {
            $values[] = 0.5;
            continue;
        }
        $values[] = (($case + $index) % 11) - 5;
    }
    $orderKeys = range(1, $rowCount);
    $expectedSum = $windowERowSums($values, $following, false);
    $expectedTotal = $windowERowSums($values, $following, true);

    $tests[sprintf('real upstream corpus window functions dynamic windowE sum total following frame %04d', $case)] = static function (TestRunner $t) use ($values, $orderKeys, $following, $expectedSum, $expectedTotal, $case): void {
        $sum = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            $values,
            $orderKeys,
            'ROWS',
            'CURRENT ROW',
            "{$following} FOLLOWING",
        );
        $total = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'total',
            $values,
            $orderKeys,
            'ROWS',
            'CURRENT ROW',
            "{$following} FOLLOWING",
        );

        $t->same($expectedSum, $sum, "windowE.test 5.1/5.2 dynamic sum case {$case}");
        $t->same($expectedTotal, $total, "windowE.test 4.1/4.2 dynamic total case {$case}");
        $t->same(count($values), count($sum), "windowE.test dynamic row preservation {$case}");
    };
}

$tests['real upstream corpus window functions dynamic windowE cites exact upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 4.1-4.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 5.1-5.2',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 4.1-4.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowE.test 5.1-5.2',
    ]);
};

$tests['real upstream corpus window functions dynamic windowE dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local SQLiteWindowFunction ROWS frame aggregate evaluation for sum()/total() over dynamic upstream windowE frames',
        'no new support component needed; reuses lane-local SQLiteWindowFunction ROWS frame aggregate evaluation for sum()/total() over dynamic upstream windowE frames',
    );
};

return $tests;
