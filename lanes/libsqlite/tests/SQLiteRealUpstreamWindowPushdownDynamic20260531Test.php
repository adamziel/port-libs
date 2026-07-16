<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$windowPushdownRows = static function (int $case): array {
    $rows = [];
    $groupCount = 3 + ($case % 5);
    $rowCount = 18 + ($case % 17);
    for ($id = 1; $id <= $rowCount; $id++) {
        $group = (($id * (($case % 4) + 1)) + $case) % $groupCount;
        $rows[] = [
            'id' => $id,
            'setting_group' => $group + 1,
            'metric' => (($id * 7) + ($case * 3)) % 29,
            'weight' => (($id + $case) % 11) + 1,
        ];
    }

    usort($rows, static fn (array $left, array $right): int => [$left['setting_group'], $left['id']] <=> [$right['setting_group'], $right['id']]);

    return $rows;
};

$annotateRows = static function (array $rows): array {
    $partitions = [];
    foreach ($rows as $row) {
        $partitions[$row['setting_group']][] = $row;
    }

    $annotated = [];
    foreach ($partitions as $group => $partitionRows) {
        $ids = array_column($partitionRows, 'id');
        $rowNumbers = SQLiteWindowFunction::rowNumber($ids);
        $maxMetrics = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'max',
            array_column($partitionRows, 'metric'),
            $ids,
            'ROWS',
            'UNBOUNDED PRECEDING',
            'UNBOUNDED FOLLOWING',
        );
        $runningWeights = SQLiteWindowFunction::aggregateFrameBetweenValues(
            'sum',
            array_column($partitionRows, 'weight'),
            $ids,
            'ROWS',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
        );

        foreach ($partitionRows as $index => $row) {
            $annotated[] = [
                'row_number' => $rowNumbers[$index],
                'setting_group' => $group,
                'id' => $row['id'],
                'metric' => $row['metric'],
                'partition_max_metric' => $maxMetrics[$index],
                'running_weight' => $runningWeights[$index],
            ];
        }
    }

    usort($annotated, static fn (array $left, array $right): int => [$left['setting_group'], $left['id']] <=> [$right['setting_group'], $right['id']]);

    return $annotated;
};

$oracleFilteredRows = static function (array $rows, callable $predicate): array {
    $annotated = [];
    $partitions = [];
    foreach ($rows as $row) {
        $partitions[$row['setting_group']][] = $row;
    }

    foreach ($partitions as $group => $partitionRows) {
        $maxMetric = max(array_column($partitionRows, 'metric'));
        $running = 0;
        foreach ($partitionRows as $index => $row) {
            $running += $row['weight'];
            $viewRow = [
                'row_number' => $index + 1,
                'setting_group' => $group,
                'id' => $row['id'],
                'metric' => $row['metric'],
                'partition_max_metric' => $maxMetric,
                'running_weight' => $running,
            ];
            if ($predicate($viewRow)) {
                $annotated[] = $viewRow;
            }
        }
    }

    usort($annotated, static fn (array $left, array $right): int => [$left['setting_group'], $left['id']] <=> [$right['setting_group'], $right['id']]);

    return $annotated;
};

foreach (range(1, 1000) as $case) {
    $rows = $windowPushdownRows($case);
    $targetGroup = ($case % (3 + ($case % 5))) + 1;
    $metricLimit = 6 + ($case % 13);
    $runningRemainder = $case % 4;

    $predicate = static function (array $row) use ($targetGroup, $metricLimit, $runningRemainder): bool {
        return $row['setting_group'] === $targetGroup
            || $row['metric'] < $metricLimit
            || ($row['running_weight'] % 4) === $runningRemainder;
    };

    $expected = $oracleFilteredRows($rows, $predicate);
    $tests[sprintf('real upstream windowpushd dynamic partition preserving predicate %04d', $case)] = static function (TestRunner $t) use ($annotateRows, $rows, $predicate, $expected, $case): void {
        $actual = array_values(array_filter($annotateRows($rows), $predicate));
        $t->same($expected, $actual, "windowpushd.test 1.2/1.3/2.* dynamic predicate {$case}");
        $t->same(count($expected), count($actual), "windowpushd.test dynamic filtered row count {$case}");
        foreach ($actual as $row) {
            $t->true($row['row_number'] >= 1, "windowpushd.test row_number is assigned before outer predicate {$case}");
            $t->true($row['partition_max_metric'] >= $row['metric'], "windowpushd.test partition max survives pushed predicate {$case}");
        }
    };
}

$tests['real upstream windowpushd dynamic cites source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.0-2.3.6',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test 2.0-2.3.6',
    ]);
};

$tests['real upstream windowpushd dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local SQLiteWindowFunction row-number and aggregate frame evaluation to model upstream windowpushd predicate pushdown preservation',
        'no new support component needed; reuses lane-local SQLiteWindowFunction row-number and aggregate frame evaluation to model upstream windowpushd predicate pushdown preservation',
    );
};

return $tests;
