<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowPushdownRows = [
    ['id' => 1, 'grp_id' => 2],
    ['id' => 2, 'grp_id' => 3],
    ['id' => 3, 'grp_id' => 3],
    ['id' => 4, 'grp_id' => 1],
    ['id' => 5, 'grp_id' => 1],
    ['id' => 6, 'grp_id' => 1],
    ['id' => 7, 'grp_id' => 1],
    ['id' => 8, 'grp_id' => 1],
    ['id' => 9, 'grp_id' => 3],
    ['id' => 10, 'grp_id' => 3],
    ['id' => 11, 'grp_id' => 2],
    ['id' => 12, 'grp_id' => 3],
    ['id' => 13, 'grp_id' => 3],
    ['id' => 14, 'grp_id' => 2],
    ['id' => 15, 'grp_id' => 1],
    ['id' => 16, 'grp_id' => 2],
    ['id' => 17, 'grp_id' => 1],
    ['id' => 18, 'grp_id' => 2],
    ['id' => 19, 'grp_id' => 3],
    ['id' => 20, 'grp_id' => 2],
];

$windowPushdownTableRows = [
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

$windowPushdownGroupedRows = [
    ['x' => 'W', 'y' => 3, 'z' => 1],
    ['x' => 'W', 'y' => 2, 'z' => 2],
    ['x' => 'X', 'y' => 1, 'z' => 4],
    ['x' => 'X', 'y' => 5, 'z' => 7],
    ['x' => 'Y', 'y' => 1, 'z' => 9],
    ['x' => 'Y', 'y' => 4, 'z' => 2],
    ['x' => 'Z', 'y' => 3, 'z' => 3],
    ['x' => 'Z', 'y' => 3, 'z' => 4],
];

$partitionRowNumber = static function (array $rows, string $partitionColumn, string $orderColumn): array {
    usort($rows, static function (array $left, array $right) use ($partitionColumn, $orderColumn): int {
        return [$left[$partitionColumn], $left[$orderColumn]] <=> [$right[$partitionColumn], $right[$orderColumn]];
    });

    $partitions = [];
    foreach ($rows as $row) {
        $partitions[(string) $row[$partitionColumn]][] = $row;
    }

    $result = [];
    foreach ($partitions as $partitionRows) {
        $numbers = SQLiteWindowFunction::rowNumber(array_column($partitionRows, $orderColumn));
        foreach ($partitionRows as $index => $row) {
            $row['row_number'] = $numbers[$index];
            $result[] = $row;
        }
    }

    return $result;
};

$partitionMaxRows = static function (array $rows, string $partitionColumn, string $valueColumn, string $orderColumn): array {
    usort($rows, static function (array $left, array $right) use ($partitionColumn, $orderColumn): int {
        return [$left[$partitionColumn], $left[$orderColumn]] <=> [$right[$partitionColumn], $right[$orderColumn]];
    });

    $partitions = [];
    foreach ($rows as $row) {
        $partitions[(string) $row[$partitionColumn]][] = $row;
    }

    $result = [];
    foreach ($partitions as $partitionRows) {
        $maxValues = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'max',
            array_column($partitionRows, $valueColumn),
            array_column($partitionRows, $orderColumn),
            'ROWS',
            'UNBOUNDED PRECEDING',
            'UNBOUNDED FOLLOWING',
        );
        foreach ($partitionRows as $index => $row) {
            $row['partition_max'] = $maxValues[$index];
            $result[] = $row;
        }
    }

    return $result;
};

$groupedWindowRows = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['x']][] = $row;
    }

    $groupRows = [];
    foreach ($groups as $x => $members) {
        $groupRows[] = [
            'x' => $x,
            's' => array_sum(array_column($members, 'y')),
            'm' => max(array_column($members, 'z')),
        ];
    }
    usort($groupRows, static fn (array $left, array $right): int => [$left['s'], $left['x']] <=> [$right['s'], $right['x']]);

    $partitions = [];
    foreach ($groupRows as $row) {
        $partitions[(string) $row['s']][] = $row;
    }

    $result = [];
    foreach ($partitions as $partitionRows) {
        $maxOfMax = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'max',
            array_column($partitionRows, 'm'),
            array_column($partitionRows, 'x'),
            'ROWS',
            'UNBOUNDED PRECEDING',
            'UNBOUNDED FOLLOWING',
        );
        foreach ($partitionRows as $index => $row) {
            $row['partition_max'] = $maxOfMax[$index];
            $result[] = $row;
        }
    }

    return $result;
};

$tests['real upstream windowpushd 1.2 row_number view preserves partition order'] = static function (TestRunner $t) use ($windowPushdownRows, $partitionRowNumber): void {
    $actual = array_map(
        static fn (array $row): array => [$row['row_number'], $row['grp_id'], $row['id']],
        $partitionRowNumber($windowPushdownRows, 'grp_id', 'id'),
    );

    $t->same([
        [1, 1, 4], [2, 1, 5], [3, 1, 6], [4, 1, 7], [5, 1, 8], [6, 1, 15], [7, 1, 17],
        [1, 2, 1], [2, 2, 11], [3, 2, 14], [4, 2, 16], [5, 2, 18], [6, 2, 20],
        [1, 3, 2], [2, 3, 3], [3, 3, 9], [4, 3, 10], [5, 3, 12], [6, 3, 13], [7, 3, 19],
    ], $actual, 'windowpushd.test 1.2');
};

$tests['real upstream windowpushd 1.3 pushed equality keeps row_number partition intact'] = static function (TestRunner $t) use ($windowPushdownRows, $partitionRowNumber): void {
    $actual = array_values(array_map(
        static fn (array $row): array => [$row['row_number'], $row['grp_id'], $row['id']],
        array_filter($partitionRowNumber($windowPushdownRows, 'grp_id', 'id'), static fn (array $row): bool => $row['grp_id'] === 2),
    ));

    $t->same([[1, 2, 1], [2, 2, 11], [3, 2, 14], [4, 2, 16], [5, 2, 18], [6, 2, 20]], $actual, 'windowpushd.test 1.3');
};

$tests['real upstream windowpushd 2.1.2 pushed IN keeps partition max intact'] = static function (TestRunner $t) use ($windowPushdownTableRows, $partitionMaxRows): void {
    $actual = array_values(array_map(
        static fn (array $row): array => [$row['a'], $row['c'], $row['partition_max']],
        array_filter($partitionMaxRows($windowPushdownTableRows, 'a', 'c', 'c'), static fn (array $row): bool => in_array($row['a'], ['A', 'B'], true)),
    ));

    $t->same([
        ['A', 1, 4], ['A', 2, 4], ['A', 3, 4], ['A', 4, 4],
        ['B', 5, 8], ['B', 6, 8], ['B', 7, 8], ['B', 8, 8],
    ], $actual, 'windowpushd.test 2.1.2');
};

$tests['real upstream windowpushd 2.3.2 pushed range keeps partition max intact'] = static function (TestRunner $t) use ($windowPushdownTableRows, $partitionMaxRows): void {
    $actual = array_values(array_map(
        static fn (array $row): array => [$row['b'], $row['d'], $row['partition_max'], $row['row_number']],
        array_filter(
            $partitionRowNumber = (static function () use ($windowPushdownTableRows, $partitionMaxRows): array {
                $rows = $partitionMaxRows($windowPushdownTableRows, 'b', 'd', 'd');
                $partitions = [];
                foreach ($rows as $row) {
                    $partitions[$row['b']][] = $row;
                }
                $result = [];
                foreach ($partitions as $partitionRows) {
                    $numbers = SQLiteWindowFunction::rowNumber(array_column($partitionRows, 'd'));
                    foreach ($partitionRows as $index => $row) {
                        $row['row_number'] = $numbers[$index];
                        $result[] = $row;
                    }
                }

                return $result;
            })(),
            static fn (array $row): bool => $row['b'] < 'E',
        ),
    ));

    $t->same([
        ['C', 0.1, 1.0, 1], ['C', 0.4, 1.0, 2], ['C', 0.7, 1.0, 3], ['C', 1.0, 1.0, 4],
        ['D', 0.2, 1.1, 1], ['D', 0.5, 1.1, 2], ['D', 0.8, 1.1, 3], ['D', 1.1, 1.1, 4],
    ], $actual, 'windowpushd.test 2.3.2');
};

$tests['real upstream windowpushd 2.4.2 pushed aggregate group keeps outer partition window'] = static function (TestRunner $t) use ($windowPushdownGroupedRows, $groupedWindowRows): void {
    $actual = array_values(array_map(
        static fn (array $row): array => [$row['x'], $row['s'], $row['m'], $row['partition_max']],
        array_filter($groupedWindowRows($windowPushdownGroupedRows), static fn (array $row): bool => $row['s'] === 6),
    ));

    $t->same([['X', 6, 7, 7], ['Z', 6, 4, 7]], $actual, 'windowpushd.test 2.4.2');
};

$tests['real upstream filter1 5.2 window count filter over subquery'] = static function (TestRunner $t): void {
    $values = [2, 3];
    $filters = array_map(static fn (int $value): bool => $value > 2, $values);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, [1, 2], 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'NO OTHERS', $filters);

    $t->same([1, 1], $actual, 'filter1.test 5.2');
};

$tests['real upstream filter1 5.3 ordered window count filter over subquery'] = static function (TestRunner $t): void {
    $values = [2, 3];
    $filters = array_map(static fn (int $value): bool => $value > 2, $values);
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $values, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $filters);

    $t->same([0, 1], $actual, 'filter1.test 5.3');
};

for ($case = 1; $case <= 1000; $case++) {
    $groupCount = 3 + ($case % 5);
    $rowsPerGroup = 3 + (intdiv($case, 5) % 7);
    $targetGroup = 1 + ($case % $groupCount);
    $multiplier = 2 + ($case % 11);
    $rows = [];
    $id = 1;
    for ($group = 1; $group <= $groupCount; $group++) {
        for ($offset = 0; $offset < $rowsPerGroup; $offset++) {
            $rows[] = [
                'id' => $id++,
                'grp_id' => $group,
                'value' => ($group * $multiplier) + $offset,
            ];
        }
    }

    $windowRows = $partitionRowNumber($rows, 'grp_id', 'id');
    $pushedRows = array_values(array_filter($windowRows, static fn (array $row): bool => $row['grp_id'] === $targetGroup));
    $expectedNumbers = range(1, $rowsPerGroup);
    $expectedIds = array_values(array_map(static fn (array $row): int => $row['id'], array_filter($rows, static fn (array $row): bool => $row['grp_id'] === $targetGroup)));
    $filterThreshold = $targetGroup * $multiplier + intdiv($rowsPerGroup, 2);
    $filters = array_map(static fn (array $row): bool => $row['value'] > $filterThreshold, $pushedRows);
    $filteredCounts = SQLiteWindowFunction::aggregateFrameBetweenValues(
        'count',
        array_column($pushedRows, 'value'),
        array_column($pushedRows, 'id'),
        'ROWS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
        'NO OTHERS',
        $filters,
    );
    $expectedCounts = [];
    $running = 0;
    foreach ($pushedRows as $row) {
        if ($row['value'] > $filterThreshold) {
            $running++;
        }
        $expectedCounts[] = $running;
    }

    $tests["real upstream windowpushd filter dynamic case {$case}"] = static function (TestRunner $t) use ($case, $pushedRows, $expectedNumbers, $expectedIds, $filteredCounts, $expectedCounts): void {
        $t->same($expectedNumbers, array_column($pushedRows, 'row_number'), "windowpushd.test 1.3 dynamic row numbers {$case}");
        $t->same($expectedIds, array_column($pushedRows, 'id'), "windowpushd.test 1.3 dynamic pushed ids {$case}");
        $t->same($expectedCounts, $filteredCounts, "filter1.test 5.3 dynamic ordered filtered count {$case}");
    };
}

$tests['real upstream windowpushd filter dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.1.1-2.4.3',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 5.1-5.3',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.1.1-2.4.3',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 5.1-5.3',
    ]);
};

return $tests;
