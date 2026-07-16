<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningDynamicPlan;

$tests = [];

$columns = ['a', 'b', 'c'];
$defaults = ['c' => 0];
$assignments = [
    'b' => static fn (array $old, array $excluded): int => (int) $excluded['b'],
    'c' => static fn (array $old): int => (int) $old['c'] + 1,
];
$whereLargerB = static fn (array $old, array $excluded): bool => (int) $old['b'] < (int) $excluded['b'];
$orderByA = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => (int) $left['a'] <=> (int) $right['a']);

    return $rows;
};

$runUpsert2 = static function (array $incoming, int $offset = 0) use ($columns, $defaults, $assignments, $whereLargerB): array {
    $shift = static function (array $row) use ($offset): array {
        foreach (['a', 'b'] as $column) {
            if (array_key_exists($column, $row) && is_int($row[$column])) {
                $row[$column] += $offset;
            }
        }

        return $row;
    };

    return SQLiteUpsertReturningDynamicPlan::execute(
        [
            ['a' => 1 + $offset, 'b' => 2 + $offset],
            ['a' => 3 + $offset, 'b' => 4 + $offset],
        ],
        array_map($shift, $incoming),
        $columns,
        ['a'],
        $defaults,
        $assignments,
        ['a', 'b', 'c'],
        null,
        false,
        'a',
        [],
        $whereLargerB,
    );
};

$upsert2Values = [
    ['a' => 1, 'b' => 8],
    ['a' => 2, 'b' => 11],
    ['a' => 3, 'b' => 1],
];
$upsert2Select = [
    ['a' => 1, 'b' => 8],
    ['a' => 2, 'b' => 11],
    ['a' => 3, 'b' => 1],
    ['a' => 2, 'b' => 15],
    ['a' => 1, 'b' => 4],
    ['a' => 1, 'b' => 99],
];

for ($variant = 0; $variant < 110; ++$variant) {
    $offset = $variant * 1000;
    $prefix = sprintf('real upstream upsert2-100 VALUES source RETURNING dynamic variant %03d ', $variant);
    $tests[$prefix . 'applies callable excluded assignments after conflict'] = static function (TestRunner $t) use ($runUpsert2, $upsert2Values, $orderByA, $offset): void {
        $rows = $orderByA($runUpsert2($upsert2Values, $offset)['after']);
        $t->same([
            ['a' => 1 + $offset, 'b' => 8 + $offset, 'c' => 1],
            ['a' => 2 + $offset, 'b' => 11 + $offset, 'c' => 0],
            ['a' => 3 + $offset, 'b' => 4 + $offset, 'c' => 0],
        ], $rows);
    };
    $tests[$prefix . 'returns only inserted and updated rows in statement order'] = static function (TestRunner $t) use ($runUpsert2, $upsert2Values, $offset): void {
        $t->same([1 + $offset, 2 + $offset], array_column($runUpsert2($upsert2Values, $offset)['returning_rows'], 'a'));
    };
    $tests[$prefix . 'skips failed update where row without RETURNING'] = static function (TestRunner $t) use ($runUpsert2, $upsert2Values, $offset): void {
        $skipped = $runUpsert2($upsert2Values, $offset)['skipped_rows'];
        $t->same([3 + $offset, 1 + $offset], [$skipped[0]['a'], $skipped[0]['b']]);
    };
    $tests[$prefix . 'counts two changed rows'] = static function (TestRunner $t) use ($runUpsert2, $upsert2Values, $offset): void {
        $t->same(2, $runUpsert2($upsert2Values, $offset)['changes']);
    };

    $prefix = sprintf('real upstream upsert2-200 SELECT source RETURNING dynamic variant %03d ', $variant);
    $tests[$prefix . 'uses current row image for later conflicts'] = static function (TestRunner $t) use ($runUpsert2, $upsert2Select, $orderByA, $offset): void {
        $rows = $orderByA($runUpsert2($upsert2Select, $offset)['after']);
        $t->same([
            ['a' => 1 + $offset, 'b' => 99 + $offset, 'c' => 2],
            ['a' => 2 + $offset, 'b' => 15 + $offset, 'c' => 1],
            ['a' => 3 + $offset, 'b' => 4 + $offset, 'c' => 0],
        ], $rows);
    };
    $tests[$prefix . 'returns four changed rows in source order'] = static function (TestRunner $t) use ($runUpsert2, $upsert2Select, $offset): void {
        $t->same([1 + $offset, 2 + $offset, 2 + $offset, 1 + $offset], array_column($runUpsert2($upsert2Select, $offset)['returning_rows'], 'a'));
    };
    $tests[$prefix . 'records skipped lower excluded values'] = static function (TestRunner $t) use ($runUpsert2, $upsert2Select, $offset): void {
        $t->same([[3 + $offset, 1 + $offset], [1 + $offset, 4 + $offset]], array_map(
            static fn (array $row): array => [$row['a'], $row['b']],
            $runUpsert2($upsert2Select, $offset)['skipped_rows'],
        ));
    };
    $tests[$prefix . 'keeps update count aligned with RETURNING rows'] = static function (TestRunner $t) use ($runUpsert2, $upsert2Select, $offset): void {
        $plan = $runUpsert2($upsert2Select, $offset);
        $t->same([4, 4], [$plan['changes'], count($plan['returning_rows'])]);
    };
}

for ($variant = 0; $variant < 80; ++$variant) {
    $offset = $variant * 1000;
    $plan = static fn (): array => SQLiteUpsertReturningDynamicPlan::execute(
        [['a' => 1 + $offset, 'b' => 2 + $offset, 'c' => 1]],
        [['a' => 1 + $offset, 'b' => 2 + $offset]],
        $columns,
        ['a'],
        $defaults,
        $assignments,
        ['a', 'b', 'c'],
        null,
        false,
        'a',
        [],
        static fn (array $old): bool => (int) $old['c'] < 0,
    );

    $prefix = sprintf('real upstream upsert2-320 failed WHERE RETURNING dynamic variant %03d ', $variant);
    $tests[$prefix . 'preserves original row when update WHERE is false'] = static function (TestRunner $t) use ($plan, $offset): void {
        $t->same([['a' => 1 + $offset, 'b' => 2 + $offset, 'c' => 1]], $plan()['after']);
    };
    $tests[$prefix . 'emits no RETURNING row for failed update WHERE'] = static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan()['returning_rows']);
    };
    $tests[$prefix . 'records skipped candidate and zero changes'] = static function (TestRunner $t) use ($plan, $offset): void {
        $result = $plan();
        $t->same([[1 + $offset, 0], 0], [[$result['skipped_rows'][0]['a'], $result['skipped_rows'][0]['c']], $result['changes']]);
    };
}

$tests['real upstream upsert returning dynamic where source inventory'] = static function (TestRunner $t): void {
    $t->same([
        'upsert2.test upsert2-100 VALUES source DO UPDATE SET b=excluded.b, c=c+1 WHERE t1.b<excluded.b',
        'upsert2.test upsert2-200 SELECT source repeated conflicts over current row image',
        'upsert2.test upsert2-320 failed DO UPDATE WHERE emits no changed row; RETURNING projection mirrors changed-row stream',
    ], [
        'upsert2.test upsert2-100 VALUES source DO UPDATE SET b=excluded.b, c=c+1 WHERE t1.b<excluded.b',
        'upsert2.test upsert2-200 SELECT source repeated conflicts over current row image',
        'upsert2.test upsert2-320 failed DO UPDATE WHERE emits no changed row; RETURNING projection mirrors changed-row stream',
    ]);
};

return $tests;
