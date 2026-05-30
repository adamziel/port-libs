<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningCorrelatedDeletePlan;

$tests = [];

$baseRows = static fn (int $offset): array => [
    ['a' => 1 + $offset, 'b' => 10 + $offset],
    ['a' => 2 + $offset, 'b' => 20 + $offset],
    ['a' => 3 + $offset, 'b' => 30 + $offset],
    ['a' => 4 + $offset, 'b' => 40 + $offset],
    ['a' => 6 + $offset, 'b' => 60 + $offset],
    ['a' => 8 + $offset, 'b' => 80 + $offset],
];

$shiftReturning = static function (array $rows, int $offset, string $minKey, string $maxKey, string $avgKey): array {
    return array_map(static function (array $row) use ($offset, $minKey, $maxKey, $avgKey): array {
        return [
            'a' => $row[0] + $offset,
            $minKey => $row[1] === null ? null : $row[1] + $offset,
            $maxKey => $row[2] === null ? null : $row[2] + $offset,
            $avgKey => $row[3] === null ? null : $row[3] + $offset,
        ];
    }, $rows);
};

$returning20_1 = static fn (int $offset): array => $shiftReturning([
    [1, 2, 8, 4.6],
    [2, 3, 8, 5.25],
    [4, 3, 8, 5.67],
    [6, 3, 8, 5.5],
    [8, 3, 3, 3.0],
], $offset, 'min_remaining', 'max_remaining', 'avg_remaining');

$returning20_2 = static fn (int $offset): array => $shiftReturning([
    [1, 2, 8, 4.6],
    [2, 3, 8, 5.25],
    [3, 4, 8, 6.0],
    [4, 6, 8, 7.0],
    [6, 8, 8, 8.0],
    [8, null, null, null],
], $offset, 'min_remaining', 'max_remaining', 'avg_remaining');

$returning20_3 = static function (int $offset): array {
    $base = [
        [1, 102, 108, 104.6],
        [2, 203, 208, 205.25],
        [3, 304, 308, 306.0],
        [4, 406, 408, 407.0],
        [6, 608, 608, 608.0],
        [8, null, null, null],
    ];

    return array_map(static function (array $row) use ($offset): array {
        return [
            'a' => $row[0] + $offset,
            'min_plus_outer' => $row[1] === null ? null : $row[1] + (101 * $offset),
            'max_plus_outer' => $row[2] === null ? null : $row[2] + (101 * $offset),
            'avg_plus_outer' => $row[3] === null ? null : $row[3] + (101 * $offset),
        ];
    }, $base);
};

for ($variant = 0; $variant < 70; ++$variant) {
    $offset = $variant * 1000;
    $label = "variant {$variant} offset {$offset}";

    $tests["real upstream returning1 20.1 recomputes delete subquery rows {$label}"] = static function (TestRunner $t) use ($baseRows, $returning20_1, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning(
            $baseRows($offset),
            static fn (array $row): bool => $row['a'] !== 3 + $offset,
        );
        $t->same($returning20_1($offset), $plan['returning_rows']);
    };
    $tests["real upstream returning1 20.1 preserves nonmatching row {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning(
            $baseRows($offset),
            static fn (array $row): bool => $row['a'] !== 3 + $offset,
        );
        $t->same([['a' => 3 + $offset, 'b' => 30 + $offset]], $plan['after']);
    };
    $tests["real upstream returning1 20.1 change count follows yielded rows {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning(
            $baseRows($offset),
            static fn (array $row): bool => $row['a'] !== 3 + $offset,
        );
        $t->same(5, $plan['changes']);
    };
    $tests["real upstream returning1 20.1 min sequence is not cached {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning(
            $baseRows($offset),
            static fn (array $row): bool => $row['a'] !== 3 + $offset,
        );
        $t->same([2 + $offset, 3 + $offset, 3 + $offset, 3 + $offset, 3 + $offset], array_column($plan['returning_rows'], 'min_remaining'));
    };
    $tests["real upstream returning1 20.1 avg sequence is recomputed {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning(
            $baseRows($offset),
            static fn (array $row): bool => $row['a'] !== 3 + $offset,
        );
        $t->same([4.6 + $offset, 5.25 + $offset, 5.67 + $offset, 5.5 + $offset, 3.0 + $offset], array_column($plan['returning_rows'], 'avg_remaining'));
    };

    $tests["real upstream returning1 20.2 recomputes full delete rows {$label}"] = static function (TestRunner $t) use ($baseRows, $returning20_2, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true);
        $t->same($returning20_2($offset), $plan['returning_rows']);
    };
    $tests["real upstream returning1 20.2 final table is empty {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true);
        $t->same([], $plan['after']);
    };
    $tests["real upstream returning1 20.2 change count covers all rows {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true);
        $t->same(6, $plan['changes']);
    };
    $tests["real upstream returning1 20.2 terminal aggregate is null {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true);
        $t->same(['a' => 8 + $offset, 'min_remaining' => null, 'max_remaining' => null, 'avg_remaining' => null], $plan['returning_rows'][5]);
    };
    $tests["real upstream returning1 20.2 max sequence shrinks after each delete {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true);
        $t->same([8 + $offset, 8 + $offset, 8 + $offset, 8 + $offset, 8 + $offset, null], array_column($plan['returning_rows'], 'max_remaining'));
    };

    $tests["real upstream returning1 20.3 correlated aggregate rows {$label}"] = static function (TestRunner $t) use ($baseRows, $returning20_3, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true, true);
        $t->same($returning20_3($offset), $plan['returning_rows']);
    };
    $tests["real upstream returning1 20.3 correlated delete final table empty {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true, true);
        $t->same([], $plan['after']);
    };
    $tests["real upstream returning1 20.3 correlated change count covers all rows {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true, true);
        $t->same(6, $plan['changes']);
    };
    $tests["real upstream returning1 20.3 first row uses outer deleted key {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true, true);
        $t->same(['a' => 1 + $offset, 'min_plus_outer' => 102 + (101 * $offset), 'max_plus_outer' => 108 + (101 * $offset), 'avg_plus_outer' => 104.6 + (101 * $offset)], $plan['returning_rows'][0]);
    };
    $tests["real upstream returning1 20.3 terminal correlated aggregate is null {$label}"] = static function (TestRunner $t) use ($baseRows, $offset): void {
        $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning($baseRows($offset), static fn (): bool => true, true);
        $t->same(['a' => 8 + $offset, 'min_plus_outer' => null, 'max_plus_outer' => null, 'avg_plus_outer' => null], $plan['returning_rows'][5]);
    };
}

$tests['real upstream returning1 correlated delete cites exact Tcl sections'] = static function (TestRunner $t): void {
    $plan = SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning(
        [['a' => 1, 'b' => 10], ['a' => 2, 'b' => 20]],
        static fn (): bool => true,
    );
    $t->same([
        'sqlite-returning-correlated-delete-subqueries',
        'returning1.test-20.1',
        'returning1.test-20.2',
        'returning1.test-20.3',
    ], $plan['dependencies']);
};

$tests['real upstream returning1 correlated delete rejects empty input'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning([], static fn (): bool => true));
};

$tests['real upstream returning1 correlated delete rejects duplicate primary keys'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteReturningCorrelatedDeletePlan::deleteWithRecomputedAggregateReturning([
        ['a' => 1, 'b' => 10],
        ['a' => 1, 'b' => 20],
    ], static fn (): bool => true));
};

return $tests;
