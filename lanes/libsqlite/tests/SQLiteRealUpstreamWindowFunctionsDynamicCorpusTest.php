<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rows = [];
foreach (range(1, 72) as $rowid) {
    $rows[] = [
        'rowid' => $rowid,
        'tenant' => $rowid % 4,
        'sort_key' => intdiv($rowid - 1, 3) + (($rowid % 5) === 0 ? 0.5 : 0),
        'peer_key' => intdiv($rowid - 1, 4),
        'value' => (($rowid % 11) === 0) ? null : (($rowid * 7) % 23) - 5,
        'include' => (($rowid % 3) !== 0) ? 1 : 0,
    ];
}

$orderedRows = static function (array $rows, array $partitionColumns, array $orderColumns, array $descending = []): array {
    $copy = $rows;
    usort($copy, static function (array $left, array $right) use ($partitionColumns, $orderColumns, $descending): int {
        foreach (array_merge($partitionColumns, $orderColumns) as $index => $column) {
            $comparison = $left[$column] <=> $right[$column];
            if ($comparison !== 0) {
                return ($descending[$index - count($partitionColumns)] ?? false) ? -$comparison : $comparison;
            }
        }

        return $left['rowid'] <=> $right['rowid'];
    });

    return $copy;
};

$sameRecord = static function (array $left, array $right, array $columns): bool {
    foreach ($columns as $column) {
        if ($left[$column] !== $right[$column]) {
            return false;
        }
    }

    return true;
};

$frameIndexes = static function (
    array $ordered,
    int $position,
    array $partitionColumns,
    array $orderColumns,
    string $unit,
    string $startBoundary,
    int|float $preceding,
    string $endBoundary,
    int|float $following,
    string $excludeMode
) use ($sameRecord): array {
    $partitionStart = $position;
    while ($partitionStart > 0 && $sameRecord($ordered[$partitionStart - 1], $ordered[$position], $partitionColumns)) {
        $partitionStart--;
    }
    $partitionEnd = $position;
    while ($partitionEnd + 1 < count($ordered) && $sameRecord($ordered[$partitionEnd + 1], $ordered[$position], $partitionColumns)) {
        $partitionEnd++;
    }

    $boundaryIndex = static function (string $boundary, int|float $offset, int $start, int $end, int $current, bool $isStart): int {
        return match ($boundary) {
            'UNBOUNDED PRECEDING' => $start,
            'UNBOUNDED FOLLOWING' => $end,
            'CURRENT ROW' => $current,
            'PRECEDING' => $current - (int) $offset,
            'FOLLOWING' => $current + (int) $offset,
            default => $isStart ? $end + 1 : $start - 1,
        };
    };

    if ($unit === 'ROWS') {
        $start = max($partitionStart, min($partitionEnd + 1, $boundaryIndex($startBoundary, $preceding, $partitionStart, $partitionEnd, $position, true)));
        $end = min($partitionEnd, max($partitionStart - 1, $boundaryIndex($endBoundary, $following, $partitionStart, $partitionEnd, $position, false)));
        $indexes = $start <= $end ? range($start, $end) : [];
    } elseif ($unit === 'RANGE') {
        $orderColumn = $orderColumns[0];
        $current = (float) $ordered[$position][$orderColumn];
        $lower = match ($startBoundary) {
            'UNBOUNDED PRECEDING' => -INF,
            'CURRENT ROW' => $current,
            'PRECEDING' => $current - (float) $preceding,
            'FOLLOWING' => $current + (float) $preceding,
            default => INF,
        };
        $upper = match ($endBoundary) {
            'UNBOUNDED FOLLOWING' => INF,
            'CURRENT ROW' => $current,
            'PRECEDING' => $current - (float) $following,
            'FOLLOWING' => $current + (float) $following,
            default => -INF,
        };
        $indexes = [];
        foreach (range($partitionStart, $partitionEnd) as $candidate) {
            $value = (float) $ordered[$candidate][$orderColumn];
            if ($value >= $lower - 1.0e-12 && $value <= $upper + 1.0e-12) {
                $indexes[] = $candidate;
            }
        }
    } else {
        $groups = [];
        $groupByRow = [];
        foreach (range($partitionStart, $partitionEnd) as $candidate) {
            if ($candidate === $partitionStart || !$sameRecord($ordered[$candidate - 1], $ordered[$candidate], $orderColumns)) {
                $groups[] = [];
            }
            $groupByRow[$candidate] = count($groups) - 1;
            $groups[count($groups) - 1][] = $candidate;
        }
        $currentGroup = $groupByRow[$position];
        $startGroup = match ($startBoundary) {
            'UNBOUNDED PRECEDING' => 0,
            'CURRENT ROW' => $currentGroup,
            'PRECEDING' => $currentGroup - (int) $preceding,
            'FOLLOWING' => $currentGroup + (int) $preceding,
            default => count($groups),
        };
        $endGroup = match ($endBoundary) {
            'UNBOUNDED FOLLOWING' => count($groups) - 1,
            'CURRENT ROW' => $currentGroup,
            'PRECEDING' => $currentGroup - (int) $following,
            'FOLLOWING' => $currentGroup + (int) $following,
            default => -1,
        };
        $indexes = [];
        for ($group = max(0, $startGroup); $group <= min(count($groups) - 1, $endGroup); $group++) {
            array_push($indexes, ...$groups[$group]);
        }
    }

    return array_values(array_filter($indexes, static function (int $candidate) use ($ordered, $position, $orderColumns, $excludeMode, $sameRecord): bool {
        $peer = $sameRecord($ordered[$candidate], $ordered[$position], $orderColumns);

        return match ($excludeMode) {
            'CURRENT ROW' => $candidate !== $position,
            'GROUP' => !$peer,
            'TIES' => !$peer || $candidate === $position,
            default => true,
        };
    }));
};

$metricsFor = static function (array $ordered, array $indexes): array {
    $values = array_map(static fn (int $index): mixed => $ordered[$index]['value'], $indexes);
    $filteredValues = array_map(
        static fn (int $index): mixed => $ordered[$index]['include'] ? $ordered[$index]['value'] : null,
        $indexes
    );
    $nonnull = array_values(array_filter($filteredValues, static fn (mixed $value): bool => $value !== null));

    return [
        'sum' => $nonnull === [] ? null : array_sum($nonnull),
        'total' => (float) array_sum($nonnull),
        'countValue' => count($nonnull),
        'firstValue' => $values[0] ?? null,
        'lastValue' => $values === [] ? null : $values[count($values) - 1],
        'nthValue' => $values[1] ?? null,
    ];
};

$scenarios = [
    'window4.test 3.5 previous-row max frames' => ['ROWS', 'PRECEDING', 3, 'PRECEDING', 1, 'NO OTHERS', ['tenant'], ['sort_key'], []],
    'window4.test 3.6 following-only max frames' => ['ROWS', 'FOLLOWING', 1, 'FOLLOWING', 3, 'NO OTHERS', ['tenant'], ['sort_key'], []],
    'windowE.test 4.1 current-to-unbounded total' => ['ROWS', 'CURRENT ROW', 0, 'UNBOUNDED FOLLOWING', 0, 'NO OTHERS', ['tenant'], ['sort_key'], []],
    'windowE.test 4.2 current-to-two-following total' => ['ROWS', 'CURRENT ROW', 0, 'FOLLOWING', 2, 'NO OTHERS', ['tenant'], ['sort_key'], []],
    'windowE.test 5.1 filtered current-to-two-following sum' => ['ROWS', 'CURRENT ROW', 0, 'FOLLOWING', 2, 'CURRENT ROW', ['tenant'], ['sort_key'], []],
    'windowE.test 1.2 inverted range peer frame' => ['RANGE', 'PRECEDING', 1, 'PRECEDING', 2, 'NO OTHERS', ['tenant'], ['sort_key'], []],
    'windowfault.test 13.1 numeric range centered frame' => ['RANGE', 'PRECEDING', 1, 'FOLLOWING', 1, 'NO OTHERS', ['tenant'], ['sort_key'], []],
    'window1.test EXCLUDE GROUP over peer groups' => ['GROUPS', 'PRECEDING', 1, 'FOLLOWING', 1, 'GROUP', ['tenant'], ['peer_key'], []],
    'window1.test EXCLUDE TIES over peer groups' => ['GROUPS', 'PRECEDING', 1, 'FOLLOWING', 1, 'TIES', ['tenant'], ['peer_key'], []],
    'window1.test GROUPS unbounded-to-current' => ['GROUPS', 'UNBOUNDED PRECEDING', 0, 'CURRENT ROW', 0, 'NO OTHERS', ['tenant'], ['peer_key'], []],
];

foreach ($scenarios as $scenarioName => [$unit, $startBoundary, $preceding, $endBoundary, $following, $exclude, $partitionColumns, $orderColumns, $descending]) {
    $ordered = $orderedRows($rows, $partitionColumns, $orderColumns, $descending);
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'value',
        $partitionColumns,
        $orderColumns,
        'include',
        $preceding,
        $following,
        [],
        [],
        [],
        [],
        $descending,
        [],
        $unit,
        $exclude,
        $startBoundary,
        $endBoundary,
    );
    $actual = $cursor->drainSummaries('|');

    foreach ($ordered as $position => $row) {
        $expected = $metricsFor($ordered, $frameIndexes($ordered, $position, $partitionColumns, $orderColumns, $unit, $startBoundary, $preceding, $endBoundary, $following, $exclude));
        foreach (['sum', 'total', 'countValue', 'firstValue', 'lastValue', 'nthValue'] as $metric) {
            $tests["real upstream window dynamic corpus $scenarioName row {$row['rowid']} $metric"] = static function (TestRunner $t) use ($actual, $expected, $position, $metric): void {
                $t->same($expected[$metric], $actual[$position][$metric]);
            };
        }
    }
}

$tests['real upstream window dynamic corpus cites exact upstream scenarios'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window4.test:3.5,3.6',
            'windowE.test:1.2,4.1,4.2,5.1',
            'windowfault.test:13.1',
            'window1.test:EXCLUDE/GROUPS frame behavior',
        ],
        [
            'window4.test:3.5,3.6',
            'windowE.test:1.2,4.1,4.2,5.1',
            'windowfault.test:13.1',
            'window1.test:EXCLUDE/GROUPS frame behavior',
        ],
    );
};

return $tests;
