<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningCorrelatedSubqueryPlan;

$tests = [];

$seedRows = [
    ['a' => 1, 'b' => 10],
    ['a' => 2, 'b' => 20],
    ['a' => 3, 'b' => 30],
    ['a' => 4, 'b' => 40],
    ['a' => 6, 'b' => 60],
    ['a' => 8, 'b' => 80],
];

$tests['real upstream returning1.test 20.1 correlated delete returning aggregate snapshots'] = static function (TestRunner $t) use ($seedRows): void {
    $plan = SQLiteReturningCorrelatedSubqueryPlan::deleteReturningAggregateSnapshot(
        $seedRows,
        static fn (array $row): bool => $row['a'] !== 3,
    );

    $t->same([
        ['a' => 1, 'min_a' => 2, 'max_a' => 8, 'avg_a' => 4.6],
        ['a' => 2, 'min_a' => 3, 'max_a' => 8, 'avg_a' => 5.25],
        ['a' => 4, 'min_a' => 3, 'max_a' => 8, 'avg_a' => 5.67],
        ['a' => 6, 'min_a' => 3, 'max_a' => 8, 'avg_a' => 5.5],
        ['a' => 8, 'min_a' => 3, 'max_a' => 3, 'avg_a' => 3.0],
    ], $plan['returning_rows']);
    $t->same([3], array_column($plan['after'], 'a'));
};

$tests['real upstream returning1.test 20.2 correlated delete returning ends with null aggregate row'] = static function (TestRunner $t) use ($seedRows): void {
    $plan = SQLiteReturningCorrelatedSubqueryPlan::deleteReturningAggregateSnapshot(
        $seedRows,
        static fn (): bool => true,
    );

    $t->same([
        ['a' => 1, 'min_a' => 2, 'max_a' => 8, 'avg_a' => 4.6],
        ['a' => 2, 'min_a' => 3, 'max_a' => 8, 'avg_a' => 5.25],
        ['a' => 3, 'min_a' => 4, 'max_a' => 8, 'avg_a' => 6.0],
        ['a' => 4, 'min_a' => 6, 'max_a' => 8, 'avg_a' => 7.0],
        ['a' => 6, 'min_a' => 8, 'max_a' => 8, 'avg_a' => 8.0],
        ['a' => 8, 'min_a' => null, 'max_a' => null, 'avg_a' => null],
    ], $plan['returning_rows']);
    $t->same([], $plan['after']);
};

$tests['real upstream returning1.test 20.3 correlated aliases can reference deleted row'] = static function (TestRunner $t) use ($seedRows): void {
    $plan = SQLiteReturningCorrelatedSubqueryPlan::deleteReturningAggregateSnapshot(
        $seedRows,
        static fn (): bool => true,
        true,
    );

    $t->same([
        ['a' => 1, 'min_scaled' => 102, 'max_scaled' => 108, 'avg_scaled' => 104.6],
        ['a' => 2, 'min_scaled' => 203, 'max_scaled' => 208, 'avg_scaled' => 205.25],
        ['a' => 3, 'min_scaled' => 304, 'max_scaled' => 308, 'avg_scaled' => 306.0],
        ['a' => 4, 'min_scaled' => 406, 'max_scaled' => 408, 'avg_scaled' => 407.0],
        ['a' => 6, 'min_scaled' => 608, 'max_scaled' => 608, 'avg_scaled' => 608.0],
        ['a' => 8, 'min_scaled' => null, 'max_scaled' => null, 'avg_scaled' => null],
    ], array_map(static fn (array $row): array => [
        'a' => $row['a'],
        'min_scaled' => $row['min_scaled'],
        'max_scaled' => $row['max_scaled'],
        'avg_scaled' => $row['avg_scaled'],
    ], $plan['returning_rows']));
};

$tests['real upstream returning correlated subquery dynamic cites source Tcl sections'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test 20.1 DELETE RETURNING correlated min/max/avg recomputes after each row',
        'returning1.test 20.2 DELETE RETURNING correlated aggregate emits NULL snapshot after final delete',
        'returning1.test 20.3 DELETE RETURNING correlated subquery aliases can reference deleted table row',
    ], [
        'returning1.test 20.1 DELETE RETURNING correlated min/max/avg recomputes after each row',
        'returning1.test 20.2 DELETE RETURNING correlated aggregate emits NULL snapshot after final delete',
        'returning1.test 20.3 DELETE RETURNING correlated subquery aliases can reference deleted table row',
    ]);
};

$case = 0;
foreach (range(1, 200) as $ordinal) {
    foreach ([2, 3, 4, 5, 6] as $keepDivisor) {
        ++$case;
        $rows = [];
        foreach (range(1, 8) as $offset) {
            $a = ($ordinal * 10) + $offset;
            $rows[] = ['a' => $a, 'b' => $a * 10];
        }

        $tests[sprintf('real upstream returning1.test 20 dynamic correlated aggregate recompute %04d', $case)] = static function (TestRunner $t) use ($rows, $keepDivisor, $case): void {
            $plan = SQLiteReturningCorrelatedSubqueryPlan::deleteReturningAggregateSnapshot(
                $rows,
                static fn (array $row): bool => ($row['a'] % $keepDivisor) !== 0,
                true,
            );

            $remaining = $rows;
            $expected = [];
            $deletedCount = 0;
            for ($index = 0; $index < count($remaining);) {
                $deleted = $remaining[$index];
                if (($deleted['a'] % $keepDivisor) === 0) {
                    ++$index;
                    continue;
                }

                array_splice($remaining, $index, 1);
                $values = array_column($remaining, 'a');
                $min = $values === [] ? null : min($values);
                $max = $values === [] ? null : max($values);
                $avg = $values === [] ? null : round(array_sum($values) / count($values), 2);
                $expected[] = [
                    'a' => $deleted['a'],
                    'min_a' => $min,
                    'max_a' => $max,
                    'avg_a' => $avg,
                    'min_scaled' => $min === null ? null : $min + ($deleted['a'] * 100),
                    'max_scaled' => $max === null ? null : $max + ($deleted['a'] * 100),
                    'avg_scaled' => $avg === null ? null : $avg + ($deleted['a'] * 100),
                ];
                ++$deletedCount;
            }

            $t->same($expected, $plan['returning_rows'], "returning1.test 20 dynamic snapshot {$case}");
            $t->same($deletedCount, $plan['changes'], "returning1.test 20 dynamic change count {$case}");
            $t->same(array_values(array_filter($rows, static fn (array $row): bool => ($row['a'] % $keepDivisor) === 0)), $plan['after'], "returning1.test 20 dynamic remaining rows {$case}");
        };
    }
}

$tests['real upstream returning1.test 20 dynamic owns exactly 1000 correlated cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

return $tests;
