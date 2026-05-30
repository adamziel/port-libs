<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$sourceRows = [
    ['a' => 1, 'b' => 'odd', 'c' => 'one', 'd' => 1, 'p' => 1],
    ['a' => 2, 'b' => 'even', 'c' => 'two', 'd' => 2, 'p' => 0],
    ['a' => 3, 'b' => 'odd', 'c' => 'three', 'd' => 3, 'p' => 1],
    ['a' => 4, 'b' => 'even', 'c' => 'four', 'd' => 4, 'p' => 0],
    ['a' => 5, 'b' => 'odd', 'c' => 'five', 'd' => 5, 'p' => 1],
    ['a' => 6, 'b' => 'even', 'c' => 'six', 'd' => 6, 'p' => 0],
];

/**
 * @param list<array<string,mixed>> $rows
 * @param list<string> $partitionColumns
 */
$windowRows = static function (
    array $rows,
    array $partitionColumns,
    int $preceding,
    int $following,
    string $startBoundary,
    string $endBoundary,
    string $unit = 'ROWS',
): array {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'd',
        $partitionColumns,
        ['d'],
        null,
        $preceding,
        $following,
        [],
        [],
        [],
        [],
        [],
        [],
        $unit,
        'NO OTHERS',
        $startBoundary,
        $endBoundary
    );

    $results = [];
    while (!$cursor->eof()) {
        $row = $cursor->currentRow();
        $results[] = [
            'a' => $row['a'],
            'sum' => $cursor->sum(),
            'frame' => array_column($cursor->currentFrameRows(false), 'a'),
        ];
        $cursor->next();
    }

    return $results;
};

/**
 * @return array<string,mixed>
 */
$case = static function (
    string $upstreamId,
    array $partitionColumns,
    int $preceding,
    int $following,
    string $startBoundary,
    string $endBoundary,
    array $expected,
    string $unit = 'ROWS',
): array {
    return [
        'upstream' => $upstreamId,
        'partition' => $partitionColumns,
        'preceding' => $preceding,
        'following' => $following,
        'start' => $startBoundary,
        'end' => $endBoundary,
        'expected' => $expected,
        'unit' => $unit,
    ];
};

$cases = [
    $case('window2.test 2.1', [], 1000, 1, 'PRECEDING', 'FOLLOWING', [[1, 3], [2, 6], [3, 10], [4, 15], [5, 21], [6, 21]]),
    $case('window2.test 2.2', [], 1000, 1000, 'PRECEDING', 'FOLLOWING', [[1, 21], [2, 21], [3, 21], [4, 21], [5, 21], [6, 21]]),
    $case('window2.test 2.3', [], 1, 1000, 'PRECEDING', 'FOLLOWING', [[1, 21], [2, 21], [3, 20], [4, 18], [5, 15], [6, 11]]),
    $case('window2.test 2.4', [], 1, 1, 'PRECEDING', 'FOLLOWING', [[1, 3], [2, 6], [3, 9], [4, 12], [5, 15], [6, 11]]),
    $case('window2.test 2.5', [], 1, 0, 'PRECEDING', 'FOLLOWING', [[1, 1], [2, 3], [3, 5], [4, 7], [5, 9], [6, 11]]),
    $case('window2.test 2.6', ['b'], 1, 1, 'PRECEDING', 'FOLLOWING', [[2, 6], [4, 12], [6, 10], [1, 4], [3, 9], [5, 8]]),
    $case('window2.test 2.7', ['b'], 0, 0, 'PRECEDING', 'FOLLOWING', [[2, 2], [4, 4], [6, 6], [1, 1], [3, 3], [5, 5]]),
    $case('window2.test 2.8', [], 0, 2, 'CURRENT ROW', 'FOLLOWING', [[1, 6], [2, 9], [3, 12], [4, 15], [5, 11], [6, 6]]),
    $case('window2.test 2.9', [], 0, 2, 'UNBOUNDED PRECEDING', 'FOLLOWING', [[1, 6], [2, 10], [3, 15], [4, 21], [5, 21], [6, 21]]),
    $case('window2.test 2.10', [], 0, 2, 'CURRENT ROW', 'FOLLOWING', [[1, 6], [2, 9], [3, 12], [4, 15], [5, 11], [6, 6]]),
    $case('window2.test 2.11', [], 2, 0, 'PRECEDING', 'CURRENT ROW', [[1, 1], [2, 3], [3, 6], [4, 9], [5, 12], [6, 15]]),
    $case('window2.test 2.13', [], 2, 0, 'PRECEDING', 'UNBOUNDED FOLLOWING', [[1, 21], [2, 21], [3, 21], [4, 20], [5, 18], [6, 15]]),
    $case('window2.test 2.14', [], 3, 1, 'PRECEDING', 'PRECEDING', [[1, null], [2, 1], [3, 3], [4, 6], [5, 9], [6, 12]]),
    $case('window2.test 2.15', ['b'], 1, 0, 'PRECEDING', 'PRECEDING', [[2, 2], [4, 6], [6, 10], [1, 1], [3, 4], [5, 8]]),
    $case('window2.test 2.16', ['b'], 1, 1, 'PRECEDING', 'PRECEDING', [[2, null], [4, 2], [6, 4], [1, null], [3, 1], [5, 3]]),
    $case('window2.test 2.17', ['b'], 1, 2, 'PRECEDING', 'PRECEDING', [[2, null], [4, null], [6, null], [1, null], [3, null], [5, null]]),
    $case('window2.test 2.18', ['b'], 0, 2, 'UNBOUNDED PRECEDING', 'PRECEDING', [[2, null], [4, null], [6, 2], [1, null], [3, null], [5, 1]]),
    $case('window2.test 2.19', ['b'], 1, 3, 'FOLLOWING', 'FOLLOWING', [[2, 10], [4, 6], [6, null], [1, 8], [3, 5], [5, null]]),
    $case('window2.test 2.20', [], 1, 2, 'FOLLOWING', 'FOLLOWING', [[1, 5], [2, 7], [3, 9], [4, 11], [5, 6], [6, null]]),
    $case('window2.test 2.21', [], 1, 0, 'FOLLOWING', 'UNBOUNDED FOLLOWING', [[1, 20], [2, 18], [3, 15], [4, 11], [5, 6], [6, null]]),
    $case('window2.test 2.22', ['b'], 1, 0, 'FOLLOWING', 'UNBOUNDED FOLLOWING', [[2, 10], [4, 6], [6, null], [1, 8], [3, 5], [5, null]]),
    $case('window2.test 2.23', [], 0, 0, 'CURRENT ROW', 'UNBOUNDED FOLLOWING', [[1, 21], [2, 20], [3, 18], [4, 15], [5, 11], [6, 6]]),
    $case('window2.test 2.24', ['p'], 0, 0, 'CURRENT ROW', 'UNBOUNDED FOLLOWING', [[2, 12], [4, 10], [6, 6], [1, 9], [3, 8], [5, 5]]),
    $case('window2.test 2.25', [], 0, 0, 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', [[1, 21], [2, 21], [3, 21], [4, 21], [5, 21], [6, 21]]),
    $case('window2.test 2.26', ['b'], 0, 0, 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', [[2, 12], [4, 12], [6, 12], [1, 9], [3, 9], [5, 9]]),
    $case('window2.test 2.27', [], 0, 0, 'CURRENT ROW', 'CURRENT ROW', [[1, 1], [2, 2], [3, 3], [4, 4], [5, 5], [6, 6]]),
    $case('window2.test 2.28', ['b'], 0, 0, 'CURRENT ROW', 'CURRENT ROW', [[2, 2], [4, 4], [6, 6], [1, 1], [3, 3], [5, 5]]),
    $case('window2.test 2.29', [], 0, 0, 'CURRENT ROW', 'UNBOUNDED FOLLOWING', [[1, 21], [2, 20], [3, 18], [4, 15], [5, 11], [6, 6]], 'RANGE'),
    $case('window2.test 3.1', ['b'], 0, 0, 'CURRENT ROW', 'UNBOUNDED FOLLOWING', [[2, 12], [4, 10], [6, 6], [1, 9], [3, 8], [5, 5]], 'RANGE'),
    $case('window2.test 3.3', [], 0, 0, 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', [[1, 21], [2, 21], [3, 21], [4, 21], [5, 21], [6, 21]]),
    $case('window2.test 3.4', [], 0, 0, 'UNBOUNDED PRECEDING', 'CURRENT ROW', [[1, 1], [2, 3], [3, 6], [4, 10], [5, 15], [6, 21]]),
];

$tests = [];

foreach ($cases as $definition) {
    $tests['real upstream dynamic window rows ' . $definition['upstream']] = static function (TestRunner $t) use ($definition, $windowRows, $sourceRows): void {
        $actual = $windowRows(
            $sourceRows,
            $definition['partition'],
            $definition['preceding'],
            $definition['following'],
            $definition['start'],
            $definition['end'],
            $definition['unit']
        );

        foreach ($definition['expected'] as $index => [$expectedA, $expectedSum]) {
            $t->same($expectedA, $actual[$index]['a']);
            $t->same($expectedSum, $actual[$index]['sum']);
            if ($expectedSum === null) {
                $t->same([], $actual[$index]['frame']);
            } else {
                $t->same($expectedSum, array_sum(array_intersect_key(array_column($sourceRows, 'd', 'a'), array_flip($actual[$index]['frame']))));
            }
        }
    };
}

$tests['real upstream dynamic window rows rejects unsupported start boundary'] = static function (TestRunner $t) use ($sourceRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor($sourceRows, 'd', [], ['d'], null, 0, 0, [], [], [], [], [], [], 'ROWS', 'NO OTHERS', 'UNBOUNDED FOLLOWING', 'CURRENT ROW'));
};

$tests['real upstream dynamic window rows rejects unsupported end boundary'] = static function (TestRunner $t) use ($sourceRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeWindowAggregateCursor($sourceRows, 'd', [], ['d'], null, 0, 0, [], [], [], [], [], [], 'ROWS', 'NO OTHERS', 'CURRENT ROW', 'UNBOUNDED PRECEDING'));
};

return $tests;
