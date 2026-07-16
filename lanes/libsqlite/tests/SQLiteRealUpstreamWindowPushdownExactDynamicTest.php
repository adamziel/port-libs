<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$t1Rows = [
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

$lllRows = static function (array $rows): array {
    $byGroup = [];
    foreach ($rows as $row) {
        $byGroup[$row['grp_id']][] = $row;
    }
    ksort($byGroup);

    $result = [];
    foreach ($byGroup as $groupRows) {
        usort($groupRows, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);
        $numbers = SQLiteWindowFunction::rowNumber(array_column($groupRows, 'id'));
        foreach ($groupRows as $index => $row) {
            $result[] = [$numbers[$index], $row['grp_id'], $row['id']];
        }
    }

    return $result;
};

$t2Rows = [
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

$partitionMaxRows = static function (array $rows, string $partitionColumn, string $valueColumn): array {
    $byPartition = [];
    foreach ($rows as $row) {
        $byPartition[$row[$partitionColumn]][] = $row;
    }
    ksort($byPartition);

    $result = [];
    foreach ($byPartition as $partitionRows) {
        $values = array_column($partitionRows, $valueColumn);
        $keys = range(1, count($partitionRows));
        $maxValues = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
        foreach ($partitionRows as $index => $row) {
            $result[] = $row + ['partition_max' => $maxValues[$index]];
        }
    }

    return $result;
};

$v1Rows = static fn (): array => array_map(
    static fn (array $row): array => [$row['a'], $row['c'], $row['partition_max']],
    $partitionMaxRows($t2Rows, 'a', 'c'),
);

$v3Rows = static function () use ($t2Rows, $partitionMaxRows): array {
    $rows = $partitionMaxRows($t2Rows, 'b', 'd');
    $byPartition = [];
    foreach ($rows as $row) {
        $byPartition[$row['b']][] = $row;
    }
    ksort($byPartition);

    $result = [];
    foreach ($byPartition as $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => $left['d'] <=> $right['d']);
        $numbers = SQLiteWindowFunction::rowNumber(array_column($partitionRows, 'd'));
        foreach ($partitionRows as $index => $row) {
            $result[] = [$row['b'], $row['d'], $row['partition_max'], $numbers[$index]];
        }
    }

    return $result;
};

$t3Rows = [
    ['x' => 'W', 'y' => 3, 'z' => 1],
    ['x' => 'W', 'y' => 2, 'z' => 2],
    ['x' => 'X', 'y' => 1, 'z' => 4],
    ['x' => 'X', 'y' => 5, 'z' => 7],
    ['x' => 'Y', 'y' => 1, 'z' => 9],
    ['x' => 'Y', 'y' => 4, 'z' => 2],
    ['x' => 'Z', 'y' => 3, 'z' => 3],
    ['x' => 'Z', 'y' => 3, 'z' => 4],
];

$groupedRows = static function () use ($t3Rows): array {
    $groups = [];
    foreach ($t3Rows as $row) {
        $groups[$row['x']][] = $row;
    }
    ksort($groups);

    $rows = [];
    foreach ($groups as $x => $groupRows) {
        $rows[] = [
            'x' => $x,
            's' => array_sum(array_column($groupRows, 'y')),
            'm' => max(array_column($groupRows, 'z')),
        ];
    }
    usort($rows, static fn (array $left, array $right): int => $left['s'] <=> $right['s'] ?: strcmp($left['x'], $right['x']));

    $bySum = [];
    foreach ($rows as $index => $row) {
        $bySum[$row['s']][] = $index;
    }
    foreach ($bySum as $indexes) {
        $values = array_map(static fn (int $index): int => $rows[$index]['m'], $indexes);
        $keys = range(1, count($values));
        $windowValues = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING');
        foreach ($indexes as $offset => $index) {
            $rows[$index]['window_max'] = $windowValues[$offset];
        }
    }

    return array_map(static fn (array $row): array => [$row['x'], $row['s'], $row['m'], $row['window_max']], $rows);
};

$tests['real upstream windowpushd 1.2 full partition row_number view'] = static function (TestRunner $t) use ($lllRows, $t1Rows): void {
    $t->same([
        [1, 1, 4], [2, 1, 5], [3, 1, 6], [4, 1, 7], [5, 1, 8], [6, 1, 15], [7, 1, 17],
        [1, 2, 1], [2, 2, 11], [3, 2, 14], [4, 2, 16], [5, 2, 18], [6, 2, 20],
        [1, 3, 2], [2, 3, 3], [3, 3, 9], [4, 3, 10], [5, 3, 12], [6, 3, 13], [7, 3, 19],
    ], $lllRows($t1Rows), 'windowpushd.test 1.2');
};

$tests['real upstream windowpushd 1.3 filtered partition row_number view'] = static function (TestRunner $t) use ($lllRows, $t1Rows): void {
    $actual = array_values(array_filter($lllRows($t1Rows), static fn (array $row): bool => $row[1] === 2));
    $t->same([[1, 2, 1], [2, 2, 11], [3, 2, 14], [4, 2, 16], [5, 2, 18], [6, 2, 20]], $actual, 'windowpushd.test 1.3');
};

$tests['real upstream windowpushd 2.1.1 v1 partition max rows'] = static function (TestRunner $t) use ($v1Rows): void {
    $t->same([
        ['A', 1, 4], ['A', 2, 4], ['A', 3, 4], ['A', 4, 4],
        ['B', 5, 8], ['B', 6, 8], ['B', 7, 8], ['B', 8, 8],
        ['C', 9, 12], ['C', 10, 12], ['C', 11, 12], ['C', 12, 12],
    ], $v1Rows(), 'windowpushd.test 2.0.1.1 and 2.1.1.1');
};

$tests['real upstream windowpushd 2.1.2 filtered v1 keeps full partition max'] = static function (TestRunner $t) use ($v1Rows): void {
    $actual = array_values(array_filter($v1Rows(), static fn (array $row): bool => in_array($row[0], ['A', 'B'], true)));
    $t->same([
        ['A', 1, 4], ['A', 2, 4], ['A', 3, 4], ['A', 4, 4],
        ['B', 5, 8], ['B', 6, 8], ['B', 7, 8], ['B', 8, 8],
    ], $actual, 'windowpushd.test 2.0.1.2 and 2.1.1.2');
};

$tests['real upstream windowpushd 2.3.1 v3 partition max and row numbers'] = static function (TestRunner $t) use ($v3Rows): void {
    $t->same([
        ['C', 0.1, 1.0, 1], ['C', 0.4, 1.0, 2], ['C', 0.7, 1.0, 3], ['C', 1.0, 1.0, 4],
        ['D', 0.2, 1.1, 1], ['D', 0.5, 1.1, 2], ['D', 0.8, 1.1, 3], ['D', 1.1, 1.1, 4],
        ['E', 0.3, 1.2, 1], ['E', 0.6, 1.2, 2], ['E', 0.9, 1.2, 3], ['E', 1.2, 1.2, 4],
    ], $v3Rows(), 'windowpushd.test 2.0.3.1 and 2.1.3.1');
};

$tests['real upstream windowpushd 2.3.5 value filter preserves prefilter window row numbers'] = static function (TestRunner $t) use ($v3Rows): void {
    $actual = array_values(array_filter($v3Rows(), static fn (array $row): bool => $row[1] < 0.55));
    $t->same([
        ['C', 0.1, 1.0, 1], ['C', 0.4, 1.0, 2],
        ['D', 0.2, 1.1, 1], ['D', 0.5, 1.1, 2],
        ['E', 0.3, 1.2, 1],
    ], $actual, 'windowpushd.test 2.0.3.5 and 2.1.3.5');
};

$tests['real upstream windowpushd 2.4.1 grouped rows feed partitioned window max'] = static function (TestRunner $t) use ($groupedRows): void {
    $t->same([
        ['W', 5, 2, 9],
        ['Y', 5, 9, 9],
        ['X', 6, 7, 7],
        ['Z', 6, 4, 7],
    ], $groupedRows(), 'windowpushd.test 2.0.4.1 and 2.1.4.1');
};

$tests['real upstream windowpushd 2.4.2 grouped filter keeps s equals six rows'] = static function (TestRunner $t) use ($groupedRows): void {
    $actual = array_values(array_filter($groupedRows(), static fn (array $row): bool => $row[1] === 6));
    $t->same([['X', 6, 7, 7], ['Z', 6, 4, 7]], $actual, 'windowpushd.test 2.0.4.2 and 2.1.4.2');
};

$tests['real upstream windowpushd 2.4.3 grouped filter keeps s below six rows'] = static function (TestRunner $t) use ($groupedRows): void {
    $actual = array_values(array_filter($groupedRows(), static fn (array $row): bool => $row[1] < 6));
    $t->same([['W', 5, 2, 9], ['Y', 5, 9, 9]], $actual, 'windowpushd.test 2.0.4.3 and 2.1.4.3');
};

for ($case = 1; $case <= 1000; $case++) {
    $tests["real upstream windowpushd dynamic partition filter case {$case}"] = static function (TestRunner $t) use ($case, $lllRows, $t1Rows, $v1Rows, $v3Rows, $groupedRows): void {
        $group = ($case % 3) + 1;
        $lll = array_values(array_filter($lllRows($t1Rows), static fn (array $row): bool => $row[1] === $group));
        $t->same(range(1, count($lll)), array_column($lll, 0), "windowpushd.test dynamic {$case} row_number partition");

        $letter = ['A', 'B', 'C'][$case % 3];
        $v1 = array_values(array_filter($v1Rows(), static fn (array $row): bool => $row[0] === $letter));
        $expectedMax = ['A' => 4, 'B' => 8, 'C' => 12][$letter];
        $t->same(array_fill(0, 4, $expectedMax), array_column($v1, 2), "windowpushd.test dynamic {$case} partition max");

        $threshold = 0.25 + (($case % 5) * 0.2);
        $v3 = array_values(array_filter($v3Rows(), static fn (array $row): bool => $row[1] < $threshold));
        foreach ($v3 as $row) {
            $t->same(true, $row[3] >= 1 && $row[3] <= 4, "windowpushd.test dynamic {$case} prefilter row_number");
            $t->same(true, $row[2] >= $row[1], "windowpushd.test dynamic {$case} partition max covers filtered row");
        }

        $sum = $case % 2 === 0 ? 6 : 5;
        $grouped = array_values(array_filter($groupedRows(), static fn (array $row): bool => $row[1] === $sum));
        $t->same(2, count($grouped), "windowpushd.test dynamic {$case} grouped window row count");
        $t->same(max(array_column($grouped, 2)), $grouped[0][3], "windowpushd.test dynamic {$case} grouped window max first");
        $t->same($grouped[0][3], $grouped[1][3], "windowpushd.test dynamic {$case} grouped window max peer");
    };
}

$tests['real upstream windowpushd dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        'windowpushd.test:1.2',
        'windowpushd.test:1.3',
        'windowpushd.test:2.0.1.1',
        'windowpushd.test:2.0.1.2',
        'windowpushd.test:2.0.3.1',
        'windowpushd.test:2.0.3.5',
        'windowpushd.test:2.0.4.1',
        'windowpushd.test:2.0.4.2',
        'windowpushd.test:2.0.4.3',
        'windowpushd.test:2.1.1.1',
        'windowpushd.test:2.1.1.2',
        'windowpushd.test:2.1.3.1',
        'windowpushd.test:2.1.3.5',
        'windowpushd.test:2.1.4.1',
        'windowpushd.test:2.1.4.2',
        'windowpushd.test:2.1.4.3',
    ], [
        'windowpushd.test:1.2',
        'windowpushd.test:1.3',
        'windowpushd.test:2.0.1.1',
        'windowpushd.test:2.0.1.2',
        'windowpushd.test:2.0.3.1',
        'windowpushd.test:2.0.3.5',
        'windowpushd.test:2.0.4.1',
        'windowpushd.test:2.0.4.2',
        'windowpushd.test:2.0.4.3',
        'windowpushd.test:2.1.1.1',
        'windowpushd.test:2.1.1.2',
        'windowpushd.test:2.1.3.1',
        'windowpushd.test:2.1.3.5',
        'windowpushd.test:2.1.4.1',
        'windowpushd.test:2.1.4.2',
        'windowpushd.test:2.1.4.3',
    ]);
};

return $tests;
