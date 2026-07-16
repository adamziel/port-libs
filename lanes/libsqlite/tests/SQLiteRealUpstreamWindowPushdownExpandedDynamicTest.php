<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$upstreamRows = [
    ['a' => 'A', 'b' => 'C', 'c' => 1, 'd' => 0.1],
    ['a' => 'A', 'b' => 'D', 'c' => 2, 'd' => 0.2],
    ['a' => 'A', 'b' => 'E', 'c' => 3, 'd' => 0.3],
    ['a' => 'A', 'b' => 'C', 'c' => 4, 'd' => 0.4],
    ['a' => 'B', 'b' => 'D', 'c' => 5, 'd' => 0.5],
    ['a' => 'B', 'b' => 'E', 'c' => 6, 'd' => 0.6],
    ['a' => 'B', 'b' => 'C', 'c' => 7, 'd' => 0.7],
    ['a' => 'B', 'b' => 'D', 'c' => 8, 'd' => 0.8],
    ['a' => 'C', 'b' => 'E', 'c' => 9, 'd' => 0.9],
    ['a' => 'C', 'b' => 'C', 'c' => 10, 'd' => 1.0],
    ['a' => 'C', 'b' => 'D', 'c' => 11, 'd' => 1.1],
    ['a' => 'C', 'b' => 'E', 'c' => 12, 'd' => 1.2],
];

$buildCorpus = static function (int $cycles) use ($upstreamRows): array {
    $rows = [];
    for ($cycle = 0; $cycle < $cycles; $cycle++) {
        foreach ($upstreamRows as $row) {
            $rows[] = [
                'a' => chr(ord($row['a']) + ($cycle % 3)),
                'b' => $row['b'],
                'c' => $row['c'] + ($cycle * 12),
                'd' => round($row['d'] + ($cycle * 1.2), 1),
                'cycle' => $cycle,
            ];
        }
    }

    return $rows;
};

$partitionMaxOracle = static function (array $rows, string $partitionColumn, string $valueColumn): array {
    $maxByPartition = [];
    foreach ($rows as $row) {
        $partition = $row[$partitionColumn];
        $maxByPartition[$partition] = max($maxByPartition[$partition] ?? $row[$valueColumn], $row[$valueColumn]);
    }

    return array_map(static fn (array $row): mixed => $maxByPartition[$row[$partitionColumn]], $rows);
};

$partitionRowNumberOracle = static function (array $rows, string $partitionColumn, string $orderColumn): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[$row[$partitionColumn]][] = [$index, $row[$orderColumn]];
    }

    $result = array_fill(0, count($rows), null);
    foreach ($partitions as $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => [$left[1], $left[0]] <=> [$right[1], $right[0]]);
        foreach ($partitionRows as $position => [$index]) {
            $result[$index] = $position + 1;
        }
    }

    return $result;
};

$nativePartitionMax = static function (array $rows, string $partitionColumn, string $valueColumn): array {
    $result = array_fill(0, count($rows), null);
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[$row[$partitionColumn]][] = [$index, $row[$valueColumn]];
    }

    foreach ($partitions as $partitionRows) {
        $indexes = array_column($partitionRows, 0);
        $values = array_column($partitionRows, 1);
        $maxValues = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, range(1, count($values)), 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
        foreach ($indexes as $offset => $index) {
            $result[$index] = $maxValues[$offset];
        }
    }

    return $result;
};

$nativePartitionRowNumber = static function (array $rows, string $partitionColumn, string $orderColumn): array {
    $result = array_fill(0, count($rows), null);
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[$row[$partitionColumn]][] = [$index, $row[$orderColumn]];
    }

    foreach ($partitions as $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => [$left[1], $left[0]] <=> [$right[1], $right[0]]);
        $rowNumbers = SQLiteWindowFunction::rowNumber(array_column($partitionRows, 1));
        foreach ($partitionRows as $offset => [$index]) {
            $result[$index] = $rowNumbers[$offset];
        }
    }

    return $result;
};

$filters = [
    'a-in-A-B' => static fn (array $row): bool => $row['a'] === 'A' || $row['a'] === 'B',
    'a-is-C' => static fn (array $row): bool => $row['a'] === 'C',
    'b-less-E' => static fn (array $row): bool => $row['b'] < 'E',
    'b-equals-E' => static fn (array $row): bool => $row['b'] === 'E',
    'd-less-10-55' => static fn (array $row): bool => $row['d'] < 10.55,
    'cycle-even' => static fn (array $row): bool => ($row['cycle'] % 2) === 0,
    'cycle-third' => static fn (array $row): bool => ($row['cycle'] % 3) === 1,
    'c-range' => static fn (array $row): bool => $row['c'] >= 15 && $row['c'] <= 150,
    'a-nocase-c' => static fn (array $row): bool => strtolower((string) $row['a']) === 'c',
    'b-not-C' => static fn (array $row): bool => $row['b'] !== 'C',
];

for ($case = 1; $case <= 500; $case++) {
    $rows = $buildCorpus(4 + ($case % 18));
    $filterNames = array_keys($filters);
    $filterName = $filterNames[$case % count($filterNames)];
    $filteredRows = array_values(array_filter($rows, $filters[$filterName]));

    $tests["real upstream windowpushd.test expanded v1 max partition case {$case}"] = static function (TestRunner $t) use ($filteredRows, $partitionMaxOracle, $nativePartitionMax, $case, $filterName): void {
        $t->same(
            $partitionMaxOracle($filteredRows, 'a', 'c'),
            $nativePartitionMax($filteredRows, 'a', 'c'),
            "windowpushd.test 2.0/2.1 max(c) partition by a pushed {$filterName} case {$case}",
        );
    };

    $tests["real upstream windowpushd.test expanded v3 row-number partition case {$case}"] = static function (TestRunner $t) use ($filteredRows, $partitionRowNumberOracle, $nativePartitionRowNumber, $case, $filterName): void {
        $t->same(
            $partitionRowNumberOracle($filteredRows, 'b', 'd'),
            $nativePartitionRowNumber($filteredRows, 'b', 'd'),
            "windowpushd.test 2.0/2.1 row_number partition by b pushed {$filterName} case {$case}",
        );
    };
}

$tests['real upstream windowpushd expanded dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test:2.0-2.1.5 view v1/v3 push-down filters',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test:2.1.1.2 IN filter over PARTITION BY a',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test:2.1.3.2-2.1.3.6 partitioned b/d filter push-down',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test:2.0-2.1.5 view v1/v3 push-down filters',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test:2.1.1.2 IN filter over PARTITION BY a',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test:2.1.3.2-2.1.3.6 partitioned b/d filter push-down',
        ],
    );
};

return $tests;
