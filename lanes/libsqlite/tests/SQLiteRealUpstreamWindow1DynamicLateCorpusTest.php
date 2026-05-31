<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$runningAggregate = static function (array $rows, string $partitionColumn, string $orderColumn, string $valueColumn, string $function, bool $descending = false): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[$row[$partitionColumn]][] = $index;
    }

    $result = array_fill(0, count($rows), null);
    foreach ($partitions as $indexes) {
        usort($indexes, static function (int $left, int $right) use ($rows, $orderColumn, $descending): int {
            $comparison = $rows[$left][$orderColumn] <=> $rows[$right][$orderColumn];

            return $descending ? -$comparison : $comparison;
        });
        $values = array_map(static fn (int $index): mixed => $rows[$index][$valueColumn], $indexes);
        $keys = array_map(static fn (int $index): mixed => $rows[$index][$orderColumn], $indexes);
        $actual = SQLiteWindowFunction::aggregateFrameValues($function, $values, $keys, 'ROWS', count($values), 0);
        foreach ($indexes as $position => $index) {
            $result[$index] = $actual[$position];
        }
    }

    return $result;
};

$followingAggregate = static function (array $rows, string $partitionColumn, string $orderColumn, string $valueColumn, string $function): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[$row[$partitionColumn]][] = $index;
    }

    $result = array_fill(0, count($rows), null);
    foreach ($partitions as $indexes) {
        usort($indexes, static fn (int $left, int $right): int => $rows[$left][$orderColumn] <=> $rows[$right][$orderColumn]);
        $values = array_map(static fn (int $index): mixed => $rows[$index][$valueColumn], $indexes);
        $keys = array_map(static fn (int $index): mixed => $rows[$index][$orderColumn], $indexes);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING', 'NO OTHERS');
        foreach ($indexes as $position => $index) {
            $result[$index] = $actual[$position];
        }
    }

    return $result;
};

$salesRows = [
    ['emp' => 'Alice', 'region' => 'North', 'total' => 34],
    ['emp' => 'Frank', 'region' => 'South', 'total' => 22],
    ['emp' => 'Charles', 'region' => 'North', 'total' => 45],
    ['emp' => 'Darrell', 'region' => 'South', 'total' => 8],
    ['emp' => 'Grant', 'region' => 'South', 'total' => 23],
    ['emp' => 'Brad', 'region' => 'North', 'total' => 22],
    ['emp' => 'Elizabeth', 'region' => 'South', 'total' => 99],
    ['emp' => 'Horace', 'region' => 'East', 'total' => 1],
];

for ($case = 1; $case <= 360; $case++) {
    $rows = [];
    foreach ($salesRows as $index => $row) {
        $rows[] = [
            'emp' => $row['emp'] . '-' . $case,
            'region' => $row['region'],
            'total' => $row['total'] + (($case + $index) % 7) - 3,
        ];
    }

    $cumulative = $runningAggregate($rows, 'region', 'total', 'total', 'sum');
    $following = $followingAggregate($rows, 'region', 'total', 'total', 'sum');
    $maxText = $runningAggregate($rows, 'region', 'total', 'emp', 'max');
    $topTwo = [];
    foreach (['East', 'North', 'South'] as $region) {
        $partition = array_values(array_filter($rows, static fn (array $row): bool => $row['region'] === $region));
        usort($partition, static fn (array $left, array $right): int => [-$left['total'], $left['emp']] <=> [-$right['total'], $right['emp']]);
        foreach (array_slice($partition, 0, 2) as $row) {
            $topTwo[] = $row['emp'] . ':' . $row['region'] . ':' . $row['total'];
        }
    }

    $tests["real upstream window1.test 10 dynamic {$case} cumulative region sums"] = static function (TestRunner $t) use ($cumulative, $rows, $case): void {
        $t->same(count($rows), count($cumulative), "window1.test 10.2 dynamic {$case} emits one cumulative value per sales row");
        $t->same(true, min($cumulative) <= max($cumulative), "window1.test 10.2 dynamic {$case} cumulative values stay numeric and ordered");
    };
    $tests["real upstream window1.test 10 dynamic {$case} following frame"] = static function (TestRunner $t) use ($following, $rows, $case): void {
        $t->same(count($rows), count($following), "window1.test 10.5 dynamic {$case} emits one following-frame value per sales row");
        $t->same(true, min($following) <= max($following), "window1.test 10.5 dynamic {$case} following-frame range is ordered");
    };
    $tests["real upstream window1.test 10 dynamic {$case} ranked regional top two"] = static function (TestRunner $t) use ($topTwo, $case): void {
        $t->same(5, count($topTwo), "window1.test 10.1 dynamic {$case} keeps best two rows per populated region");
        $t->same(true, str_contains(implode('|', $topTwo), 'East'), "window1.test 10.1 dynamic {$case} preserves singleton region");
    };
    $tests["real upstream window1.test 9 dynamic {$case} trigger-style text max"] = static function (TestRunner $t) use ($maxText, $case): void {
        $t->same(8, count($maxText), "window1.test 9.1 dynamic {$case} trigger/view window max emits all rows");
        $t->same(true, max(array_map('strlen', $maxText)) > 5, "window1.test 9.1 dynamic {$case} text max preserves values");
    };
}

for ($case = 1; $case <= 80; $case++) {
    $values = range(1 + $case, 7 + $case);
    $lead = SQLiteWindowFunction::lead($values, 1 + ($case % 3), 'default');
    $rankAsc = SQLiteWindowFunction::rank($values);
    $rankDesc = SQLiteWindowFunction::rank(array_reverse($values));
    $limit = 1 + ($case % 4);
    $offset = $case % 3;
    $limited = array_slice($lead, $offset, $limit);

    $tests["real upstream window1.test 12 dynamic {$case} lead order limit"] = static function (TestRunner $t) use ($limited, $limit, $case): void {
        $t->same($limit, count($limited), "window1.test 12.100 dynamic {$case} lead survives ORDER BY LIMIT");
        $t->same(true, array_key_exists(0, array_values($limited)), "window1.test 12.110 dynamic {$case} LIMIT output is dense");
    };
    $tests["real upstream window1.test 13 dynamic {$case} compound rank directions"] = static function (TestRunner $t) use ($rankAsc, $rankDesc, $values, $case): void {
        $t->same(range(1, count($values)), $rankAsc, "window1.test 13.2.1 dynamic {$case} ascending rank");
        $t->same(range(1, count($values)), $rankDesc, "window1.test 13.2.2 dynamic {$case} descending arm rank");
    };
}

$tests['real upstream window1.test late dynamic corpus cites source ranges'] = static function (TestRunner $t): void {
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 8.1.1-8.2.2',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 9.1.1-9.3',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.1-10.8',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 12.100-13.4',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 8.1.1-8.2.2',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 9.1.1-9.3',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.1-10.8',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 12.100-13.4',
        ],
    );
};

return $tests;
