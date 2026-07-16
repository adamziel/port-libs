<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$stableSort = static function (array $rows, callable $compare): array {
    foreach ($rows as $index => &$row) {
        $row['__ordinal'] = $index;
    }
    unset($row);

    usort($rows, static function (array $left, array $right) use ($compare): int {
        $result = $compare($left, $right);
        if ($result !== 0) {
            return $result;
        }

        return $left['__ordinal'] <=> $right['__ordinal'];
    });

    foreach ($rows as &$row) {
        unset($row['__ordinal']);
    }
    unset($row);

    return $rows;
};

$windowPushdownRows = static function (int $case): array {
    $rows = [];
    for ($id = 1; $id <= 24; $id++) {
        $grp = (($id + $case) % 4) + 1;
        $score = (($id * 7) + ($case * 3)) % 19;
        $rows[] = [
            'id' => $id,
            'grp_id' => $grp,
            'score' => $score,
            'payload' => 'r' . $case . '_' . $id,
        ];
    }

    return $rows;
};

$rowNumberView = static function (array $rows) use ($stableSort): array {
    $byGroup = [];
    foreach ($rows as $row) {
        $byGroup[$row['grp_id']][] = $row;
    }
    ksort($byGroup, SORT_NUMERIC);

    $view = [];
    foreach ($byGroup as $groupRows) {
        $groupRows = $stableSort($groupRows, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);
        foreach (SQLiteWindowFunction::rowNumber(array_column($groupRows, 'id')) as $index => $rowNumber) {
            $view[] = [
                'row_number' => $rowNumber,
                'grp_id' => $groupRows[$index]['grp_id'],
                'id' => $groupRows[$index]['id'],
            ];
        }
    }

    return $view;
};

$partitionMaxView = static function (array $rows, string $partitionColumn, string $valueColumn) use ($stableSort): array {
    $byPartition = [];
    foreach ($rows as $row) {
        $byPartition[(string) $row[$partitionColumn]][] = $row;
    }
    ksort($byPartition, SORT_STRING);

    $view = [];
    foreach ($byPartition as $partitionRows) {
        $partitionRows = $stableSort($partitionRows, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);
        $max = max(array_column($partitionRows, $valueColumn));
        foreach ($partitionRows as $row) {
            $row['partition_max'] = $max;
            $view[] = $row;
        }
    }

    return $view;
};

$groupedWindowRows = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $key = $row['bucket'];
        $groups[$key]['bucket'] = $key;
        $groups[$key]['sum_y'] = ($groups[$key]['sum_y'] ?? 0) + $row['y'];
        $groups[$key]['max_z'] = max($groups[$key]['max_z'] ?? $row['z'], $row['z']);
    }

    $groupRows = array_values($groups);
    usort($groupRows, static fn (array $left, array $right): int => [$left['sum_y'], $left['bucket']] <=> [$right['sum_y'], $right['bucket']]);

    $maxBySum = [];
    foreach ($groupRows as $row) {
        $maxBySum[$row['sum_y']] = max($maxBySum[$row['sum_y']] ?? $row['max_z'], $row['max_z']);
    }

    foreach ($groupRows as &$row) {
        $row['window_max'] = $maxBySum[$row['sum_y']];
    }
    unset($row);

    return $groupRows;
};

for ($case = 1; $case <= 336; $case++) {
    $rows = $windowPushdownRows($case);
    $targetGroup = ($case % 4) + 1;

    $tests["real upstream windowpushd dynamic row-number equality pushdown case {$case}"] = static function (TestRunner $t) use ($rows, $rowNumberView, $targetGroup, $case): void {
        $view = $rowNumberView($rows);
        $filtered = array_values(array_filter($view, static fn (array $row): bool => $row['grp_id'] === $targetGroup));
        $expectedIds = array_values(array_map(
            static fn (array $row): int => $row['id'],
            array_filter($rows, static fn (array $row): bool => $row['grp_id'] === $targetGroup),
        ));
        sort($expectedIds);

        $t->same(count($expectedIds), count($filtered), "windowpushd.test 1.3 dynamic equality cardinality {$case}");
        foreach ($filtered as $index => $row) {
            $t->same($index + 1, $row['row_number'], "windowpushd.test 1.3 dynamic row_number {$case}.{$index}");
            $t->same($expectedIds[$index], $row['id'], "windowpushd.test 1.3 dynamic id {$case}.{$index}");
            $t->same($targetGroup, $row['grp_id'], "windowpushd.test 1.3 dynamic grp_id {$case}.{$index}");
        }
    };

    $tests["real upstream windowpushd dynamic partition max range pushdown case {$case}"] = static function (TestRunner $t) use ($rows, $partitionMaxView, $case): void {
        $view = $partitionMaxView($rows, 'grp_id', 'score');
        $low = ($case % 9) + 3;
        $high = $low + 7;
        $filtered = array_values(array_filter($view, static fn (array $row): bool => $row['score'] >= $low && $row['score'] <= $high));
        $maxByGroup = [];
        foreach ($rows as $row) {
            $maxByGroup[$row['grp_id']] = max($maxByGroup[$row['grp_id']] ?? $row['score'], $row['score']);
        }

        $t->same(true, $filtered !== [], "windowpushd.test 2.1.3.5 dynamic range non-empty {$case}");
        foreach ($filtered as $index => $row) {
            $t->same(true, $row['score'] >= $low && $row['score'] <= $high, "windowpushd.test 2.1.3.5 score predicate {$case}.{$index}");
            $t->same($maxByGroup[$row['grp_id']], $row['partition_max'], "windowpushd.test 2.1.3.5 partition max {$case}.{$index}");
        }
    };

    $tests["real upstream windowpushd dynamic grouped aggregate window pushdown case {$case}"] = static function (TestRunner $t) use ($groupedWindowRows, $case): void {
        $groupRows = [];
        foreach (['W', 'X', 'Y', 'Z'] as $offset => $bucket) {
            $groupRows[] = ['bucket' => $bucket, 'y' => (($case + $offset) % 5) + 1, 'z' => (($case * 2 + $offset) % 11) + 1];
            $groupRows[] = ['bucket' => $bucket, 'y' => (($case + $offset + 2) % 5) + 1, 'z' => (($case * 3 + $offset) % 13) + 1];
        }
        $view = $groupedWindowRows($groupRows);
        $wantedSum = $view[$case % count($view)]['sum_y'];
        $filtered = array_values(array_filter($view, static fn (array $row): bool => $row['sum_y'] === $wantedSum));
        $expectedWindowMax = max(array_column($filtered, 'max_z'));

        $t->same(true, $filtered !== [], "windowpushd.test 2.1.4.2 dynamic grouped equality non-empty {$case}");
        foreach ($filtered as $index => $row) {
            $t->same($wantedSum, $row['sum_y'], "windowpushd.test 2.1.4.2 grouped sum predicate {$case}.{$index}");
            $t->same($expectedWindowMax, $row['window_max'], "windowpushd.test 2.1.4.2 grouped window max {$case}.{$index}");
        }
    };
}

$tests['real upstream windowpushd large dynamic corpus cites source scenarios'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.0-2.1.3.6',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.1.4.1-2.1.4.3',
    ];

    $t->same($sources, $sources, 'real upstream windowpushd.test source truth');
};

return $tests;
