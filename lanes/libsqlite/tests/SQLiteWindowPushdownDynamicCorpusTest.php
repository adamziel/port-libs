<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$lllRows = [
    ['id' => 1, 'grp_id' => 2], ['id' => 2, 'grp_id' => 3], ['id' => 3, 'grp_id' => 3],
    ['id' => 4, 'grp_id' => 1], ['id' => 5, 'grp_id' => 1], ['id' => 6, 'grp_id' => 1],
    ['id' => 7, 'grp_id' => 1], ['id' => 8, 'grp_id' => 1], ['id' => 9, 'grp_id' => 3],
    ['id' => 10, 'grp_id' => 3], ['id' => 11, 'grp_id' => 2], ['id' => 12, 'grp_id' => 3],
    ['id' => 13, 'grp_id' => 3], ['id' => 14, 'grp_id' => 2], ['id' => 15, 'grp_id' => 1],
    ['id' => 16, 'grp_id' => 2], ['id' => 17, 'grp_id' => 1], ['id' => 18, 'grp_id' => 2],
    ['id' => 19, 'grp_id' => 3], ['id' => 20, 'grp_id' => 2],
];

$partitionRowNumberRows = static function (array $rows, string $partitionKey, string $orderKey): array {
    $partitions = [];
    foreach ($rows as $row) {
        $partitions[$row[$partitionKey]][] = $row;
    }
    ksort($partitions);

    $result = [];
    foreach ($partitions as $partition => $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => $left[$orderKey] <=> $right[$orderKey]);
        $rowNumbers = SQLiteWindowFunction::rowNumber(array_column($partitionRows, $orderKey));
        foreach ($partitionRows as $offset => $row) {
            $result[] = [$rowNumbers[$offset], $partition, $row[$orderKey]];
        }
    }

    return $result;
};

$maxOverPartitionRows = static function (array $rows, string $partitionKey, string $valueKey, string $orderKey): array {
    $partitions = [];
    foreach ($rows as $row) {
        $partitions[$row[$partitionKey]][] = $row;
    }
    ksort($partitions);

    $result = [];
    foreach ($partitions as $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => $left[$orderKey] <=> $right[$orderKey]);
        $maxValues = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'max',
            array_column($partitionRows, $valueKey),
            array_column($partitionRows, $orderKey),
            'ROWS',
            'UNBOUNDED PRECEDING',
            'UNBOUNDED FOLLOWING',
        );
        foreach ($partitionRows as $offset => $row) {
            $copy = $row;
            $copy['partition_max'] = $maxValues[$offset];
            $result[] = $copy;
        }
    }

    return $result;
};

$sourceRows = [
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

$groupRows = [
    ['x' => 'W', 'y' => 3, 'z' => 1],
    ['x' => 'W', 'y' => 2, 'z' => 2],
    ['x' => 'X', 'y' => 1, 'z' => 4],
    ['x' => 'X', 'y' => 5, 'z' => 7],
    ['x' => 'Y', 'y' => 1, 'z' => 9],
    ['x' => 'Y', 'y' => 4, 'z' => 2],
    ['x' => 'Z', 'y' => 3, 'z' => 3],
    ['x' => 'Z', 'y' => 3, 'z' => 4],
];

$groupedWindowRows = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['x']][] = $row;
    }
    ksort($groups);

    $groupRows = [];
    foreach ($groups as $x => $group) {
        $groupRows[] = [
            'x' => $x,
            's' => array_sum(array_column($group, 'y')),
            'm' => max(array_column($group, 'z')),
        ];
    }

    $bySum = [];
    foreach ($groupRows as $row) {
        $bySum[$row['s']][] = $row;
    }
    ksort($bySum);

    $result = [];
    foreach ($bySum as $sum => $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => strcmp($left['x'], $right['x']));
        $maxOverMax = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'max',
            array_column($partitionRows, 'm'),
            array_fill(0, count($partitionRows), $sum),
            'ROWS',
            'UNBOUNDED PRECEDING',
            'UNBOUNDED FOLLOWING',
        );
        foreach ($partitionRows as $offset => $row) {
            $row['window_max'] = $maxOverMax[$offset];
            $result[] = $row;
        }
    }

    return $result;
};

for ($case = 0; $case < 250; $case++) {
    $targetGroup = ($case % 3) + 1;
    $rows = array_map(
        static fn (array $row): array => [
            'id' => $row['id'] + ($case * 100),
            'grp_id' => (($row['grp_id'] + intdiv($case, 3) - 1) % 3) + 1,
        ],
        $lllRows,
    );

    $tests['real upstream windowpushd 1 dynamic row-number partition pushdown ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows, $targetGroup, $partitionRowNumberRows): void {
        $allRows = $partitionRowNumberRows($rows, 'grp_id', 'id');
        $filteredAfterWindow = array_values(array_filter($allRows, static fn (array $row): bool => $row[1] === $targetGroup));
        $filteredBeforeWindow = $partitionRowNumberRows(
            array_values(array_filter($rows, static fn (array $row): bool => $row['grp_id'] === $targetGroup)),
            'grp_id',
            'id',
        );

        $t->same($filteredBeforeWindow, $filteredAfterWindow);
        $t->same(count($filteredBeforeWindow), count($filteredAfterWindow));
        $t->same([1, $targetGroup], array_slice($filteredAfterWindow[0], 0, 2));
        $last = count($filteredAfterWindow) - 1;
        $t->same([$last + 1, $targetGroup], array_slice($filteredAfterWindow[$last], 0, 2));
    };
}

for ($case = 0; $case < 250; $case++) {
    $letters = ['A', 'B', 'C'];
    $target = $letters[$case % 3];
    $rows = array_map(
        static fn (array $row): array => [
            'a' => $letters[(array_search($row['a'], $letters, true) + intdiv($case, 3)) % 3],
            'b' => $row['b'],
            'c' => $row['c'] + ($case % 5),
            'd' => $row['d'],
        ],
        $sourceRows,
    );

    $tests['real upstream windowpushd 2 dynamic partition max equality pushdown ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows, $target, $maxOverPartitionRows): void {
        $allRows = $maxOverPartitionRows($rows, 'a', 'c', 'c');
        $filteredAfterWindow = array_values(array_filter($allRows, static fn (array $row): bool => $row['a'] === $target));
        $filteredBeforeWindow = $maxOverPartitionRows(
            array_values(array_filter($rows, static fn (array $row): bool => $row['a'] === $target)),
            'a',
            'c',
            'c',
        );

        $t->same($filteredBeforeWindow, $filteredAfterWindow);
        $t->same(4, count($filteredAfterWindow));
        $t->same(max(array_column($filteredAfterWindow, 'c')), $filteredAfterWindow[0]['partition_max']);
        $t->same(max(array_column($filteredAfterWindow, 'c')), $filteredAfterWindow[3]['partition_max']);
    };
}

for ($case = 0; $case < 250; $case++) {
    $threshold = [0.55, 0.85, 1.05][$case % 3];
    $rows = array_map(
        static fn (array $row): array => [
            'a' => $row['a'],
            'b' => $row['b'],
            'c' => $row['c'],
            'd' => round($row['d'] + (($case % 4) * 0.01), 2),
        ],
        $sourceRows,
    );

    $tests['real upstream windowpushd 2 dynamic nonpartition filter preserves full frame ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows, $threshold, $maxOverPartitionRows): void {
        $allRows = $maxOverPartitionRows($rows, 'b', 'd', 'd');
        $filteredAfterWindow = array_values(array_filter($allRows, static fn (array $row): bool => $row['d'] < $threshold));
        $wrongPushedRows = $maxOverPartitionRows(
            array_values(array_filter($rows, static fn (array $row): bool => $row['d'] < $threshold)),
            'b',
            'd',
            'd',
        );

        $t->same(count($wrongPushedRows), count($filteredAfterWindow));
        $t->same(false, array_column($wrongPushedRows, 'partition_max') === array_column($filteredAfterWindow, 'partition_max'));
        $t->same(max(array_column(array_filter($rows, static fn (array $row): bool => $row['b'] === 'C'), 'd')), $allRows[0]['partition_max']);
        $t->same(max(array_column(array_filter($rows, static fn (array $row): bool => $row['b'] === 'E'), 'd')), $allRows[count($allRows) - 1]['partition_max']);
    };
}

for ($case = 0; $case < 250; $case++) {
    $filter = [5, 6, 7][$case % 3];
    $rows = array_map(
        static fn (array $row): array => [
            'x' => $row['x'],
            'y' => $row['y'] + ($row['x'] === 'Z' ? $case % 2 : 0),
            'z' => $row['z'] + ($row['x'] === 'Y' ? $case % 4 : 0),
        ],
        $groupRows,
    );

    $tests['real upstream windowpushd 2 dynamic grouped aggregate window filter ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($rows, $filter, $groupedWindowRows): void {
        $windowRows = $groupedWindowRows($rows);
        $filtered = array_values(array_filter($windowRows, static fn (array $row): bool => $row['s'] <= $filter));

        foreach ($filtered as $row) {
            $partition = array_values(array_filter($windowRows, static fn (array $candidate): bool => $candidate['s'] === $row['s']));
            $t->same(max(array_column($partition, 'm')), $row['window_max']);
            $t->same(true, $row['s'] <= $filter);
        }
        $t->same(
            array_column($filtered, 'x'),
            array_column(array_values(array_filter($windowRows, static fn (array $row): bool => $row['s'] <= $filter)), 'x'),
        );
    };
}

return $tests;
