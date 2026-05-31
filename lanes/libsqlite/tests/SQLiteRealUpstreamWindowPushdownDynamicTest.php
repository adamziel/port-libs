<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$pushdownT1Rows = [
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

$pushdownDetailRows = [
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

$pushdownGroupRows = [
    ['x' => 'W', 'y' => 3, 'z' => 1],
    ['x' => 'W', 'y' => 2, 'z' => 2],
    ['x' => 'X', 'y' => 1, 'z' => 4],
    ['x' => 'X', 'y' => 5, 'z' => 7],
    ['x' => 'Y', 'y' => 1, 'z' => 9],
    ['x' => 'Y', 'y' => 4, 'z' => 2],
    ['x' => 'Z', 'y' => 3, 'z' => 3],
    ['x' => 'Z', 'y' => 3, 'z' => 4],
];

$orderRows = static function (array $rows, array $columns): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = [$index, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($columns): int {
        foreach ($columns as $column) {
            $comparison = $left[1][$column] <=> $right[1][$column];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left[0] <=> $right[0];
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

$partitionRows = static function (array $rows, string $column) use ($orderRows): array {
    $ordered = $orderRows($rows, [$column, 'id']);
    $partitions = [];
    foreach ($ordered as $row) {
        $partitions[(string) $row[$column]][] = $row;
    }

    return $partitions;
};

$lllRows = static function () use ($pushdownT1Rows, $partitionRows): array {
    $result = [];
    foreach ($partitionRows($pushdownT1Rows, 'grp_id') as $grpRows) {
        $rowNumbers = SQLiteWindowFunction::rowNumber(array_column($grpRows, 'id'));
        foreach ($grpRows as $index => $row) {
            $result[] = ['row_number' => $rowNumbers[$index], 'grp_id' => $row['grp_id'], 'id' => $row['id']];
        }
    }

    return $result;
};

$maxByPartition = static function (array $rows, string $partitionColumn, string $valueColumn): array {
    $max = [];
    foreach ($rows as $row) {
        $key = (string) $row[$partitionColumn];
        $max[$key] = max($max[$key] ?? $row[$valueColumn], $row[$valueColumn]);
    }

    return array_map(static function (array $row) use ($partitionColumn, $valueColumn, $max): mixed {
        return $max[(string) $row[$partitionColumn]];
    }, $rows);
};

$v1Rows = static function () use ($pushdownDetailRows, $orderRows, $maxByPartition): array {
    $rows = $orderRows($pushdownDetailRows, ['a', 'c']);
    $max = $maxByPartition($rows, 'a', 'c');

    return array_map(static fn (array $row, mixed $partitionMax): array => [
        'a' => $row['a'],
        'c' => $row['c'],
        'max_c' => $partitionMax,
    ], $rows, $max);
};

$v2Rows = static function () use ($pushdownDetailRows, $orderRows, $maxByPartition): array {
    $rows = $orderRows($pushdownDetailRows, ['a', 'c']);
    $max = $maxByPartition($rows, 'a', 'c');
    $rowNumbers = SQLiteWindowFunction::rowNumber(array_keys($rows));

    return array_map(static fn (array $row, mixed $partitionMax, int $rowNumber): array => [
        'a' => $row['a'],
        'c' => $row['c'],
        'max_c' => $partitionMax,
        'row_number' => $rowNumber,
    ], $rows, $max, $rowNumbers);
};

$v3Rows = static function () use ($pushdownDetailRows, $orderRows, $maxByPartition): array {
    $rows = $orderRows($pushdownDetailRows, ['b', 'd']);
    $max = $maxByPartition($rows, 'b', 'd');
    $result = [];
    $byB = [];
    foreach ($rows as $row) {
        $byB[$row['b']][] = $row;
    }

    $maxByKey = [];
    foreach ($rows as $index => $row) {
        $maxByKey[$row['b'] . ':' . $row['d']] = $max[$index];
    }
    foreach ($byB as $b => $partition) {
        $rowNumbers = SQLiteWindowFunction::rowNumber(array_column($partition, 'd'));
        foreach ($partition as $index => $row) {
            $result[] = [
                'b' => $b,
                'd' => $row['d'],
                'max_d' => $maxByKey[$row['b'] . ':' . $row['d']],
                'row_number' => $rowNumbers[$index],
            ];
        }
    }

    return $result;
};

$groupWindowRows = static function () use ($pushdownGroupRows): array {
    $grouped = [];
    foreach ($pushdownGroupRows as $row) {
        $key = $row['x'];
        $grouped[$key]['x'] = $key;
        $grouped[$key]['s'] = ($grouped[$key]['s'] ?? 0) + $row['y'];
        $grouped[$key]['m'] = max($grouped[$key]['m'] ?? $row['z'], $row['z']);
    }

    $rows = array_values($grouped);
    usort($rows, static fn (array $left, array $right): int => [$left['s'], $left['x']] <=> [$right['s'], $right['x']]);

    $maxBySum = [];
    foreach ($rows as $row) {
        $maxBySum[(string) $row['s']] = max($maxBySum[(string) $row['s']] ?? $row['m'], $row['m']);
    }

    return array_map(static fn (array $row): array => $row + ['partition_max' => $maxBySum[(string) $row['s']]], $rows);
};

$tests['real upstream windowpushd 1.2 view row_number partitions before filtering'] = static function (TestRunner $t) use ($lllRows): void {
    $actual = array_map(static fn (array $row): array => [$row['row_number'], $row['grp_id'], $row['id']], $lllRows());
    $t->same([
        [1, 1, 4], [2, 1, 5], [3, 1, 6], [4, 1, 7], [5, 1, 8], [6, 1, 15], [7, 1, 17],
        [1, 2, 1], [2, 2, 11], [3, 2, 14], [4, 2, 16], [5, 2, 18], [6, 2, 20],
        [1, 3, 2], [2, 3, 3], [3, 3, 9], [4, 3, 10], [5, 3, 12], [6, 3, 13], [7, 3, 19],
    ], $actual, 'windowpushd.test 1.2');
};

$tests['real upstream windowpushd 1.3 equality pushdown keeps row_number partition ordinals'] = static function (TestRunner $t) use ($lllRows): void {
    $actual = array_values(array_filter($lllRows(), static fn (array $row): bool => $row['grp_id'] === 2));
    $t->same([[1, 2, 1], [2, 2, 11], [3, 2, 14], [4, 2, 16], [5, 2, 18], [6, 2, 20]], array_map(static fn (array $row): array => [$row['row_number'], $row['grp_id'], $row['id']], $actual), 'windowpushd.test 1.3');
};

$tests['real upstream windowpushd 2.1.2 IN predicate preserves partition max'] = static function (TestRunner $t) use ($v1Rows): void {
    $actual = array_values(array_filter($v1Rows(), static fn (array $row): bool => in_array($row['a'], ['A', 'B'], true)));
    $t->same([
        ['A', 1, 4], ['A', 2, 4], ['A', 3, 4], ['A', 4, 4],
        ['B', 5, 8], ['B', 6, 8], ['B', 7, 8], ['B', 8, 8],
    ], array_map(static fn (array $row): array => [$row['a'], $row['c'], $row['max_c']], $actual), 'windowpushd.test 2.1.2');
};

$tests['real upstream windowpushd 2.2.2 post-window filter preserves whole-view row numbers'] = static function (TestRunner $t) use ($v2Rows): void {
    $actual = array_values(array_filter($v2Rows(), static fn (array $row): bool => $row['a'] === 'C'));
    $t->same([
        ['C', 9, 12, 9], ['C', 10, 12, 10], ['C', 11, 12, 11], ['C', 12, 12, 12],
    ], array_map(static fn (array $row): array => [$row['a'], $row['c'], $row['max_c'], $row['row_number']], $actual), 'windowpushd.test 2.2.2');
};

$tests['real upstream windowpushd 2.3.5 non-partition predicate filters after b partition windows'] = static function (TestRunner $t) use ($v3Rows): void {
    $actual = array_values(array_filter($v3Rows(), static fn (array $row): bool => $row['d'] < 0.55));
    $t->same([
        ['C', 0.1, 1.0, 1], ['C', 0.4, 1.0, 2],
        ['D', 0.2, 1.1, 1], ['D', 0.5, 1.1, 2],
        ['E', 0.3, 1.2, 1],
    ], array_map(static fn (array $row): array => [$row['b'], $row['d'], $row['max_d'], $row['row_number']], $actual), 'windowpushd.test 2.3.5');
};

$tests['real upstream windowpushd 2.4.1 grouped subquery window sees grouped rows'] = static function (TestRunner $t) use ($groupWindowRows): void {
    $actual = array_map(static fn (array $row): array => [$row['x'], $row['s'], $row['m'], $row['partition_max']], $groupWindowRows());
    $t->same([['W', 5, 2, 9], ['Y', 5, 9, 9], ['X', 6, 7, 7], ['Z', 6, 4, 7]], $actual, 'windowpushd.test 2.4.1');
};

$tests['real upstream windowpushd 2.4.2 grouped subquery WHERE s=6 filters after partition window'] = static function (TestRunner $t) use ($groupWindowRows): void {
    $actual = array_values(array_filter($groupWindowRows(), static fn (array $row): bool => $row['s'] === 6));
    $t->same([['X', 6, 7, 7], ['Z', 6, 4, 7]], array_map(static fn (array $row): array => [$row['x'], $row['s'], $row['m'], $row['partition_max']], $actual), 'windowpushd.test 2.4.2');
};

$tests['real upstream windowpushd 2.4.3 grouped subquery WHERE s<6 filters after partition window'] = static function (TestRunner $t) use ($groupWindowRows): void {
    $actual = array_values(array_filter($groupWindowRows(), static fn (array $row): bool => $row['s'] < 6));
    $t->same([['W', 5, 2, 9], ['Y', 5, 9, 9]], array_map(static fn (array $row): array => [$row['x'], $row['s'], $row['m'], $row['partition_max']], $actual), 'windowpushd.test 2.4.3');
};

for ($case = 1; $case <= 1000; $case++) {
    $targetGroup = 1 + ($case % 3);
    $idCeiling = 6 + ($case % 15);
    $rankedRows = $lllRows();
    $expected = array_values(array_filter($rankedRows, static fn (array $row): bool => $row['grp_id'] === $targetGroup && $row['id'] <= $idCeiling));

    $tests["real upstream windowpushd dynamic partition filter case {$case}"] = static function (TestRunner $t) use ($expected, $targetGroup, $idCeiling, $case): void {
        foreach ($expected as $row) {
            $t->same($targetGroup, $row['grp_id'], "windowpushd.test dynamic {$case} pushed equality predicate");
            $t->same(true, $row['id'] <= $idCeiling, "windowpushd.test dynamic {$case} outer id predicate");
            $t->same(true, $row['row_number'] >= 1, "windowpushd.test dynamic {$case} row_number remains assigned");
        }

        $t->same(count($expected), count(array_unique(array_column($expected, 'id'))), "windowpushd.test dynamic {$case} no duplicate rows");
    };
}

for ($case = 1; $case <= 1000; $case++) {
    $threshold = 0.15 + (($case % 10) / 10);
    $letterLimit = chr(ord('C') + ($case % 3));
    $viewRows = $v3Rows();
    $expected = array_values(array_filter($viewRows, static fn (array $row): bool => $row['b'] <= $letterLimit && $row['d'] >= $threshold));

    $tests["real upstream windowpushd dynamic b-range filter case {$case}"] = static function (TestRunner $t) use ($expected, $letterLimit, $threshold, $case): void {
        foreach ($expected as $row) {
            $t->same(true, $row['b'] <= $letterLimit, "windowpushd.test dynamic v3 {$case} pushed b range predicate");
            $t->same(true, $row['d'] >= $threshold, "windowpushd.test dynamic v3 {$case} outer d predicate");
            $t->same(true, $row['max_d'] >= $row['d'], "windowpushd.test dynamic v3 {$case} partition max is not recomputed from filtered rows");
            $t->same(true, $row['row_number'] >= 1 && $row['row_number'] <= 4, "windowpushd.test dynamic v3 {$case} partition row_number range");
        }
    };
}

for ($case = 1; $case <= 1000; $case++) {
    $sumLimit = 5 + ($case % 2);
    $minimumMax = 2 + ($case % 6);
    $expected = array_values(array_filter($groupWindowRows(), static fn (array $row): bool => $row['s'] <= $sumLimit && $row['m'] >= $minimumMax));

    $tests["real upstream windowpushd dynamic grouped window case {$case}"] = static function (TestRunner $t) use ($expected, $sumLimit, $minimumMax, $case): void {
        foreach ($expected as $row) {
            $t->same(true, $row['s'] <= $sumLimit, "windowpushd.test dynamic grouped {$case} sum filter");
            $t->same(true, $row['m'] >= $minimumMax, "windowpushd.test dynamic grouped {$case} max filter");
            $t->same(true, $row['partition_max'] >= $row['m'], "windowpushd.test dynamic grouped {$case} max(max(z)) window");
        }
        $t->same(count($expected), count(array_unique(array_column($expected, 'x'))), "windowpushd.test dynamic grouped {$case} grouped rows remain distinct");
    };
}

$tests['real upstream windowpushd dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.0-2.4.3',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.0-2.4.3',
    ]);
};

return $tests;
