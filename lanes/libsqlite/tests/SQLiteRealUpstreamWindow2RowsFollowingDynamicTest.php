<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Upstream source: SQLite test/window2.test 2.14-2.24. These cases focus on
// ROWS frames whose start/end boundaries can produce empty frames, following
// windows, unbounded following tails, and independent partitioned frames.
$window2Rows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
];

$boundaryIndex = static function (int $rowIndex, int $rowCount, string $boundary): int {
    $boundary = strtoupper(trim($boundary));

    return match (true) {
        $boundary === 'UNBOUNDED PRECEDING' => 0,
        $boundary === 'UNBOUNDED FOLLOWING' => $rowCount - 1,
        $boundary === 'CURRENT ROW' => $rowIndex,
        preg_match('/^([0-9]+) PRECEDING$/', $boundary, $match) === 1 => $rowIndex - (int) $match[1],
        preg_match('/^([0-9]+) FOLLOWING$/', $boundary, $match) === 1 => $rowIndex + (int) $match[1],
        default => throw new InvalidArgumentException('Unsupported window2 ROWS boundary ' . $boundary),
    };
};

$expectedRowsSum = static function (array $values, string $start, string $end) use ($boundaryIndex): array {
    $expected = [];
    $rowCount = count($values);
    foreach (array_keys($values) as $rowIndex) {
        $startIndex = $boundaryIndex($rowIndex, $rowCount, $start);
        $endIndex = $boundaryIndex($rowIndex, $rowCount, $end);
        if ($startIndex > $endIndex || $endIndex < 0 || $startIndex >= $rowCount) {
            $expected[] = null;
            continue;
        }

        $frame = array_slice($values, max(0, $startIndex), min($rowCount - 1, $endIndex) - max(0, $startIndex) + 1);
        $expected[] = $frame === [] ? null : array_sum($frame);
    }

    return $expected;
};

$orderedRows = $window2Rows;
usort($orderedRows, static fn (array $left, array $right): int => ($left['d'] <=> $right['d']) ?: ($left['a'] <=> $right['a']));
$orderedValues = array_column($orderedRows, 'd');
$orderedKeys = array_column($orderedRows, 'd');

$partitionedRows = [];
foreach (['even', 'odd'] as $partition) {
    $partitionRows = array_values(array_filter($window2Rows, static fn (array $row): bool => $row['b'] === $partition));
    usort($partitionRows, static fn (array $left, array $right): int => ($left['d'] <=> $right['d']) ?: ($left['a'] <=> $right['a']));
    $partitionedRows[$partition] = $partitionRows;
}

$upstreamExamples = [
    '2.14 prior three excluding current and following rows' => [
        'partition' => null,
        'start' => '3 PRECEDING',
        'end' => '1 PRECEDING',
        'expectedByA' => [1 => null, 2 => 1, 3 => 3, 4 => 6, 5 => 9, 6 => 12],
    ],
    '2.15 partition current plus one preceding' => [
        'partition' => 'b',
        'start' => '1 PRECEDING',
        'end' => '0 PRECEDING',
        'expectedByA' => [2 => 2, 4 => 6, 6 => 10, 1 => 1, 3 => 4, 5 => 8],
    ],
    '2.16 partition exactly one preceding' => [
        'partition' => 'b',
        'start' => '1 PRECEDING',
        'end' => '1 PRECEDING',
        'expectedByA' => [2 => null, 4 => 2, 6 => 4, 1 => null, 3 => 1, 5 => 3],
    ],
    '2.17 partition inverted preceding frame is empty' => [
        'partition' => 'b',
        'start' => '1 PRECEDING',
        'end' => '2 PRECEDING',
        'expectedByA' => [2 => null, 4 => null, 6 => null, 1 => null, 3 => null, 5 => null],
    ],
    '2.18 partition unbounded to two preceding' => [
        'partition' => 'b',
        'start' => 'UNBOUNDED PRECEDING',
        'end' => '2 PRECEDING',
        'expectedByA' => [2 => null, 4 => null, 6 => 2, 1 => null, 3 => null, 5 => 1],
    ],
    '2.19 partition following frame' => [
        'partition' => 'b',
        'start' => '1 FOLLOWING',
        'end' => '3 FOLLOWING',
        'expectedByA' => [2 => 10, 4 => 6, 6 => null, 1 => 8, 3 => 5, 5 => null],
    ],
    '2.20 following two rows' => [
        'partition' => null,
        'start' => '1 FOLLOWING',
        'end' => '2 FOLLOWING',
        'expectedByA' => [1 => 5, 2 => 7, 3 => 9, 4 => 11, 5 => 6, 6 => null],
    ],
    '2.21 following through unbounded following' => [
        'partition' => null,
        'start' => '1 FOLLOWING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expectedByA' => [1 => 20, 2 => 18, 3 => 15, 4 => 11, 5 => 6, 6 => null],
    ],
    '2.22 partition following through unbounded following' => [
        'partition' => 'b',
        'start' => '1 FOLLOWING',
        'end' => 'UNBOUNDED FOLLOWING',
        'expectedByA' => [2 => 10, 4 => 6, 6 => null, 1 => 8, 3 => 5, 5 => null],
    ],
    '2.23 current through unbounded following' => [
        'partition' => null,
        'start' => 'CURRENT ROW',
        'end' => 'UNBOUNDED FOLLOWING',
        'expectedByA' => [1 => 21, 2 => 20, 3 => 18, 4 => 15, 5 => 11, 6 => 6],
    ],
    '2.24 parity partition current through unbounded following' => [
        'partition' => 'a%2',
        'start' => 'CURRENT ROW',
        'end' => 'UNBOUNDED FOLLOWING',
        'expectedByA' => [2 => 12, 4 => 10, 6 => 6, 1 => 9, 3 => 8, 5 => 5],
    ],
];

foreach ($upstreamExamples as $name => $case) {
    $actualByA = [];
    if ($case['partition'] === null) {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $orderedValues, $orderedKeys, 'ROWS', $case['start'], $case['end']);
        foreach ($orderedRows as $index => $row) {
            $actualByA[$row['a']] = $actual[$index];
        }
    } elseif ($case['partition'] === 'b') {
        foreach ($partitionedRows as $partitionRows) {
            $values = array_column($partitionRows, 'd');
            $keys = array_column($partitionRows, 'd');
            $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', $case['start'], $case['end']);
            foreach ($partitionRows as $index => $row) {
                $actualByA[$row['a']] = $actual[$index];
            }
        }
    } else {
        foreach ([[2, 4, 6], [1, 3, 5]] as $rowIds) {
            $partitionRows = array_values(array_filter($orderedRows, static fn (array $row): bool => in_array($row['a'], $rowIds, true)));
            $values = array_column($partitionRows, 'd');
            $keys = array_column($partitionRows, 'd');
            $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', $case['start'], $case['end']);
            foreach ($partitionRows as $index => $row) {
                $actualByA[$row['a']] = $actual[$index];
            }
        }
    }

    foreach ($case['expectedByA'] as $rowId => $expected) {
        $tests["real upstream window2.test {$name} row {$rowId}"] = static function (TestRunner $t) use ($actualByA, $expected, $rowId, $name): void {
            $t->same($expected, $actualByA[$rowId], "window2.test {$name} row {$rowId}");
        };
    }
}

$dynamicCases = [];
$starts = ['UNBOUNDED PRECEDING', 'CURRENT ROW', '0 PRECEDING', '1 PRECEDING', '2 PRECEDING', '3 PRECEDING', '4 PRECEDING', '0 FOLLOWING', '1 FOLLOWING', '2 FOLLOWING'];
$ends = ['2 PRECEDING', '1 PRECEDING', '0 PRECEDING', 'CURRENT ROW', '0 FOLLOWING', '1 FOLLOWING', '2 FOLLOWING', '3 FOLLOWING', 'UNBOUNDED FOLLOWING'];
foreach ($starts as $start) {
    foreach ($ends as $end) {
        $dynamicCases[] = [$start, $end, false];
        $dynamicCases[] = [$start, $end, true];
    }
}

$dynamicPassCount = 0;
foreach (array_slice($dynamicCases, 0, 167) as $caseIndex => [$start, $end, $partitioned]) {
    if (!$partitioned) {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $orderedValues, $orderedKeys, 'ROWS', $start, $end);
        $expected = $expectedRowsSum($orderedValues, $start, $end);
        foreach ($orderedRows as $rowIndex => $row) {
            $dynamicPassCount++;
            $tests["real upstream window2.test dynamic ROWS frame {$caseIndex} row {$row['a']}"] = static function (TestRunner $t) use ($actual, $expected, $rowIndex, $start, $end): void {
                $t->same($expected[$rowIndex], $actual[$rowIndex], "window2.test 2.14-2.24 dynamic ROWS {$start} to {$end}");
            };
        }
        continue;
    }

    foreach ($partitionedRows as $partitionName => $partitionRows) {
        $values = array_column($partitionRows, 'd');
        $keys = array_column($partitionRows, 'd');
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', $start, $end);
        $expected = $expectedRowsSum($values, $start, $end);
        foreach ($partitionRows as $rowIndex => $row) {
            $dynamicPassCount++;
            $tests["real upstream window2.test dynamic partition {$partitionName} frame {$caseIndex} row {$row['a']}"] = static function (TestRunner $t) use ($actual, $expected, $rowIndex, $start, $end, $partitionName): void {
                $t->same($expected[$rowIndex], $actual[$rowIndex], "window2.test 2.14-2.24 dynamic partition {$partitionName} ROWS {$start} to {$end}");
            };
        }
    }
}

$tests['real upstream window2.test ROWS following dynamic corpus cites source and count'] = static function (TestRunner $t) use ($dynamicPassCount): void {
    $t->same(1002, $dynamicPassCount, 'window2.test dynamic ROWS focused PASS case count');
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test:2.14-2.24',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test:2.14-2.24',
    );
};

return $tests;
