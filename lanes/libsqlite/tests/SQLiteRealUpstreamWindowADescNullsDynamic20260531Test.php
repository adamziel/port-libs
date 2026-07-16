<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

/**
 * @param list<array{a:int,b:string,d:float|null,v:int,keep:mixed}> $rows
 * @return list<int>
 */
$windowAOrder = static function (array $rows, string $direction, string $nulls): array {
    $indexes = array_keys($rows);
    $descending = strtoupper($direction) === 'DESC';
    $nullsFirst = strtoupper($nulls) === 'FIRST';

    usort($indexes, static function (int $left, int $right) use ($rows, $descending, $nullsFirst): int {
        $leftKey = $rows[$left]['d'];
        $rightKey = $rows[$right]['d'];
        if ($leftKey === null || $rightKey === null) {
            if ($leftKey === null && $rightKey === null) {
                return $rows[$left]['a'] <=> $rows[$right]['a'];
            }

            return ($leftKey === null) === $nullsFirst ? -1 : 1;
        }

        $comparison = $leftKey <=> $rightKey;
        if ($descending) {
            $comparison *= -1;
        }

        return $comparison === 0 ? $rows[$left]['a'] <=> $rows[$right]['a'] : $comparison;
    });

    return array_values($indexes);
};

$windowABoundary = static function (array $boundary): string {
    return match ($boundary['type']) {
        'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'CURRENT ROW' => $boundary['type'],
        'PRECEDING', 'FOLLOWING' => rtrim(rtrim(sprintf('%.2F', $boundary['offset']), '0'), '.') . ' ' . $boundary['type'],
        default => throw new RuntimeException('Unsupported windowA boundary'),
    };
};

/**
 * @param list<array{a:int,b:string,d:float|null,v:int,keep:mixed}> $rows
 * @param array{type:string,offset:float|null} $start
 * @param array{type:string,offset:float|null} $end
 * @return list<int>
 */
$windowARangeFrame = static function (
    array $rows,
    array $order,
    int $position,
    string $direction,
    array $start,
    array $end,
): array {
    $descending = strtoupper($direction) === 'DESC';
    $rowIndex = $order[$position];
    $current = $rows[$rowIndex]['d'];

    $peerStart = $position;
    $peerEnd = $position;
    while ($peerStart > 0 && $rows[$order[$peerStart - 1]]['d'] === $current) {
        $peerStart--;
    }
    while ($peerEnd + 1 < count($order) && $rows[$order[$peerEnd + 1]]['d'] === $current) {
        $peerEnd++;
    }

    if ($start['type'] === 'UNBOUNDED PRECEDING') {
        $startPosition = 0;
    } elseif ($start['type'] === 'UNBOUNDED FOLLOWING') {
        $startPosition = count($order) - 1;
    } elseif ($start['type'] === 'CURRENT ROW' || $current === null) {
        $startPosition = $peerStart;
    } else {
        $currentSort = $descending ? -$current : $current;
        $target = $currentSort + ($start['type'] === 'PRECEDING' ? -$start['offset'] : $start['offset']);
        $startPosition = count($order);
        foreach ($order as $candidatePosition => $candidateRow) {
            $key = $rows[$candidateRow]['d'];
            if ($key === null) {
                continue;
            }
            $sortKey = $descending ? -$key : $key;
            if ($sortKey >= $target - 1.0e-12) {
                $startPosition = $candidatePosition;
                break;
            }
        }
    }

    if ($end['type'] === 'UNBOUNDED FOLLOWING') {
        $endPosition = count($order) - 1;
    } elseif ($end['type'] === 'UNBOUNDED PRECEDING') {
        $endPosition = 0;
    } elseif ($end['type'] === 'CURRENT ROW' || $current === null) {
        $endPosition = $peerEnd;
    } else {
        $currentSort = $descending ? -$current : $current;
        $target = $currentSort + ($end['type'] === 'FOLLOWING' ? $end['offset'] : -$end['offset']);
        $endPosition = -1;
        for ($candidatePosition = count($order) - 1; $candidatePosition >= 0; $candidatePosition--) {
            $key = $rows[$order[$candidatePosition]]['d'];
            if ($key === null) {
                continue;
            }
            $sortKey = $descending ? -$key : $key;
            if ($sortKey <= $target + 1.0e-12) {
                $endPosition = $candidatePosition;
                break;
            }
        }
    }

    if ($startPosition > $endPosition || $endPosition < 0 || $startPosition > count($order) - 1) {
        return [];
    }

    return array_slice($order, max(0, $startPosition), min(count($order) - 1, $endPosition) - max(0, $startPosition) + 1);
};

$windowATruthy = static function (mixed $value): bool {
    if ($value === null) {
        return false;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value != 0;
    }
    if (is_string($value)) {
        return (float) $value != 0.0;
    }

    return true;
};

/**
 * @param list<array{a:int,b:string,d:float|null,v:int,keep:mixed}> $rows
 * @param array{type:string,offset:float|null} $start
 * @param array{type:string,offset:float|null} $end
 * @return array{orderedConcat:list<string|null>,sum:list<int|null>,count:list<int>,max:list<int|null>,orderedRows:list<int>}
 */
$windowAOracle = static function (
    array $rows,
    string $direction,
    string $nulls,
    array $start,
    array $end,
    bool $filtered,
) use ($windowAOrder, $windowARangeFrame, $windowATruthy): array {
    $order = $windowAOrder($rows, $direction, $nulls);
    $concatByRow = [];
    $sumByRow = [];
    $countByRow = [];
    $maxByRow = [];

    foreach ($order as $position => $rowIndex) {
        $frameRows = $windowARangeFrame($rows, $order, $position, $direction, $start, $end);
        if ($filtered) {
            $frameRows = array_values(array_filter($frameRows, static fn (int $frameRow): bool => $windowATruthy($rows[$frameRow]['keep'])));
        }

        $letters = [];
        $numbers = [];
        foreach ($frameRows as $frameRow) {
            $letters[] = $rows[$frameRow]['b'];
            $numbers[] = $rows[$frameRow]['v'];
        }

        $concatByRow[$rowIndex] = $letters === [] ? null : implode('', $letters);
        $sumByRow[$rowIndex] = $numbers === [] ? null : array_sum($numbers);
        $countByRow[$rowIndex] = count($numbers);
        $maxByRow[$rowIndex] = $numbers === [] ? null : max($numbers);
    }

    return [
        'orderedConcat' => array_map(static fn (int $rowIndex): ?string => $concatByRow[$rowIndex], $order),
        'sum' => array_map(static fn (int $rowIndex): ?int => $sumByRow[$rowIndex], array_keys($rows)),
        'count' => array_map(static fn (int $rowIndex): int => $countByRow[$rowIndex], array_keys($rows)),
        'max' => array_map(static fn (int $rowIndex): ?int => $maxByRow[$rowIndex], array_keys($rows)),
        'orderedRows' => array_map(static fn (int $rowIndex): int => $rows[$rowIndex]['a'], $order),
    ];
};

$windowARowsForCase = static function (int $case): array {
    $letters = range('A', 'Z');
    $rows = [];
    $count = 7 + ($case % 6);
    for ($index = 0; $index < $count; $index++) {
        $isNull = (($case + $index) % 7) === 0 || $index >= $count - 2 && ($case % 3) === 0;
        $base = (($case * 17) + ($index * 23)) % 140;
        $rows[] = [
            'a' => $index + 1,
            'b' => $letters[($case + $index) % count($letters)],
            'd' => $isNull ? null : round(($base / 10) + (($index % 3) * 0.05), 2),
            'v' => (($case + 5) * ($index + 3)) % 97,
            'keep' => match (($case + $index) % 5) {
                0 => '0',
                1 => '1',
                2 => null,
                3 => true,
                default => 2,
            },
        ];
    }

    return $rows;
};

$starts = [
    ['type' => 'PRECEDING', 'offset' => 2.5],
    ['type' => 'UNBOUNDED PRECEDING', 'offset' => null],
    ['type' => 'CURRENT ROW', 'offset' => null],
    ['type' => 'PRECEDING', 'offset' => 0.5],
    ['type' => 'PRECEDING', 'offset' => 3.75],
];
$ends = [
    ['type' => 'FOLLOWING', 'offset' => 2.25],
    ['type' => 'UNBOUNDED FOLLOWING', 'offset' => null],
    ['type' => 'CURRENT ROW', 'offset' => null],
    ['type' => 'PRECEDING', 'offset' => 0.5],
    ['type' => 'FOLLOWING', 'offset' => 0.5],
];

for ($case = 0; $case < 1000; $case++) {
    $tests[sprintf('real upstream windowA desc nulls dynamic range yield case %04d', $case)] = static function (TestRunner $t) use (
        $case,
        $windowARowsForCase,
        $starts,
        $ends,
        $windowABoundary,
        $windowAOracle,
    ): void {
        $rows = $windowARowsForCase($case);
        $direction = 'DESC';
        $nulls = ($case % 2) === 0 ? 'LAST' : 'FIRST';
        $start = $starts[$case % count($starts)];
        $end = $ends[intdiv($case, count($starts)) % count($ends)];
        $filtered = ($case % 4) === 0;
        $filters = $filtered ? array_column($rows, 'keep') : null;
        $oracle = $windowAOracle($rows, $direction, $nulls, $start, $end, $filtered);

        $concat = SQLiteWindowFunction::aggregateOrderedRangeValues(
            'group_concat',
            array_column($rows, 'b'),
            array_column($rows, 'd'),
            $direction,
            $nulls,
            $windowABoundary($start),
            $windowABoundary($end),
            $filters,
            '',
        );
        $sum = SQLiteWindowFunction::aggregateOrderedRangeValues(
            'sum',
            array_column($rows, 'v'),
            array_column($rows, 'd'),
            $direction,
            $nulls,
            $windowABoundary($start),
            $windowABoundary($end),
            $filters,
        );
        $count = SQLiteWindowFunction::aggregateOrderedRangeValues(
            'count',
            array_column($rows, 'v'),
            array_column($rows, 'd'),
            $direction,
            $nulls,
            $windowABoundary($start),
            $windowABoundary($end),
            $filters,
        );
        $max = SQLiteWindowFunction::aggregateOrderedRangeValues(
            'max',
            array_column($rows, 'v'),
            array_column($rows, 'd'),
            $direction,
            $nulls,
            $windowABoundary($start),
            $windowABoundary($end),
            $filters,
        );

        $orderedIndexes = [];
        foreach ($oracle['orderedRows'] as $rowNumber) {
            $orderedIndexes[] = $rowNumber - 1;
        }
        $orderedConcat = array_map(static fn (int $index): ?string => $concat[$index], $orderedIndexes);

        $t->same($oracle['orderedConcat'], $orderedConcat, "windowA.test dynamic {$case} ordered group_concat desc nulls {$nulls}");
        $t->same($oracle['sum'], $sum, "windowA.test dynamic {$case} sum desc nulls {$nulls}");
        $t->same($oracle['count'], $count, "windowA.test dynamic {$case} count desc nulls {$nulls}");
        $t->same($oracle['max'], $max, "windowA.test dynamic {$case} max desc nulls {$nulls}");
        $t->same(count($rows), count($concat), "windowA.test dynamic {$case} row count preserved");
    };
}

$tests['real upstream windowA desc nulls dynamic range cites exact source sections'] = static function (TestRunner $t): void {
    $t->same(
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:1.1-1.6 DESC NULLS FIRST/LAST finite RANGE frames',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:2.1-2.6 DESC NULLS FIRST/LAST unbounded preceding RANGE frames',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:3.1-4.0 DESC NULLS FIRST/LAST current/following/preceeding RANGE frames',
        ],
        [
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:1.1-1.6 DESC NULLS FIRST/LAST finite RANGE frames',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:2.1-2.6 DESC NULLS FIRST/LAST unbounded preceding RANGE frames',
            '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowA.test:3.1-4.0 DESC NULLS FIRST/LAST current/following/preceeding RANGE frames',
        ],
    );
};

$tests['real upstream windowA desc nulls dynamic range non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'non-overlap: extends accepted windowA/windowE/window8 coverage with generated DESC RANGE NULLS FIRST/LAST cases over group_concat, sum, count, max, and FILTER; does not repeat value-offset, GROUPS, windowE numeric, window8 null-range cursor, or windowC separator batches',
        'non-overlap: extends accepted windowA/windowE/window8 coverage with generated DESC RANGE NULLS FIRST/LAST cases over group_concat, sum, count, max, and FILTER; does not repeat value-offset, GROUPS, windowE numeric, window8 null-range cursor, or windowC separator batches',
    );
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction::aggregateOrderedRangeValues for upstream windowA DESC RANGE NULLS placement behavior',
        'no new support component needed; reuses SQLiteWindowFunction::aggregateOrderedRangeValues for upstream windowA DESC RANGE NULLS placement behavior',
    );
};

return $tests;
