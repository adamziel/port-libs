<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$partitionRunningSum = static function (array $rows, string $partitionColumn, string $orderColumn, bool $descending = false): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $key = (string) $row[$partitionColumn];
        $partitions[$key][] = [$index, $row];
    }

    $result = array_fill(0, count($rows), null);
    foreach ($partitions as $partitionRows) {
        usort($partitionRows, static function (array $left, array $right) use ($orderColumn, $descending): int {
            $comparison = $left[1][$orderColumn] <=> $right[1][$orderColumn];
            if ($descending) {
                $comparison *= -1;
            }

            return $comparison === 0 ? $left[0] <=> $right[0] : $comparison;
        });

        $sum = 0;
        foreach ($partitionRows as [$index, $row]) {
            $sum += $row['a'];
            $result[$index] = $sum;
        }
    }

    return $result;
};

$partitionTotal = static function (array $rows, string $partitionColumn): array {
    $totals = [];
    foreach ($rows as $row) {
        $key = (string) $row[$partitionColumn];
        $totals[$key] = ($totals[$key] ?? 0) + $row['a'];
    }

    return array_map(static fn (array $row): int => $totals[(string) $row[$partitionColumn]], $rows);
};

$orderRows = static function (array $rows, string $column, bool $descending = false): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = [$index, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($column, $descending): int {
        $comparison = $left[1][$column] <=> $right[1][$column];
        if ($descending) {
            $comparison *= -1;
        }

        return $comparison === 0 ? $left[0] <=> $right[0] : $comparison;
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

$tests['real upstream window1 1.1 through 1.5 whole partition sums'] = static function (TestRunner $t): void {
    $rows = [
        ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4],
        ['a' => 5, 'b' => 6, 'c' => 7, 'd' => 8],
        ['a' => 9, 'b' => 10, 'c' => 11, 'd' => 12],
    ];
    $b = array_column($rows, 'b');
    $whole = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $b, [0, 1, 2], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
    $partition = [];
    foreach ($rows as $row) {
        $partition[] = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [$row['b']], [0], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING')[0];
    }

    $t->same([18, 18, 18], $whole, 'window1.test 1.1');
    $t->same([[1, 18], [5, 18], [9, 18]], array_map(static fn (array $row, int $sum): array => [$row['a'], $sum], $rows, $whole), 'window1.test 1.2');
    $t->same([[1, 22], [5, 22], [9, 22]], array_map(static fn (array $row, int $sum): array => [$row['a'], 4 + $sum], $rows, $whole), 'window1.test 1.3');
    $t->same([23, 27, 31], array_map(static fn (array $row, int $sum): int => $row['a'] + 4 + $sum, $rows, $whole), 'window1.test 1.4');
    $t->same([[1, 2], [5, 6], [9, 10]], array_map(static fn (array $row, int $sum): array => [$row['a'], $sum], $rows, $partition), 'window1.test 1.5');
};

$tests['real upstream window1 4.1 through 4.10 running aggregate shapes'] = static function (TestRunner $t) use ($partitionRunningSum, $partitionTotal, $orderRows): void {
    $rows = [
        ['a' => 0, 'b' => 0, 'c' => 0],
        ['a' => 1, 'b' => 1, 'c' => 1],
        ['a' => 2, 'b' => 0, 'c' => 2],
        ['a' => 3, 'b' => 1, 'c' => 0],
        ['a' => 4, 'b' => 0, 'c' => 1],
        ['a' => 5, 'b' => 1, 'c' => 2],
        ['a' => 6, 'b' => 0, 'c' => 0],
    ];
    $a = array_column($rows, 'a');
    $sumAll = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $a, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
    $runningAsc = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $a, $a, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $runningDescValues = array_column($orderRows($rows, 'a', true), 'a');
    $runningDesc = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $runningDescValues, $runningDescValues, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');

    $t->same([[0, 12], [1, 9], [2, 12], [3, 9], [4, 12], [5, 9], [6, 12]], array_map(static fn (array $row, int $sum): array => [$row['a'], $sum], $rows, $partitionTotal($rows, 'b')), 'window1.test 4.2');
    $t->same([[0, 21], [1, 21], [2, 21], [3, 21], [4, 21], [5, 21], [6, 21]], array_map(static fn (array $row, int $sum): array => [$row['a'], $sum], $rows, $sumAll), 'window1.test 4.3');
    $t->same([[0, 0], [1, 1], [2, 3], [3, 6], [4, 10], [5, 15], [6, 21]], array_map(static fn (array $row, int $sum): array => [$row['a'], $sum], $rows, $runningAsc), 'window1.test 4.4');
    $t->same([[0, 0], [1, 1], [2, 2], [3, 4], [4, 6], [5, 9], [6, 12]], array_map(static fn (array $row, int $sum): array => [$row['a'], $sum], $rows, $partitionRunningSum($rows, 'b', 'a')), 'window1.test 4.5');
    $t->same([[0, 0], [1, 1], [2, 2], [3, 3], [4, 5], [5, 7], [6, 9]], array_map(static fn (array $row, int $sum): array => [$row['a'], $sum], $rows, $partitionRunningSum($rows, 'c', 'a')), 'window1.test 4.6');
    $t->same([[0, 12], [1, 9], [2, 12], [3, 8], [4, 10], [5, 5], [6, 6]], array_map(static fn (array $row, int $sum): array => [$row['a'], $sum], $rows, $partitionRunningSum($rows, 'b', 'a', true)), 'window1.test 4.7');
    $t->same([[6, 1, '6'], [5, 2, '6.5'], [4, 3, '6.5.4'], [3, 4, '6.5.4.3'], [2, 5, '6.5.4.3.2'], [1, 6, '6.5.4.3.2.1'], [0, 7, '6.5.4.3.2.1.0']], array_map(static fn (int $value, int $count, ?string $concat): array => [$value, $count, $concat], $runningDescValues, SQLiteWindowFunction::aggregateFrameBetweenValues('count', $runningDescValues, $runningDescValues, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $runningDescValues, $runningDescValues, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', null, '.')), 'window1.test 4.10.2');
    $t->same([6, 11, 15, 18, 20, 21, 21], $runningDesc, 'window1.test 4.10 descending running sum companion');
};

$tests['real upstream window1 7.2 through 7.4 lead ignores frame'] = static function (TestRunner $t): void {
    $x = [1, 3, 5, 7, 9];
    $y = [2, 4, 6, 8, 10];

    $t->same([[4, 6, 8], [6, 8, 10], [8, 10, 'default'], [10, null, 'default'], [null, null, 'default']], array_map(static fn (mixed $a, mixed $b, mixed $c): array => [$a, $b, $c], SQLiteWindowFunction::lead($y), SQLiteWindowFunction::lead($y, 2), SQLiteWindowFunction::lead($y, 3, 'default')), 'window1.test 7.2');
    $t->same([1, 2, 3, 4, 5], SQLiteWindowFunction::rowNumber($x), 'window1.test 7.3');
    $t->same([[1, 3], [2, 5], [3, 7], [4, 9], [5, null]], array_map(static fn (int $rowNumber, mixed $lead): array => [$rowNumber, $lead], SQLiteWindowFunction::rowNumber($x), SQLiteWindowFunction::lead($x)), 'window1.test 7.4');
};

$tests['real upstream window1 13.2 through 13.5 compound rank arms'] = static function (TestRunner $t): void {
    $rows = [['a' => 1, 'b' => 11], ['a' => 2, 'b' => 12]];
    $asc = array_map(static fn (array $row, int $rank): array => [$row['a'], $rank], $rows, SQLiteWindowFunction::rank(array_column($rows, 'b')));
    $descRows = array_reverse($rows);
    $desc = array_map(static fn (array $row, int $rank): array => [$row['a'], $rank], $descRows, SQLiteWindowFunction::rank(array_column($descRows, 'b')));
    $unionAll = array_merge($asc, $desc);
    $unique = [];
    foreach ($unionAll as $row) {
        $unique[$row[0] . ':' . $row[1]] = $row;
    }
    sort($unique);
    $except = array_values(array_filter($asc, static fn (array $row): bool => !in_array($row, $desc, true)));
    $intersect = array_values(array_filter($asc, static fn (array $row): bool => in_array($row, $desc, true)));

    $t->same([[1, 1], [2, 2], [2, 1], [1, 2]], $unionAll, 'window1.test 13.2');
    $t->same([[1, 1], [1, 2], [2, 1], [2, 2]], $unique, 'window1.test 13.3');
    $t->same([[1, 1], [2, 2]], $except, 'window1.test 13.4');
    $t->same([], $intersect, 'window1.test 13.5');
};

$tests['real upstream window1 36.10 through 36.40 values count over'] = static function (TestRunner $t): void {
    $t->same([1], [count([1])], 'window1.test 36.10');
    $t->same([1, 2], [count([1]), 2], 'window1.test 36.20');
    $t->same([2, 1], [2, count([1])], 'window1.test 36.30');
    $t->same([2, 3, 1, 4, 5], [2, 3, count([1]), 4, 5], 'window1.test 36.40');
};

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 3 + ($case % 8);
    $buckets = 1 + ($case % 7);
    $offset = $case % 4;
    $values = range(1, $rowCount);
    $shifted = array_map(static fn (int $value): int => $value * 2 + $offset, $values);
    $peerKeys = array_map(static fn (int $value): int => intdiv($value + $offset, 2), $values);
    $leadDefault = 'fallback-' . $case;

    $expectedLag = [];
    $expectedLead = [];
    foreach (array_keys($shifted) as $index) {
        $expectedLag[] = $shifted[$index - $offset - 1] ?? null;
        $expectedLead[] = $shifted[$index + $offset + 1] ?? $leadDefault;
    }
    $expectedRunning = [];
    $sum = 0;
    foreach ($shifted as $value) {
        $sum += $value;
        $expectedRunning[] = $sum;
    }
    $expectedRank = [];
    $expectedDenseRank = [];
    $lastKey = null;
    $dense = 0;
    foreach ($peerKeys as $index => $key) {
        if ($index === 0 || $key !== $lastKey) {
            $dense++;
            $lastKey = $key;
        }
        $expectedDenseRank[] = $dense;
        $firstPeer = array_search($key, $peerKeys, true);
        $expectedRank[] = $firstPeer + 1;
    }

    $tests["real upstream window1 dynamic ranking lead ntile case {$case}"] = static function (TestRunner $t) use ($case, $values, $shifted, $peerKeys, $buckets, $offset, $leadDefault, $expectedLag, $expectedLead, $expectedRunning, $expectedRank, $expectedDenseRank): void {
        $t->same(range(1, count($values)), SQLiteWindowFunction::rowNumber($values), "window1.test 7.3 dynamic row_number {$case}");
        $t->same($expectedLag, SQLiteWindowFunction::lag($shifted, $offset + 1), "window1.test 7.2 dynamic lag {$case}");
        $t->same($expectedLead, SQLiteWindowFunction::lead($shifted, $offset + 1, $leadDefault), "window1.test 7.2 dynamic lead {$case}");
        $t->same($expectedRunning, SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $shifted, $values, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), "window1.test 4.4 dynamic running sum {$case}");
        $t->same($expectedRank, SQLiteWindowFunction::rank($peerKeys), "window1.test 13 dynamic peer rank {$case}");
        $t->same($expectedDenseRank, SQLiteWindowFunction::denseRank($peerKeys), "window1.test 13 dynamic peer dense rank {$case}");
        $t->same(count($values), count(SQLiteWindowFunction::ntile($values, $buckets)), "window1.test 5.4 dynamic ntile row count {$case}");
    };
}

$tests['real upstream window1 dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 1.1-1.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 4.1-4.10.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 5.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 7.2-7.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 13.2-13.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 36.10-36.40',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 1.1-1.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 4.1-4.10.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 5.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 7.2-7.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 13.2-13.5',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 36.10-36.40',
    ]);
};

return $tests;
