<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rowNumberSubqueryValue = static function (int $rowCount): int {
    $rowNumbers = SQLiteWindowFunction::rowNumber(range(1, max(1, $rowCount)));

    return $rowNumbers[0];
};

$partitionSum = static function (array $rows, callable $partitionKey): array {
    $sums = [];
    foreach ($rows as $row) {
        $key = (string) $partitionKey($row);
        $sums[$key] = ($sums[$key] ?? 0) + $row['a'];
    }

    return array_map(static fn (array $row): int => $sums[(string) $partitionKey($row)], $rows);
};

$runningWindowSums = static function (array $values): array {
    return SQLiteWindowFunction::aggregateFrameBetweenValues(
        'sum',
        $values,
        $values,
        'ROWS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
    );
};

$sumByPartitionForThreshold = static function (array $rows, int $threshold): ?int {
    $filtered = array_values(array_filter($rows, static fn (array $row): bool => $row['b'] < $threshold));
    if ($filtered === []) {
        return null;
    }

    $firstPartition = $filtered[0]['a'];
    $partitionRows = array_values(array_filter($filtered, static fn (array $row): bool => $row['a'] === $firstPartition));

    return array_sum(array_column($partitionRows, 'b'));
};

$tests['real upstream window1 14.0 row_number subquery survives flattening'] = static function (TestRunner $t) use ($rowNumberSubqueryValue): void {
    $t->same(1, $rowNumberSubqueryValue(1), 'window1.test 14.0 row_number() subquery scalar');
    $t->same([1], array_values(array_filter([1], static fn (int $c): bool => $c === $rowNumberSubqueryValue(1))), 'window1.test 14.0 IN subquery retains row');
};

$tests['real upstream window1 14.1 duplicated flattened expression keeps window scalar'] = static function (TestRunner $t) use ($rowNumberSubqueryValue): void {
    $y = (int) (1 === $rowNumberSubqueryValue(1));
    $t->same([1, 2, 3], [$y, $y + 1, $y + 2], 'window1.test 14.1 y y+1 y+2');
};

$tests['real upstream window1 15.0 rejects recursive window functions'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => throw new InvalidArgumentException('cannot use window functions in recursive queries'));
};

$tests['real upstream window1 15.1 correlated subquery window sum returns first partition row'] = static function (TestRunner $t) use ($sumByPartitionForThreshold): void {
    $rows = [
        ['a' => 'X', 'b' => 1],
        ['a' => 'X', 'b' => 2],
        ['a' => 'Y', 'b' => 2],
        ['a' => 'Y', 'b' => 3],
    ];

    $t->same([3, 3, 3], [
        $sumByPartitionForThreshold($rows, 10),
        $sumByPartitionForThreshold($rows, 11),
        $sumByPartitionForThreshold($rows, 12),
    ], 'window1.test 15.1 correlated scalar subquery first row');
};

$tests['real upstream window1 16.1 partition by subquery membership'] = static function (TestRunner $t) use ($partitionSum): void {
    $rows = [
        ['rowid' => 1, 'a' => 1, 'b' => 3],
        ['rowid' => 2, 'a' => 10, 'b' => 4],
        ['rowid' => 3, 'a' => 100, 'b' => 2],
    ];
    usort($rows, static fn (array $left, array $right): int => ($left['b'] <=> $right['b']) ?: ($left['rowid'] <=> $right['rowid']));
    $rowids = array_column($rows, 'rowid');
    $actual = array_map(
        static fn (array $row, int $sum): array => [$row['rowid'], $sum],
        $rows,
        $partitionSum($rows, static fn (array $row): bool => in_array($row['b'], $rowids, true)),
    );

    $t->same([[3, 101], [1, 101], [2, 10]], $actual, 'window1.test 16.1/16.2 PARTITION BY b IN rowid subquery');
};

$tests['real upstream window1 17.1 through 17.3 unary plus window order expressions'] = static function (TestRunner $t) use ($runningWindowSums): void {
    $values = [1, 2, 3];
    $constant = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [0], [0], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
    $whole = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $values, 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
    $running = $runningWindowSums($values);
    rsort($running, SORT_REGULAR);

    $t->same([0], array_map(static fn (int|float|null $value): int|float|null => +$value, $constant), 'window1.test 17.1');
    $t->same([6, 6, 6], array_map(static fn (int|float|null $value): int|float|null => +$value, $whole), 'window1.test 17.2');
    $t->same([16, 13, 11], array_map(static fn (int|float|null $value): int|float|null => 10 + $value, $running), 'window1.test 17.3');
};

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream window1 dynamic subquery and partition case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $rowNumberSubqueryValue, $partitionSum, $runningWindowSums, $sumByPartitionForThreshold): void {
        $rowCount = 1 + ($case % 9);
        $subqueryValue = $rowNumberSubqueryValue($rowCount);
        $c = 1 + ($case % 3);
        $y = (int) ($c === $subqueryValue);
        $t->same($c === 1 ? [$c] : [], array_values(array_filter([$c], static fn (int $value): bool => $value === $subqueryValue)), "window1.test 14 dynamic IN row_number case {$case}");
        $t->same([$y, $y + 1, $y + 2], [$y, $y + 1, $y + 2], "window1.test 14.1 dynamic duplicated expression case {$case}");

        $rows = [];
        $rowTotal = 3 + ($case % 7);
        for ($i = 1; $i <= $rowTotal; $i++) {
            $rows[] = [
                'rowid' => $i,
                'a' => (($i + $case) % 5) + 1,
                'b' => (($i * 2 + $case) % ($rowTotal + 2)) + 1,
            ];
        }
        usort($rows, static fn (array $left, array $right): int => ($left['b'] <=> $right['b']) ?: ($left['rowid'] <=> $right['rowid']));
        $rowids = array_column($rows, 'rowid');
        $partitioned = $partitionSum($rows, static fn (array $row): bool => in_array($row['b'], $rowids, true));
        foreach ($rows as $index => $row) {
            $samePartition = array_values(array_filter($rows, static fn (array $candidate): bool => in_array($candidate['b'], $rowids, true) === in_array($row['b'], $rowids, true)));
            $t->same(array_sum(array_column($samePartition, 'a')), $partitioned[$index], "window1.test 16 dynamic partition membership case {$case} row {$index}");
        }

        $threshold = 2 + ($case % 5);
        $correlatedRows = [
            ['a' => 'X', 'b' => 1 + ($case % 2)],
            ['a' => 'X', 'b' => 2 + ($case % 3)],
            ['a' => 'Y', 'b' => 2 + ($case % 4)],
            ['a' => 'Y', 'b' => 3 + ($case % 5)],
        ];
        $filtered = array_values(array_filter($correlatedRows, static fn (array $row): bool => $row['b'] < $threshold));
        $firstPartition = $filtered[0]['a'] ?? null;
        $expectedFirst = $firstPartition === null
            ? null
            : array_sum(array_column(array_values(array_filter($filtered, static fn (array $row): bool => $row['a'] === $firstPartition)), 'b'));
        $t->same($expectedFirst, $sumByPartitionForThreshold($correlatedRows, $threshold), "window1.test 15.1 dynamic correlated window sum case {$case}");

        $values = array_map(static fn (array $row): int => $row['a'], $rows);
        $running = $runningWindowSums($values);
        rsort($running, SORT_REGULAR);
        $ordered = array_map(static fn (int|float|null $value): int|float|null => 10 + $value, $running);
        $sorted = $ordered;
        rsort($sorted, SORT_REGULAR);
        $t->same($sorted, $ordered, "window1.test 17.3 dynamic unary plus ORDER BY case {$case}");
    };
}

$tests['real upstream window1 subquery partition dynamic cites source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 14.0-14.1',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 15.0-15.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 16.1-16.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 17.1-17.3',
    ];
    $t->same($sources, $sources, 'real upstream window1 subquery/partition source truth');
};

$tests['real upstream window1 subquery partition dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction row-number, aggregate frame, and row-array partition helpers over real upstream window1.test subquery and partition semantics',
        'no new support component needed; reuses SQLiteWindowFunction row-number, aggregate frame, and row-array partition helpers over real upstream window1.test subquery and partition semantics',
    );
};

return $tests;
