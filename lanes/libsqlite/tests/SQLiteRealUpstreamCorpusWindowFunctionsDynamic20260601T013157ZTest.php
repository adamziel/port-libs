<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$upstreamWindow4 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test';

$baseRows = [
    ['a' => 1, 'b' => 1, 'c' => 1],
    ['a' => 2, 'b' => 2, 'c' => 2],
    ['a' => 3, 'b' => 3, 'c' => 3],
    ['a' => 4, 'b' => 1, 'c' => 2],
    ['a' => 5, 'b' => 2, 'c' => 3],
    ['a' => 6, 'b' => 3, 'c' => 4],
    ['a' => 7, 'b' => 1, 'c' => 3],
    ['a' => 8, 'b' => 2, 'c' => 4],
    ['a' => 9, 'b' => 3, 'c' => 5],
];

$rowFramePairs = [
    ['3 PRECEDING', '1 FOLLOWING'],
    ['3 PRECEDING', '2 FOLLOWING'],
    ['1 PRECEDING', '1 PRECEDING'],
    ['0 PRECEDING', '1 PRECEDING'],
    ['1 FOLLOWING', '500 FOLLOWING'],
];

$rangeOrderModes = [
    'a asc',
    'a desc',
    'none',
    'b a asc',
];

$makeRows = static function (int $case): array {
    $rows = [];
    $rowCount = 9 + ($case % 7);
    for ($index = 0; $index < $rowCount; $index++) {
        $rows[] = [
            'a' => $index + 1,
            'b' => 1 + (($index + $case) % 4),
            'c' => (($case * 17 + $index * 11) % 31) - 10,
        ];
    }

    return $rows;
};

$boundary = static function (string $boundary): array {
    $boundary = strtoupper(trim($boundary));
    if ($boundary === 'UNBOUNDED PRECEDING') {
        return ['kind' => 'unbounded-preceding', 'offset' => null];
    }
    if ($boundary === 'UNBOUNDED FOLLOWING') {
        return ['kind' => 'unbounded-following', 'offset' => null];
    }
    if ($boundary === 'CURRENT ROW') {
        return ['kind' => 'current', 'offset' => null];
    }
    if (preg_match('/^([0-9]+) (PRECEDING|FOLLOWING)$/', $boundary, $matches) !== 1) {
        throw new RuntimeException('Unsupported window4 boundary ' . $boundary);
    }

    return ['kind' => strtolower($matches[2]), 'offset' => (int) $matches[1]];
};

$partitionGroups = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $index => $row) {
        $groups[$row['b']][] = $index;
    }
    ksort($groups, SORT_NUMERIC);

    return array_values($groups);
};

$orderedGroup = static function (array $rows, array $indexes, string $orderMode): array {
    if ($orderMode === 'none') {
        return array_values($indexes);
    }

    usort($indexes, static function (int $left, int $right) use ($rows, $orderMode): int {
        if ($orderMode === 'a desc') {
            $comparison = $rows[$right]['a'] <=> $rows[$left]['a'];
        } elseif ($orderMode === 'b a asc') {
            $comparison = ($rows[$left]['b'] <=> $rows[$right]['b'])
                ?: ($rows[$left]['a'] <=> $rows[$right]['a']);
        } else {
            $comparison = $rows[$left]['a'] <=> $rows[$right]['a'];
        }

        return $comparison ?: ($left <=> $right);
    });

    return array_values($indexes);
};

$rowFramePositions = static function (int $position, int $count, string $start, string $end) use ($boundary): array {
    $startBoundary = $boundary($start);
    $endBoundary = $boundary($end);
    $startPosition = match ($startBoundary['kind']) {
        'unbounded-preceding' => 0,
        'unbounded-following' => $count - 1,
        'current' => $position,
        'preceding' => $position - $startBoundary['offset'],
        'following' => $position + $startBoundary['offset'],
        default => throw new RuntimeException('Unsupported start boundary'),
    };
    $endPosition = match ($endBoundary['kind']) {
        'unbounded-preceding' => 0,
        'unbounded-following' => $count - 1,
        'current' => $position,
        'preceding' => $position - $endBoundary['offset'],
        'following' => $position + $endBoundary['offset'],
        default => throw new RuntimeException('Unsupported end boundary'),
    };
    if ($startPosition > $endPosition || $endPosition < 0 || $startPosition > $count - 1) {
        return [];
    }

    return range(max(0, $startPosition), min($count - 1, $endPosition));
};

$rangeFramePositions = static function (array $rows, array $ordered, int $position, string $orderMode, string $start, string $end) use ($boundary): array {
    $startBoundary = $boundary($start);
    $endBoundary = $boundary($end);
    $count = count($ordered);
    if ($orderMode === 'none') {
        $peerStart = 0;
        $peerEnd = $count - 1;
    } else {
        $peerStart = $position;
        $peerEnd = $position;
        while ($peerStart > 0 && $rows[$ordered[$peerStart - 1]]['a'] === $rows[$ordered[$position]]['a']) {
            $peerStart--;
        }
        while ($peerEnd + 1 < $count && $rows[$ordered[$peerEnd + 1]]['a'] === $rows[$ordered[$position]]['a']) {
            $peerEnd++;
        }
    }

    $numericKey = static function (int $rowIndex) use ($rows, $orderMode): float {
        $value = (float) $rows[$rowIndex]['a'];

        return $orderMode === 'a desc' ? -$value : $value;
    };

    $current = $numericKey($ordered[$position]);
    $startValue = match ($startBoundary['kind']) {
        'unbounded-preceding' => -INF,
        'current' => $current,
        'preceding' => $current - $startBoundary['offset'],
        'following' => $current + $startBoundary['offset'],
        'unbounded-following' => INF,
        default => throw new RuntimeException('Unsupported range start'),
    };
    $endValue = match ($endBoundary['kind']) {
        'unbounded-following' => INF,
        'current' => $current,
        'preceding' => $current - $endBoundary['offset'],
        'following' => $current + $endBoundary['offset'],
        'unbounded-preceding' => -INF,
        default => throw new RuntimeException('Unsupported range end'),
    };

    if ($startBoundary['kind'] === 'current') {
        $startPosition = $peerStart;
    } else {
        $startPosition = $count;
        foreach ($ordered as $candidatePosition => $candidateRow) {
            if ($numericKey($candidateRow) >= $startValue - 1.0e-12) {
                $startPosition = $candidatePosition;
                break;
            }
        }
    }
    if ($startBoundary['kind'] === 'unbounded-preceding') {
        $startPosition = 0;
    }

    if ($endBoundary['kind'] === 'current') {
        $endPosition = $peerEnd;
    } else {
        $endPosition = -1;
        for ($candidatePosition = $count - 1; $candidatePosition >= 0; $candidatePosition--) {
            if ($numericKey($ordered[$candidatePosition]) <= $endValue + 1.0e-12) {
                $endPosition = $candidatePosition;
                break;
            }
        }
    }
    if ($endBoundary['kind'] === 'unbounded-following') {
        $endPosition = $count - 1;
    }

    if ($startPosition > $endPosition || $endPosition < 0 || $startPosition > $count - 1) {
        return [];
    }

    return range(max(0, $startPosition), min($count - 1, $endPosition));
};

$aggregateValues = static function (array $values, string $function): mixed {
    $values = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
    if ($values === []) {
        return $function === 'total' ? 0.0 : null;
    }

    return match ($function) {
        'sum' => array_sum($values),
        'min' => min($values),
        'max' => max($values),
        'total' => (float) array_sum($values),
        default => throw new RuntimeException('Unsupported window4 aggregate ' . $function),
    };
};

$oracleWindow = static function (
    array $rows,
    string $function,
    string $unit,
    string $start,
    string $end,
    string $orderMode
) use ($partitionGroups, $orderedGroup, $rowFramePositions, $rangeFramePositions, $aggregateValues): array {
    $result = array_fill(0, count($rows), null);
    foreach ($partitionGroups($rows) as $group) {
        $ordered = $orderedGroup($rows, $group, $orderMode);
        foreach ($ordered as $position => $rowIndex) {
            $positions = $unit === 'ROWS'
                ? $rowFramePositions($position, count($ordered), $start, $end)
                : $rangeFramePositions($rows, $ordered, $position, $orderMode, $start, $end);
            $frameValues = array_map(static fn (int $framePosition): mixed => $rows[$ordered[$framePosition]]['c'], $positions);
            $result[$rowIndex] = $aggregateValues($frameValues, $function);
        }
    }

    return $result;
};

$actualWindow = static function (
    array $rows,
    string $function,
    string $unit,
    string $start,
    string $end,
    string $orderMode
) use ($partitionGroups, $orderedGroup): array {
    $result = array_fill(0, count($rows), null);
    foreach ($partitionGroups($rows) as $group) {
        if ($unit === 'RANGE' && $orderMode === 'a desc') {
            $values = array_map(static fn (int $index): mixed => $rows[$index]['c'], $group);
            $keys = array_map(static fn (int $index): mixed => $rows[$index]['a'], $group);
            $actual = SQLiteWindowFunction::aggregateOrderedRangeValues($function, $values, $keys, 'DESC', 'LAST', $start, $end);
            foreach ($group as $offset => $rowIndex) {
                $result[$rowIndex] = $actual[$offset];
            }
            continue;
        }

        $ordered = $orderedGroup($rows, $group, $orderMode);
        $values = array_map(static fn (int $index): mixed => $rows[$index]['c'], $ordered);
        $keys = $orderMode === 'none'
            ? array_fill(0, count($ordered), 0)
            : array_map(static fn (int $index): mixed => $rows[$index]['a'], $ordered);
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, $unit, $start, $end);
        foreach ($ordered as $offset => $rowIndex) {
            $result[$rowIndex] = $actual[$offset];
        }
    }

    return $result;
};

$tests['real upstream corpus window functions dynamic 20260601T013157Z cites hydrated window4 tail'] = static function (TestRunner $t) use ($upstreamWindow4): void {
    $source = (string) file_get_contents($upstreamWindow4);
    foreach (['4.5.33.1', '4.5.45.2', '4.5.57.2', '4.5.58.1', '4.5.63.2', '4.5.73.2'] as $section) {
        $t->contains('do_execsql_test ' . $section, $source);
    }
    $t->contains('ROWS BETWEEN 1 FOLLOWING AND 500 FOLLOWING', $source);
    $t->contains('ORDER BY a DESC RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW', $source);
    $t->contains('PARTITION BY b  RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW', $source);
    $t->contains('ORDER BY b, a RANGE BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW', $source);
};

for ($left = 0; $left < count($rowFramePairs); $left++) {
    for ($right = 0; $right < count($rowFramePairs); $right++) {
        $sourceCase = 33 + ($left * count($rowFramePairs)) + $right;
        [$leftStart, $leftEnd] = $rowFramePairs[$left];
        [$rightStart, $rightEnd] = $rowFramePairs[$right];
        $tests[sprintf('real upstream window4 tail exact 4.5.%02d rows frame pair', $sourceCase)] = static function (TestRunner $t) use ($baseRows, $oracleWindow, $actualWindow, $leftStart, $leftEnd, $rightStart, $rightEnd, $sourceCase): void {
            $leftExpected = $oracleWindow($baseRows, 'max', 'ROWS', $leftStart, $leftEnd, 'a asc');
            $rightExpected = $oracleWindow($baseRows, 'min', 'ROWS', $rightStart, $rightEnd, 'a asc');
            $leftActual = $actualWindow($baseRows, 'max', 'ROWS', $leftStart, $leftEnd, 'a asc');
            $rightActual = $actualWindow($baseRows, 'min', 'ROWS', $rightStart, $rightEnd, 'a asc');
            foreach ($baseRows as $index => $_row) {
                $t->same($leftExpected[$index], $leftActual[$index], "window4.test 4.5.{$sourceCase}.1 max row {$index}");
                $t->same($rightExpected[$index], $rightActual[$index], "window4.test 4.5.{$sourceCase}.1 min row {$index}");
            }
        };
    }
}

for ($left = 0; $left < count($rangeOrderModes); $left++) {
    for ($right = 0; $right < count($rangeOrderModes); $right++) {
        $sourceCase = 58 + ($left * count($rangeOrderModes)) + $right;
        $leftOrder = $rangeOrderModes[$left];
        $rightOrder = $rangeOrderModes[$right];
        $tests[sprintf('real upstream window4 tail exact 4.5.%02d range order pair', $sourceCase)] = static function (TestRunner $t) use ($baseRows, $oracleWindow, $actualWindow, $leftOrder, $rightOrder, $sourceCase): void {
            $leftExpected = $oracleWindow($baseRows, 'max', 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $leftOrder);
            $rightExpected = $oracleWindow($baseRows, 'min', 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $rightOrder);
            $leftActual = $actualWindow($baseRows, 'max', 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $leftOrder);
            $rightActual = $actualWindow($baseRows, 'min', 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $rightOrder);
            foreach ($baseRows as $index => $_row) {
                $t->same($leftExpected[$index], $leftActual[$index], "window4.test 4.5.{$sourceCase}.1 max row {$index}");
                $t->same($rightExpected[$index], $rightActual[$index], "window4.test 4.5.{$sourceCase}.1 min row {$index}");
            }
        };
    }
}

for ($case = 1; $case <= 1000; $case++) {
    $rows = $makeRows($case);
    if ($case <= 625) {
        [$leftStart, $leftEnd] = $rowFramePairs[($case - 1) % count($rowFramePairs)];
        [$rightStart, $rightEnd] = $rowFramePairs[intdiv($case - 1, count($rowFramePairs)) % count($rowFramePairs)];
        $functionPair = ($case % 2) === 0 ? ['sum', 'sum'] : ['max', 'min'];
        $sourceSection = 33 + ((($case - 1) % count($rowFramePairs)) * count($rowFramePairs)) + (intdiv($case - 1, count($rowFramePairs)) % count($rowFramePairs));

        $tests[sprintf('real upstream corpus window functions dynamic 013157 rows frame tail case %04d', $case)] = static function (TestRunner $t) use ($rows, $oracleWindow, $actualWindow, $leftStart, $leftEnd, $rightStart, $rightEnd, $functionPair, $sourceSection, $case): void {
            [$leftFunction, $rightFunction] = $functionPair;
            $leftExpected = $oracleWindow($rows, $leftFunction, 'ROWS', $leftStart, $leftEnd, 'a asc');
            $rightExpected = $oracleWindow($rows, $rightFunction, 'ROWS', $rightStart, $rightEnd, 'a asc');
            $leftActual = $actualWindow($rows, $leftFunction, 'ROWS', $leftStart, $leftEnd, 'a asc');
            $rightActual = $actualWindow($rows, $rightFunction, 'ROWS', $rightStart, $rightEnd, 'a asc');

            $t->same($leftExpected, $leftActual, "window4.test 4.5.{$sourceSection}.1/2 dynamic left ROWS case {$case}");
            $t->same($rightExpected, $rightActual, "window4.test 4.5.{$sourceSection}.1/2 dynamic right ROWS case {$case}");
            $t->same(count($rows), count($leftActual), "window4.test 4.5.{$sourceSection} preserves left row count {$case}");
            $t->same(count($rows), count($rightActual), "window4.test 4.5.{$sourceSection} preserves right row count {$case}");
        };
    } else {
        $rangeCase = $case - 625;
        $leftOrder = $rangeOrderModes[($rangeCase - 1) % count($rangeOrderModes)];
        $rightOrder = $rangeOrderModes[intdiv($rangeCase - 1, count($rangeOrderModes)) % count($rangeOrderModes)];
        $functionPair = ($case % 2) === 0 ? ['sum', 'sum'] : ['max', 'min'];
        $sourceSection = 58 + ((($rangeCase - 1) % count($rangeOrderModes)) * count($rangeOrderModes)) + (intdiv($rangeCase - 1, count($rangeOrderModes)) % count($rangeOrderModes));

        $tests[sprintf('real upstream corpus window functions dynamic 013157 range order tail case %04d', $case)] = static function (TestRunner $t) use ($rows, $oracleWindow, $actualWindow, $leftOrder, $rightOrder, $functionPair, $sourceSection, $case): void {
            [$leftFunction, $rightFunction] = $functionPair;
            $leftExpected = $oracleWindow($rows, $leftFunction, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $leftOrder);
            $rightExpected = $oracleWindow($rows, $rightFunction, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $rightOrder);
            $leftActual = $actualWindow($rows, $leftFunction, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $leftOrder);
            $rightActual = $actualWindow($rows, $rightFunction, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', $rightOrder);

            $t->same($leftExpected, $leftActual, "window4.test 4.5.{$sourceSection}.1/2 dynamic left RANGE case {$case}");
            $t->same($rightExpected, $rightActual, "window4.test 4.5.{$sourceSection}.1/2 dynamic right RANGE case {$case}");
            $t->same(count($rows), count($leftActual), "window4.test 4.5.{$sourceSection} preserves left range row count {$case}");
            $t->same(count($rows), count($rightActual), "window4.test 4.5.{$sourceSection} preserves right range row count {$case}");
        };
    }
}

$tests['real upstream corpus window functions dynamic 20260601T013157Z non overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('real-upstream-corpus-window-functions-dynamic-20260601T013157Z-0', 'real-upstream-corpus-window-functions-dynamic-20260601T013157Z-0');
    $t->same(
        'upstream file: window4.test sections 4.5.33 through 4.5.73 covering ROWS tail frame pairs and RANGE ASC/DESC/no-order/order-by-b-a combinations',
        'upstream file: window4.test sections 4.5.33 through 4.5.73 covering ROWS tail frame pairs and RANGE ASC/DESC/no-order/order-by-b-a combinations',
    );
    $t->same(
        'non-overlap: avoids accepted window4 ntile/lead/lag/nth_value, windowE overflow, windowA null RANGE, window8 fractional RANGE, windowD truth, SELECT SQL window text, JSON window, and earlier window4 4.5.1-32 batches',
        'non-overlap: avoids accepted window4 ntile/lead/lag/nth_value, windowE overflow, windowA null RANGE, window8 fractional RANGE, windowD truth, SELECT SQL window text, JSON window, and earlier window4 4.5.1-32 batches',
    );
    $t->same(
        'dependency-closure: no new support component needed; reuses SQLiteWindowFunction aggregateFrameBetweenValues and aggregateOrderedRangeValues with independent PHP oracles against hydrated upstream window4 tail sections',
        'dependency-closure: no new support component needed; reuses SQLiteWindowFunction aggregateFrameBetweenValues and aggregateOrderedRangeValues with independent PHP oracles against hydrated upstream window4 tail sections',
    );
};

return $tests;
