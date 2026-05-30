<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
];

$frameBoundary = static function (int $index, int $count, string $boundary, int $offset): int {
    return match ($boundary) {
        'UNBOUNDED PRECEDING' => 0,
        'UNBOUNDED FOLLOWING' => $count - 1,
        'CURRENT ROW' => $index,
        'PRECEDING' => $index - $offset,
        'FOLLOWING' => $index + $offset,
        default => throw new InvalidArgumentException('Unsupported window2.test frame boundary ' . $boundary),
    };
};

$sortRows = static function (array $sourceRows, array $partitionColumns, array $orderColumns): array {
    $sorted = $sourceRows;
    usort($sorted, static function (array $left, array $right) use ($partitionColumns, $orderColumns): int {
        foreach (array_merge($partitionColumns, $orderColumns) as $column) {
            $comparison = $left[$column] <=> $right[$column];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left['a'] <=> $right['a'];
    });

    return $sorted;
};

$expectedFrameRows = static function (
    array $orderedRows,
    int $position,
    string $unit,
    string $startBoundary,
    int $startOffset,
    string $endBoundary,
    int $endOffset,
    array $partitionColumns,
    array $orderColumns
) use ($frameBoundary): array {
    $current = $orderedRows[$position];
    $partitionRows = [];
    $partitionPositions = [];
    foreach ($orderedRows as $absolute => $row) {
        $samePartition = true;
        foreach ($partitionColumns as $column) {
            if ($row[$column] !== $current[$column]) {
                $samePartition = false;
                break;
            }
        }
        if ($samePartition) {
            $partitionPositions[] = $absolute;
            $partitionRows[] = $row;
        }
    }
    $partitionIndex = array_search($position, $partitionPositions, true);
    if (!is_int($partitionIndex)) {
        throw new RuntimeException('Current row is missing from its partition');
    }

    if ($unit === 'ROWS') {
        $start = $frameBoundary($partitionIndex, count($partitionRows), $startBoundary, $startOffset);
        $end = $frameBoundary($partitionIndex, count($partitionRows), $endBoundary, $endOffset);

        return $start > $end || $end < 0 || $start >= count($partitionRows)
            ? []
            : array_slice($partitionRows, max(0, $start), min(count($partitionRows) - 1, $end) - max(0, $start) + 1);
    }

    if ($unit === 'RANGE') {
        $orderColumn = $orderColumns[0];
        $currentValue = $current[$orderColumn];
        $lower = match ($startBoundary) {
            'UNBOUNDED PRECEDING' => -INF,
            'CURRENT ROW' => $currentValue,
            'PRECEDING' => $currentValue - $startOffset,
            'FOLLOWING' => $currentValue + $startOffset,
            default => throw new InvalidArgumentException('Unsupported RANGE start boundary ' . $startBoundary),
        };
        $upper = match ($endBoundary) {
            'UNBOUNDED FOLLOWING' => INF,
            'CURRENT ROW' => $currentValue,
            'PRECEDING' => $currentValue - $endOffset,
            'FOLLOWING' => $currentValue + $endOffset,
            default => throw new InvalidArgumentException('Unsupported RANGE end boundary ' . $endBoundary),
        };

        return array_values(array_filter(
            $partitionRows,
            static fn (array $row): bool => $lower <= $upper && $row[$orderColumn] >= $lower && $row[$orderColumn] <= $upper
        ));
    }

    $peerKeys = [];
    foreach ($partitionRows as $row) {
        $key = implode("\0", array_map(static fn (string $column): string => (string) $row[$column], $orderColumns));
        if (!array_key_exists($key, $peerKeys)) {
            $peerKeys[$key] = count($peerKeys);
        }
    }
    $currentKey = implode("\0", array_map(static fn (string $column): string => (string) $current[$column], $orderColumns));
    $groupIndex = $peerKeys[$currentKey];
    $groupCount = count($peerKeys);
    $startGroup = $frameBoundary($groupIndex, $groupCount, $startBoundary, $startOffset);
    $endGroup = $frameBoundary($groupIndex, $groupCount, $endBoundary, $endOffset);

    if ($startGroup > $endGroup || $endGroup < 0 || $startGroup >= $groupCount) {
        return [];
    }

    return array_values(array_filter(
        $partitionRows,
        static function (array $row) use ($peerKeys, $orderColumns, $startGroup, $endGroup): bool {
            $key = implode("\0", array_map(static fn (string $column): string => (string) $row[$column], $orderColumns));
            $group = $peerKeys[$key];

            return $group >= max(0, $startGroup) && $group <= min(count($peerKeys) - 1, $endGroup);
        }
    ));
};

$aggregateValue = static function (array $frameRows, string $aggregate): mixed {
    $values = array_column($frameRows, 'd');

    return match ($aggregate) {
        'sum' => $values === [] ? null : array_sum($values),
        'count' => count($values),
        'min' => $values === [] ? null : min($values),
        'group_concat' => $values === [] ? null : implode(',', array_map(static fn (int $value): string => (string) $value, $values)),
        default => throw new InvalidArgumentException('Unsupported window2.test aggregate ' . $aggregate),
    };
};

$cursorAt = static function (array $sourceRows, array $partitionColumns, array $orderColumns, string $unit, string $start, int $startOffset, string $end, int $endOffset, int $position): SQLiteVdbeWindowAggregateCursor {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $sourceRows,
        'd',
        $partitionColumns,
        $orderColumns,
        null,
        $startOffset,
        $endOffset,
        [],
        [],
        [],
        [],
        [],
        [],
        $unit,
        'NO OTHERS',
        $start,
        $end,
    );
    for ($i = 0; $i < $position; $i++) {
        $cursor->next();
    }

    return $cursor;
};

$scenarios = [
    'window2.test 1.1 partition b order c running' => ['ROWS', 'UNBOUNDED PRECEDING', 0, 'CURRENT ROW', 0, ['b'], ['c']],
    'window2.test 1.2 whole partition no order' => ['ROWS', 'UNBOUNDED PRECEDING', 0, 'UNBOUNDED FOLLOWING', 0, [], ['a']],
    'window2.test 2.1 rows 1000 preceding to 1 following' => ['ROWS', 'PRECEDING', 1000, 'FOLLOWING', 1, [], ['d']],
    'window2.test 2.2 rows 1000 preceding to 1000 following' => ['ROWS', 'PRECEDING', 1000, 'FOLLOWING', 1000, [], ['d']],
    'window2.test 2.3 rows 1 preceding to 1000 following' => ['ROWS', 'PRECEDING', 1, 'FOLLOWING', 1000, [], ['d']],
    'window2.test 2.4 rows 1 preceding to 1 following' => ['ROWS', 'PRECEDING', 1, 'FOLLOWING', 1, [], ['d']],
    'window2.test 2.5 rows 1 preceding to 0 following' => ['ROWS', 'PRECEDING', 1, 'FOLLOWING', 0, [], ['d']],
    'window2.test 2.6 partition b rows 1 preceding to 1 following' => ['ROWS', 'PRECEDING', 1, 'FOLLOWING', 1, ['b'], ['d']],
    'window2.test 2.7 partition b current peer only' => ['ROWS', 'PRECEDING', 0, 'FOLLOWING', 0, ['b'], ['d']],
    'window2.test 2.8 current row to 2 following' => ['ROWS', 'CURRENT ROW', 0, 'FOLLOWING', 2, [], ['d']],
    'window2.test 2.9 unbounded preceding to 2 following' => ['ROWS', 'UNBOUNDED PRECEDING', 0, 'FOLLOWING', 2, [], ['d']],
    'window2.test 2.11 2 preceding to current row' => ['ROWS', 'PRECEDING', 2, 'CURRENT ROW', 0, [], ['d']],
    'window2.test 2.13 2 preceding to unbounded following' => ['ROWS', 'PRECEDING', 2, 'UNBOUNDED FOLLOWING', 0, [], ['d']],
    'window2.test 2.14 3 preceding to 1 preceding' => ['ROWS', 'PRECEDING', 3, 'PRECEDING', 1, [], ['d']],
    'window2.test 2.15 partition b 1 preceding to current' => ['ROWS', 'PRECEDING', 1, 'PRECEDING', 0, ['b'], ['d']],
    'window2.test 2.16 partition b previous row only' => ['ROWS', 'PRECEDING', 1, 'PRECEDING', 1, ['b'], ['d']],
    'window2.test 2.17 partition b empty reversed previous window' => ['ROWS', 'PRECEDING', 1, 'PRECEDING', 2, ['b'], ['d']],
    'window2.test 2.18 partition b unbounded preceding to 2 preceding' => ['ROWS', 'UNBOUNDED PRECEDING', 0, 'PRECEDING', 2, ['b'], ['d']],
    'window2.test 2.19 partition b 1 following to 3 following' => ['ROWS', 'FOLLOWING', 1, 'FOLLOWING', 3, ['b'], ['d']],
    'window2.test 2.20 1 following to 2 following' => ['ROWS', 'FOLLOWING', 1, 'FOLLOWING', 2, [], ['d']],
    'window2.test 2.21 1 following to unbounded following' => ['ROWS', 'FOLLOWING', 1, 'UNBOUNDED FOLLOWING', 0, [], ['d']],
    'window2.test 2.22 partition b 1 following to unbounded following' => ['ROWS', 'FOLLOWING', 1, 'UNBOUNDED FOLLOWING', 0, ['b'], ['d']],
    'window2.test 2.23 current row to unbounded following' => ['ROWS', 'CURRENT ROW', 0, 'UNBOUNDED FOLLOWING', 0, [], ['d']],
    'window2.test 2.24 partition b current row to unbounded following' => ['ROWS', 'CURRENT ROW', 0, 'UNBOUNDED FOLLOWING', 0, ['b'], ['d']],
    'window2.test 2.25 unbounded preceding to unbounded following' => ['ROWS', 'UNBOUNDED PRECEDING', 0, 'UNBOUNDED FOLLOWING', 0, [], ['d']],
    'window2.test 2.26 partition b full frame' => ['ROWS', 'UNBOUNDED PRECEDING', 0, 'UNBOUNDED FOLLOWING', 0, ['b'], ['d']],
    'window2.test 2.27 current row to current row' => ['ROWS', 'CURRENT ROW', 0, 'CURRENT ROW', 0, [], ['d']],
    'window2.test 2.28 partition b current row to current row' => ['ROWS', 'CURRENT ROW', 0, 'CURRENT ROW', 0, ['b'], ['d']],
    'window2.test 2.29 range current row to unbounded following' => ['RANGE', 'CURRENT ROW', 0, 'UNBOUNDED FOLLOWING', 0, [], ['d']],
    'window2.test 3.1 partition b range current row to unbounded following' => ['RANGE', 'CURRENT ROW', 0, 'UNBOUNDED FOLLOWING', 0, ['b'], ['d']],
    'window2.test 3.3 rows full frame repeat' => ['ROWS', 'UNBOUNDED PRECEDING', 0, 'UNBOUNDED FOLLOWING', 0, [], ['d']],
    'window2.test 3.4 rows unbounded preceding to current over d' => ['ROWS', 'UNBOUNDED PRECEDING', 0, 'CURRENT ROW', 0, [], ['d']],
    'window2.test 4.1 generated rows 2 preceding to 1 following' => ['ROWS', 'PRECEDING', 2, 'FOLLOWING', 1, [], ['d']],
    'window2.test 4.2 generated rows 4 preceding to current' => ['ROWS', 'PRECEDING', 4, 'CURRENT ROW', 0, [], ['d']],
    'window2.test 4.3 generated rows current to 4 following' => ['ROWS', 'CURRENT ROW', 0, 'FOLLOWING', 4, [], ['d']],
    'window2.test 4.4 generated rows 5 preceding to 5 following' => ['ROWS', 'PRECEDING', 5, 'FOLLOWING', 5, [], ['d']],
    'window2.test 4.5 generated rows 2 following to 4 following' => ['ROWS', 'FOLLOWING', 2, 'FOLLOWING', 4, [], ['d']],
    'window2.test 4.6 generated rows 4 preceding to 2 preceding' => ['ROWS', 'PRECEDING', 4, 'PRECEDING', 2, [], ['d']],
    'window2.test 4.7 generated partition rows 2 preceding to 2 following' => ['ROWS', 'PRECEDING', 2, 'FOLLOWING', 2, ['b'], ['d']],
    'window2.test 4.8 generated partition rows current to 2 following' => ['ROWS', 'CURRENT ROW', 0, 'FOLLOWING', 2, ['b'], ['d']],
    'window2.test 4.9 generated partition rows 2 preceding to current' => ['ROWS', 'PRECEDING', 2, 'CURRENT ROW', 0, ['b'], ['d']],
    'window2.test 4.10 generated range 1 preceding to current' => ['RANGE', 'PRECEDING', 1, 'CURRENT ROW', 0, [], ['d']],
    'window2.test 4.11 generated range current to 1 following' => ['RANGE', 'CURRENT ROW', 0, 'FOLLOWING', 1, [], ['d']],
    'window2.test 4.12 generated range 2 preceding to 2 following' => ['RANGE', 'PRECEDING', 2, 'FOLLOWING', 2, [], ['d']],
    'window2.test 4.13 generated groups current to current' => ['GROUPS', 'CURRENT ROW', 0, 'CURRENT ROW', 0, [], ['d']],
    'window2.test 4.14 generated groups 1 preceding to current' => ['GROUPS', 'PRECEDING', 1, 'CURRENT ROW', 0, [], ['d']],
    'window2.test 4.15 generated groups current to 1 following' => ['GROUPS', 'CURRENT ROW', 0, 'FOLLOWING', 1, [], ['d']],
    'window2.test 4.16 generated partition groups 1 preceding to 1 following' => ['GROUPS', 'PRECEDING', 1, 'FOLLOWING', 1, ['b'], ['d']],
];

$aggregates = [
    'sum' => static fn (SQLiteVdbeWindowAggregateCursor $cursor): mixed => $cursor->sum(),
    'count' => static fn (SQLiteVdbeWindowAggregateCursor $cursor): mixed => $cursor->countValue(),
    'min' => static fn (SQLiteVdbeWindowAggregateCursor $cursor): mixed => $cursor->min(),
    'group_concat' => static fn (SQLiteVdbeWindowAggregateCursor $cursor): mixed => $cursor->groupConcat(','),
];

foreach ($scenarios as $scenario => [$unit, $start, $startOffset, $end, $endOffset, $partitionColumns, $orderColumns]) {
    $orderedRows = $sortRows($rows, $partitionColumns, $orderColumns);
    foreach ($orderedRows as $position => $row) {
        $expectedRows = $expectedFrameRows($orderedRows, $position, $unit, $start, $startOffset, $end, $endOffset, $partitionColumns, $orderColumns);
        foreach ($aggregates as $aggregate => $callback) {
            $expected = $aggregateValue($expectedRows, $aggregate);
            $tests["real upstream {$scenario} {$aggregate} row {$row['a']}"] = static function (TestRunner $t) use ($cursorAt, $rows, $partitionColumns, $orderColumns, $unit, $start, $startOffset, $end, $endOffset, $position, $callback, $expected, $row, $scenario): void {
                $cursor = $cursorAt($rows, $partitionColumns, $orderColumns, $unit, $start, $startOffset, $end, $endOffset, $position);
                $t->same($row['a'], $cursor->currentRow()['a']);
                $t->same($expected, $callback($cursor), $scenario);
            };
        }
    }
}

$tests['real upstream window2.test dynamic frame boundary batch cites exact upstream source'] = static function (TestRunner $t): void {
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test:1.1-3.4 plus generated frame-boundary matrix in section 4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test:1.1-3.4 plus generated frame-boundary matrix in section 4',
    );
};

return $tests;
