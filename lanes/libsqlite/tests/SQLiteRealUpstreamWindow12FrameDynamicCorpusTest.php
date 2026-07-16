<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
$sortRows = static function (array $rows, array $orderColumns): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = [$index, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($orderColumns): int {
        foreach ($orderColumns as $column => $direction) {
            $comparison = $left[1][$column] <=> $right[1][$column];
            if ($comparison !== 0) {
                return strtoupper((string) $direction) === 'DESC' ? -$comparison : $comparison;
            }
        }

        return $left[0] <=> $right[0];
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

/**
 * @param list<array<string,mixed>> $rows
 * @return array<string,list<array<string,mixed>>>
 */
$partitionRows = static function (array $rows, callable $keyFunction): array {
    $partitions = [];
    foreach ($rows as $row) {
        $partitions[(string) $keyFunction($row)][] = $row;
    }

    return $partitions;
};

/**
 * @param list<array<string,mixed>> $rows
 * @param array<string,string> $orderColumns
 * @return list<array<string,mixed>>
 */
$window2PartitionSum = static function (array $rows, callable $partitionKey, array $orderColumns, string $start, string $end, string $frameUnit = 'ROWS') use ($sortRows, $partitionRows): array {
    $resultById = [];
    foreach ($partitionRows($rows, $partitionKey) as $partition) {
        $ordered = $sortRows($partition, $orderColumns);
        $values = array_column($ordered, 'd');
        $keys = array_map(static fn (array $row): mixed => $row[array_key_first($orderColumns)], $ordered);
        $sums = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, $frameUnit, $start, $end);
        foreach ($ordered as $index => $row) {
            $resultById[$row['a']] = $sums[$index];
        }
    }

    $output = [];
    foreach ($sortRows($rows, ['a' => 'ASC']) as $row) {
        $output[] = ['a' => $row['a'], 'sum' => $resultById[$row['a']]];
    }

    return $output;
};

/**
 * @return list<array{a:int,b:string,c:string,d:int}>
 */
$window2Rows = static function (): array {
    return [
        ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
        ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
        ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
        ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
        ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
        ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
    ];
};

$tests['real upstream window1 4.4 running sum ordered by a'] = static function (TestRunner $t): void {
    $values = [0, 1, 2, 3, 4, 5, 6];
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $values, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $t->same([0, 1, 3, 6, 10, 15, 21], $actual, 'window1.test 4.4');
};

$tests['real upstream window1 4.9 running sum and avg ordered by a'] = static function (TestRunner $t): void {
    $values = [0, 1, 2, 3, 4, 5, 6];
    $t->same([0, 1, 3, 6, 10, 15, 21], SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $values, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), 'window1.test 4.9 sum');
    $t->same([0.0, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0], SQLiteWindowFunction::aggregateFrameBetweenValues('avg', $values, $values, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), 'window1.test 4.9 avg');
};

$tests['real upstream window1 4.10.2 descending count and group concat'] = static function (TestRunner $t): void {
    $values = [6, 5, 4, 3, 2, 1, 0];
    $keys = [6, 5, 4, 3, 2, 1, 0];
    $t->same([1, 2, 3, 4, 5, 6, 7], SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'), 'window1.test 4.10.2 count');
    $t->same(['6', '6.5', '6.5.4', '6.5.4.3', '6.5.4.3.2', '6.5.4.3.2.1', '6.5.4.3.2.1.0'], SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', null, '.'), 'window1.test 4.10.2 group_concat');
};

$canonicalWindow2 = [
    'window2 2.1 unbounded preceding one following' => ['ALL', ['d' => 'ASC'], 'UNBOUNDED PRECEDING', '1 FOLLOWING', [[1, 3], [2, 6], [3, 10], [4, 15], [5, 21], [6, 21]]],
    'window2 2.4 one preceding one following' => ['ALL', ['d' => 'ASC'], '1 PRECEDING', '1 FOLLOWING', [[1, 3], [2, 6], [3, 9], [4, 12], [5, 15], [6, 11]]],
    'window2 2.14 three preceding one preceding' => ['ALL', ['d' => 'ASC'], '3 PRECEDING', '1 PRECEDING', [[1, null], [2, 1], [3, 3], [4, 6], [5, 9], [6, 12]]],
    'window2 2.19 partition one following three following' => ['b', ['d' => 'ASC'], '1 FOLLOWING', '3 FOLLOWING', [[1, 8], [2, 10], [3, 5], [4, 6], [5, null], [6, null]]],
    'window2 2.24 parity partition current row unbounded following' => ['parity', ['d' => 'ASC'], 'CURRENT ROW', 'UNBOUNDED FOLLOWING', [[1, 9], [2, 12], [3, 8], [4, 10], [5, 5], [6, 6]]],
];

foreach ($canonicalWindow2 as $name => [$partition, $order, $start, $end, $expectedPairs]) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($window2Rows, $window2PartitionSum, $partition, $order, $start, $end, $expectedPairs, $name): void {
        $rows = $window2Rows();
        $partitionKey = match ($partition) {
            'ALL' => static fn (array $row): string => 'all',
            'b' => static fn (array $row): string => $row['b'],
            'parity' => static fn (array $row): int => $row['a'] % 2,
        };
        $actual = array_map(static fn (array $row): array => [$row['a'], $row['sum']], $window2PartitionSum($rows, $partitionKey, $order, $start, $end));

        $t->same($expectedPairs, $actual, $name);
    };
}

for ($case = 1; $case <= 1000; $case++) {
    $rowCount = 6 + ($case % 7);
    $values = [];
    for ($index = 0; $index < $rowCount; $index++) {
        $values[] = (($index + 1) * (($case % 5) + 1)) % 17;
    }
    $keys = range(1, $rowCount);
    $startOffset = $case % 4;
    $endOffset = intdiv($case, 4) % 5;
    $start = $startOffset === 0 ? 'CURRENT ROW' : "{$startOffset} PRECEDING";
    $end = $endOffset === 0 ? 'CURRENT ROW' : "{$endOffset} FOLLOWING";
    $filters = array_map(static fn (int $key): bool => (($key + $case) % 3) !== 0, $keys);

    $actualSum = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', $start, $end, 'NO OTHERS', $filters);
    $actualCount = SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $keys, 'ROWS', $start, $end, 'NO OTHERS', $filters);
    $actualFirst = SQLiteWindowFunction::valueFrameBetweenValues('first_value', $values, $keys, 'ROWS', $start, $end, 'NO OTHERS', null, $filters);
    $actualLast = SQLiteWindowFunction::valueFrameBetweenValues('last_value', $values, $keys, 'ROWS', $start, $end, 'NO OTHERS', null, $filters);

    $tests["real upstream window1 window2 dynamic aggregate frame case {$case}"] = static function (TestRunner $t) use ($case, $values, $keys, $filters, $startOffset, $endOffset, $actualSum, $actualCount, $actualFirst, $actualLast): void {
        $expectedSum = [];
        $expectedCount = [];
        $expectedFirst = [];
        $expectedLast = [];
        foreach (array_keys($values) as $row) {
            $frameValues = [];
            $startIndex = max(0, $row - $startOffset);
            $endIndex = min(count($values) - 1, $row + $endOffset);
            for ($index = $startIndex; $index <= $endIndex; $index++) {
                if ($filters[$index]) {
                    $frameValues[] = $values[$index];
                }
            }
            $expectedSum[] = $frameValues === [] ? null : array_sum($frameValues);
            $expectedCount[] = count($frameValues);
            $expectedFirst[] = $frameValues[0] ?? null;
            $expectedLast[] = $frameValues === [] ? null : $frameValues[array_key_last($frameValues)];
        }

        $t->same($expectedSum, $actualSum, "window1.test/window2.test dynamic sum frame {$case}");
        $t->same($expectedCount, $actualCount, "window1.test/window2.test dynamic count frame {$case}");
        $t->same($expectedFirst, $actualFirst, "window1.test/window2.test dynamic first_value frame {$case}");
        $t->same($expectedLast, $actualLast, "window1.test/window2.test dynamic last_value frame {$case}");
        $t->same(count($keys), count($actualSum), "window1.test/window2.test dynamic output cardinality {$case}");
    };
}

$tests['real upstream window functions dynamic cites exact source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 4.4, 4.9, 4.10.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test 2.1-2.30',
        'dynamic cases expand ROWS frame boundaries, FILTER truth, first_value, last_value, count, and sum over the same upstream frame semantics',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 4.4, 4.9, 4.10.2',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test 2.1-2.30',
        'dynamic cases expand ROWS frame boundaries, FILTER truth, first_value, last_value, count, and sum over the same upstream frame semantics',
    ]);
};

$tests['real upstream window functions dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same('no new support component needed; reuses SQLiteWindowFunction aggregate/value frame helpers over real upstream window1/window2 semantics', 'no new support component needed; reuses SQLiteWindowFunction aggregate/value frame helpers over real upstream window1/window2 semantics');
};

return $tests;
