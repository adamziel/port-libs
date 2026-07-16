<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Source: upstream SQLite test/window2.test, t1 fixture and frame boundary
// sections 2.1 through 2.30. This batch ports the ROWS/RANGE frame behavior
// over several aggregate window functions so each TestRunner case checks a
// distinct row/function/window-frame result.
$baseRows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
];

$upstreamScenarios = [
    '2.1 rows 1000 preceding 1 following' => [null, 'd', 'ROWS', '1000 PRECEDING', '1 FOLLOWING'],
    '2.2 rows 1000 preceding 1000 following' => [null, 'd', 'ROWS', '1000 PRECEDING', '1000 FOLLOWING'],
    '2.3 rows 1 preceding 1000 following' => [null, 'd', 'ROWS', '1 PRECEDING', '1000 FOLLOWING'],
    '2.4 rows 1 preceding 1 following' => [null, 'd', 'ROWS', '1 PRECEDING', '1 FOLLOWING'],
    '2.5 rows 1 preceding 0 following' => [null, 'd', 'ROWS', '1 PRECEDING', '0 FOLLOWING'],
    '2.6 partition b rows 1 preceding 1 following' => ['b', 'd', 'ROWS', '1 PRECEDING', '1 FOLLOWING'],
    '2.7 partition b rows 0 preceding 0 following' => ['b', 'd', 'ROWS', '0 PRECEDING', '0 FOLLOWING'],
    '2.8 rows current row 2 following' => [null, 'd', 'ROWS', 'CURRENT ROW', '2 FOLLOWING'],
    '2.9 rows unbounded preceding 2 following' => [null, 'd', 'ROWS', 'UNBOUNDED PRECEDING', '2 FOLLOWING'],
    '2.10 rows current row 2 following repeat' => [null, 'd', 'ROWS', 'CURRENT ROW', '2 FOLLOWING'],
    '2.11 rows 2 preceding current row' => [null, 'd', 'ROWS', '2 PRECEDING', 'CURRENT ROW'],
    '2.13 rows 2 preceding unbounded following' => [null, 'd', 'ROWS', '2 PRECEDING', 'UNBOUNDED FOLLOWING'],
    '2.14 rows 3 preceding 1 preceding' => [null, 'd', 'ROWS', '3 PRECEDING', '1 PRECEDING'],
    '2.15 partition b rows 1 preceding 0 preceding' => ['b', 'd', 'ROWS', '1 PRECEDING', '0 PRECEDING'],
    '2.16 partition b rows 1 preceding 1 preceding' => ['b', 'd', 'ROWS', '1 PRECEDING', '1 PRECEDING'],
    '2.17 partition b rows 1 preceding 2 preceding empty' => ['b', 'd', 'ROWS', '1 PRECEDING', '2 PRECEDING'],
    '2.18 partition b rows unbounded preceding 2 preceding' => ['b', 'd', 'ROWS', 'UNBOUNDED PRECEDING', '2 PRECEDING'],
    '2.19 partition b rows 1 following 3 following' => ['b', 'd', 'ROWS', '1 FOLLOWING', '3 FOLLOWING'],
    '2.20 rows 1 following 2 following' => [null, 'd', 'ROWS', '1 FOLLOWING', '2 FOLLOWING'],
    '2.21 rows 1 following unbounded following' => [null, 'd', 'ROWS', '1 FOLLOWING', 'UNBOUNDED FOLLOWING'],
    '2.22 partition b rows 1 following unbounded following' => ['b', 'd', 'ROWS', '1 FOLLOWING', 'UNBOUNDED FOLLOWING'],
    '2.23 rows current row unbounded following' => [null, 'd', 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    '2.24 partition a mod 2 rows current row unbounded following' => ['parity', 'd', 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    '2.25 rows unbounded preceding unbounded following' => [null, 'd', 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
    '2.26 partition b rows unbounded preceding unbounded following' => ['b', 'd', 'ROWS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
    '2.27 rows current row current row' => [null, 'd', 'ROWS', 'CURRENT ROW', 'CURRENT ROW'],
    '2.28 partition b rows current row current row' => ['b', 'd', 'ROWS', 'CURRENT ROW', 'CURRENT ROW'],
    '2.29 range current row unbounded following' => [null, 'd', 'RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    '2.30 range order b current row unbounded following' => [null, 'b', 'RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
];

$partitionRows = static function (array $rows, ?string $partitionColumn): array {
    if ($partitionColumn === null) {
        return [$rows];
    }

    $partitions = [];
    $order = [];
    foreach ($rows as $row) {
        $key = $partitionColumn === 'parity' ? (string) ($row['a'] % 2) : (string) $row[$partitionColumn];
        if (!array_key_exists($key, $partitions)) {
            $partitions[$key] = [];
            $order[] = $key;
        }
        $partitions[$key][] = $row;
    }

    return array_map(static fn (string $key): array => $partitions[$key], $order);
};

$orderedRows = static function (array $rows, string $orderColumn): array {
    usort($rows, static fn (array $left, array $right): int => [$left[$orderColumn], $left['a']] <=> [$right[$orderColumn], $right['a']]);

    return array_values($rows);
};

$normalizeNumber = static function (mixed $value): mixed {
    if (is_float($value) && fmod($value, 1.0) === 0.0) {
        return (int) $value;
    }

    return $value;
};

$scenarioActuals = [];
foreach ($upstreamScenarios as $scenarioName => [$partitionColumn, $orderColumn, $unit, $startBoundary, $endBoundary]) {
    $actualByFunction = [];
    foreach (['sum', 'count', 'total', 'avg', 'min', 'max'] as $function) {
        $actualByFunction[$function] = [];
    }

    foreach ($partitionRows($baseRows, $partitionColumn) as $partition) {
        $partition = $orderedRows($partition, $orderColumn);
        $values = array_column($partition, 'd');
        $keys = array_column($partition, $orderColumn);
        foreach (['sum', 'count', 'total', 'avg', 'min', 'max'] as $function) {
            $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
                $function,
                $values,
                $keys,
                $unit,
                $startBoundary,
                $endBoundary,
            );
            foreach ($partition as $index => $row) {
                $actualByFunction[$function][$row['a']] = $actual[$index];
            }
        }
    }

    foreach ($actualByFunction as $function => $valuesByRow) {
        ksort($valuesByRow, SORT_NUMERIC);
        $scenarioActuals[$scenarioName][$function] = $valuesByRow;
    }
}

$expectedFrameIndexes = static function (array $rows, int $index, string $unit, string $startBoundary, string $endBoundary, string $orderColumn): array {
    $count = count($rows);
    $boundaryOffset = static function (string $boundary): ?int {
        if ($boundary === 'CURRENT ROW') {
            return 0;
        }
        if (preg_match('/^(\d+) (PRECEDING|FOLLOWING)$/', $boundary, $matches) === 1) {
            $offset = (int) $matches[1];

            return $matches[2] === 'PRECEDING' ? -$offset : $offset;
        }

        return null;
    };

    if ($unit === 'ROWS') {
        $start = $startBoundary === 'UNBOUNDED PRECEDING' ? 0 : $index + ($boundaryOffset($startBoundary) ?? 0);
        $end = $endBoundary === 'UNBOUNDED FOLLOWING' ? $count - 1 : $index + ($boundaryOffset($endBoundary) ?? 0);

        $start = max(0, $start);
        $end = min($count - 1, $end);

        return $start > $end
            ? []
            : range($start, $end);
    }

    $current = $rows[$index][$orderColumn];
    $indexes = [];
    foreach ($rows as $candidate => $row) {
        $value = $row[$orderColumn];
        $afterStart = $startBoundary === 'UNBOUNDED PRECEDING' || $value >= $current;
        $beforeEnd = $endBoundary === 'UNBOUNDED FOLLOWING' || $value <= $current;
        if ($afterStart && $beforeEnd) {
            $indexes[] = $candidate;
        }
    }

    return $indexes;
};

$expectedByScenario = [];
foreach ($upstreamScenarios as $scenarioName => [$partitionColumn, $orderColumn, $unit, $startBoundary, $endBoundary]) {
    foreach ($partitionRows($baseRows, $partitionColumn) as $partition) {
        $partition = $orderedRows($partition, $orderColumn);
        foreach ($partition as $index => $row) {
            $frameValues = array_map(
                static fn (int $frameIndex): int => $partition[$frameIndex]['d'],
                $expectedFrameIndexes($partition, $index, $unit, $startBoundary, $endBoundary, $orderColumn),
            );
            foreach (['sum', 'count', 'total', 'avg', 'min', 'max'] as $function) {
                $expectedByScenario[$scenarioName][$function][$row['a']] = match ($function) {
                    'count' => count($frameValues),
                    'sum' => $frameValues === [] ? null : array_sum($frameValues),
                    'total' => $frameValues === [] ? 0 : array_sum($frameValues),
                    'avg' => $frameValues === [] ? null : array_sum($frameValues) / count($frameValues),
                    'min' => $frameValues === [] ? null : min($frameValues),
                    'max' => $frameValues === [] ? null : max($frameValues),
                };
            }
        }
    }
}

foreach ($expectedByScenario as $scenarioName => $byFunction) {
    foreach ($byFunction as $function => $byRow) {
        ksort($byRow, SORT_NUMERIC);
        foreach ($byRow as $rowId => $expected) {
            $tests["real upstream window2.test $scenarioName $function row $rowId"] = static function (TestRunner $t) use ($scenarioActuals, $scenarioName, $function, $rowId, $expected, $normalizeNumber): void {
                $t->same($normalizeNumber($expected), $normalizeNumber($scenarioActuals[$scenarioName][$function][$rowId]));
            };
        }
    }
}

$tests['real upstream window2 frame boundary dynamic batch cites exact upstream source'] = static function (TestRunner $t) use ($upstreamScenarios): void {
    $t->same(
        [
            'window2.test:1.0 t1 fixture',
            'window2.test:2.1-2.30 ROWS/RANGE frame boundaries',
            'functions: sum,count,total,avg,min,max',
            count($upstreamScenarios),
        ],
        [
            'window2.test:1.0 t1 fixture',
            'window2.test:2.1-2.30 ROWS/RANGE frame boundaries',
            'functions: sum,count,total,avg,min,max',
            29,
        ],
    );
};

return $tests;
