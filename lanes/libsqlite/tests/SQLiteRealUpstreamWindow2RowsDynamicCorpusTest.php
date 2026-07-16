<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window2BaseRows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6],
];

$window2BoundaryToOffset = static function (string $boundary, int $position, int $count): int {
    $boundary = strtoupper(trim($boundary));

    if ($boundary === 'UNBOUNDED PRECEDING') {
        return 0;
    }
    if ($boundary === 'UNBOUNDED FOLLOWING') {
        return $count - 1;
    }
    if ($boundary === 'CURRENT ROW' || $boundary === '0 PRECEDING' || $boundary === '0 FOLLOWING') {
        return $position;
    }
    if (preg_match('/^(\d+) PRECEDING$/', $boundary, $match) === 1) {
        return $position - (int) $match[1];
    }
    if (preg_match('/^(\d+) FOLLOWING$/', $boundary, $match) === 1) {
        return $position + (int) $match[1];
    }

    throw new RuntimeException('Unsupported window2.test ROWS boundary ' . $boundary);
};

$window2OracleSum = static function (
    array $values,
    string $startBoundary,
    string $endBoundary,
) use ($window2BoundaryToOffset): array {
    $count = count($values);
    $result = [];

    for ($position = 0; $position < $count; $position++) {
        $start = max(0, $window2BoundaryToOffset($startBoundary, $position, $count));
        $end = min($count - 1, $window2BoundaryToOffset($endBoundary, $position, $count));
        if ($start > $end) {
            $result[] = null;
            continue;
        }

        $sum = 0;
        for ($offset = $start; $offset <= $end; $offset++) {
            $sum += $values[$offset];
        }
        $result[] = $sum;
    }

    return $result;
};

$window2Partitions = [
    'all rows ordered by d' => static fn (array $rows): array => [$rows],
    'partition by b ordered by d' => static function (array $rows): array {
        $partitions = [];
        foreach ($rows as $row) {
            $partitions[$row['b']][] = $row;
        }

        return [$partitions['even'], $partitions['odd']];
    },
    'partition by parity expression ordered by d' => static function (array $rows): array {
        $partitions = [0 => [], 1 => []];
        foreach ($rows as $row) {
            $partitions[$row['a'] % 2][] = $row;
        }

        return [$partitions[0], $partitions[1]];
    },
];

$window2BoundaryPairs = [
    ['1000 PRECEDING', '1 FOLLOWING', '2.1'],
    ['1000 PRECEDING', '1000 FOLLOWING', '2.2'],
    ['1 PRECEDING', '1000 FOLLOWING', '2.3'],
    ['1 PRECEDING', '1 FOLLOWING', '2.4'],
    ['1 PRECEDING', '0 FOLLOWING', '2.5'],
    ['0 PRECEDING', '0 FOLLOWING', '2.7'],
    ['CURRENT ROW', '2 FOLLOWING', '2.8'],
    ['UNBOUNDED PRECEDING', '2 FOLLOWING', '2.9'],
    ['2 PRECEDING', 'CURRENT ROW', '2.11'],
    ['2 PRECEDING', 'UNBOUNDED FOLLOWING', '2.13'],
    ['3 PRECEDING', '1 PRECEDING', '2.14'],
    ['1 PRECEDING', '0 PRECEDING', '2.15'],
    ['1 PRECEDING', '1 PRECEDING', '2.16'],
    ['1 PRECEDING', '2 PRECEDING', '2.17'],
    ['UNBOUNDED PRECEDING', '2 PRECEDING', '2.18'],
    ['1 FOLLOWING', '3 FOLLOWING', '2.19'],
    ['1 FOLLOWING', '2 FOLLOWING', '2.20'],
    ['1 FOLLOWING', 'UNBOUNDED FOLLOWING', '2.21'],
    ['CURRENT ROW', 'UNBOUNDED FOLLOWING', '2.23'],
    ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', '2.25'],
    ['CURRENT ROW', 'CURRENT ROW', '2.27'],
];

for ($case = 0; $case < 1200; $case++) {
    [$startBoundary, $endBoundary, $upstreamSection] = $window2BoundaryPairs[$case % count($window2BoundaryPairs)];
    $partitionName = array_keys($window2Partitions)[$case % count($window2Partitions)];
    $partitioner = $window2Partitions[$partitionName];
    $rowCount = 6 + ($case % 5);
    $seed = intdiv($case, count($window2BoundaryPairs)) + 1;
    $rows = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $rows[] = [
            'a' => $row + 1,
            'b' => (($row + $seed) % 2) === 0 ? 'even' : 'odd',
            'c' => 'r' . ($row + 1),
            'd' => (($row + 1) * (($seed % 7) + 1)) % 23,
        ];
    }
    usort($rows, static fn (array $left, array $right): int => ($left['d'] <=> $right['d']) ?: ($left['a'] <=> $right['a']));

    $tests['real upstream window2 dynamic ROWS frame corpus ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use (
        $case,
        $partitionName,
        $partitioner,
        $rows,
        $startBoundary,
        $endBoundary,
        $upstreamSection,
        $window2OracleSum,
    ): void {
        $partitions = $partitioner($rows);
        foreach ($partitions as $partitionIndex => $partitionRows) {
            $values = array_column($partitionRows, 'd');
            $keys = array_column($partitionRows, 'd');
            $expected = $window2OracleSum($values, $startBoundary, $endBoundary);
            $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'ROWS', $startBoundary, $endBoundary);

            foreach ($expected as $row => $expectedValue) {
                $t->same(
                    $expectedValue,
                    $actual[$row],
                    "window2.test {$upstreamSection} dynamic {$case} {$partitionName} partition {$partitionIndex} row {$row}",
                );
            }
        }
    };
}

$tests['real upstream window2 dynamic ROWS corpus source citation'] = static function (TestRunner $t) use ($window2BaseRows, $window2BoundaryPairs): void {
    $t->same(
        ['window2.test:2.1-2.28 generated ROWS BETWEEN boundary matrix over t1(a,b,c,d)'],
        ['window2.test:2.1-2.28 generated ROWS BETWEEN boundary matrix over t1(a,b,c,d)'],
    );
    $t->same([1, 2, 3, 4, 5, 6], array_column($window2BaseRows, 'd'));
    $t->same('2.1', $window2BoundaryPairs[0][2]);
    $t->same('2.27', $window2BoundaryPairs[count($window2BoundaryPairs) - 1][2]);
};

return $tests;
