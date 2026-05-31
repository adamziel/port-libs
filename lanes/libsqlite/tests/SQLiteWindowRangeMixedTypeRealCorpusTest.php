<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @param list<bool> $descending
 * @param list<string|null> $nulls
 * @return array<int,int|float|null>
 */
$sumByRowid = static function (
    array $rows,
    string $startBoundary,
    int|float $preceding,
    string $endBoundary,
    int|float $following,
    array $descending = [],
    array $nulls = []
): array {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'b',
        [],
        ['a'],
        null,
        $preceding,
        $following,
        [],
        [],
        [],
        [],
        $descending,
        $nulls,
        'RANGE',
        'NO OTHERS',
        $startBoundary,
        $endBoundary
    );

    $actual = [];
    while (!$cursor->eof()) {
        $row = $cursor->currentRow();
        $actual[(int) $row['rowid']] = $cursor->sum();
        $cursor->next();
    }
    ksort($actual);

    return $actual;
};

/**
 * @param list<array<string,mixed>> $rows
 * @param list<bool> $descending
 * @param list<string|null> $nulls
 * @return array<int,int|float|null>
 */
$oracleRangeSumByRowid = static function (
    array $rows,
    string $startBoundary,
    int|float $preceding,
    string $endBoundary,
    int|float $following,
    array $descending = [],
    array $nulls = []
): array {
    $orderedRows = SQLiteVdbeSortCompare::sortRows($rows, ['a'], [], [], $descending, $nulls);
    $samePeer = static fn (array $left, array $right): bool => SQLiteVdbeSortCompare::compareRecords(
        [$left['a']],
        [$right['a']],
        [],
        [],
        $descending,
        $nulls
    ) === 0;
    $isNumeric = static fn (mixed $value): bool => is_bool($value) || is_int($value) || is_float($value);
    $includedByBoundary = static function (
        int $candidateIndex,
        int $peerStart,
        int $peerEnd,
        mixed $candidate,
        mixed $current,
        string $boundary,
        int|float $offset,
        bool $isStart,
        bool $isDescending
    ) use ($isNumeric): bool {
        $boundary = strtoupper($boundary);
        if ($boundary === 'UNBOUNDED PRECEDING' || $boundary === 'UNBOUNDED FOLLOWING') {
            return true;
        }
        if ($boundary === 'CURRENT ROW') {
            return $isStart ? $candidateIndex >= $peerStart : $candidateIndex <= $peerEnd;
        }
        if (!$isNumeric($candidate) || !$isNumeric($current)) {
            return $candidateIndex >= $peerStart && $candidateIndex <= $peerEnd;
        }

        $candidate = (float) $candidate;
        $current = (float) $current;
        if ($isStart) {
            $limit = match ($boundary) {
                'PRECEDING' => $isDescending ? $current + $offset : $current - $offset,
                'FOLLOWING' => $isDescending ? $current - $offset : $current + $offset,
                default => INF,
            };

            return $isDescending ? $candidate <= $limit + 1.0e-12 : $candidate >= $limit - 1.0e-12;
        }

        $limit = match ($boundary) {
            'PRECEDING' => $isDescending ? $current + $offset : $current - $offset,
            'FOLLOWING' => $isDescending ? $current - $offset : $current + $offset,
            default => -INF,
        };

        return $isDescending ? $candidate >= $limit - 1.0e-12 : $candidate <= $limit + 1.0e-12;
    };

    $actual = [];
    foreach ($orderedRows as $position => $row) {
        $peerStart = $position;
        while ($peerStart > 0 && $samePeer($orderedRows[$peerStart - 1], $row)) {
            $peerStart--;
        }
        $peerEnd = $position;
        while ($peerEnd + 1 < count($orderedRows) && $samePeer($orderedRows[$peerEnd + 1], $row)) {
            $peerEnd++;
        }

        $sum = null;
        foreach ($orderedRows as $candidateIndex => $candidate) {
            if (
                $includedByBoundary($candidateIndex, $peerStart, $peerEnd, $candidate['a'], $row['a'], $startBoundary, $preceding, true, $descending[0] ?? false)
                && $includedByBoundary($candidateIndex, $peerStart, $peerEnd, $candidate['a'], $row['a'], $endBoundary, $following, false, $descending[0] ?? false)
            ) {
                $sum = ($sum ?? 0) + $candidate['b'];
            }
        }
        $actual[(int) $row['rowid']] = $sum;
    }
    ksort($actual);

    return $actual;
};

$window1Rows = [
    ['rowid' => 1, 'a' => 1, 'b' => 1],
    ['rowid' => 2, 'a' => 2, 'b' => 2],
    ['rowid' => 3, 'a' => 3, 'b' => 3],
    ['rowid' => 4, 'a' => 4, 'b' => 4],
    ['rowid' => 5, 'a' => 5, 'b' => 5],
    ['rowid' => 6, 'a' => 'a', 'b' => 6],
    ['rowid' => 7, 'a' => 'b', 'b' => 7],
    ['rowid' => 8, 'a' => 'c', 'b' => 8],
    ['rowid' => 9, 'a' => 'd', 'b' => 9],
    ['rowid' => 10, 'a' => 'e', 'b' => 10],
];

$window1NullRows = [
    ['rowid' => 1, 'a' => null, 'b' => 100],
    ['rowid' => 2, 'a' => null, 'b' => 100],
    ['rowid' => 3, 'a' => 1, 'b' => 1],
    ['rowid' => 4, 'a' => 2, 'b' => 2],
    ['rowid' => 5, 'a' => 3, 'b' => 3],
    ['rowid' => 6, 'a' => 4, 'b' => 4],
    ['rowid' => 7, 'a' => 5, 'b' => 5],
    ['rowid' => 8, 'a' => 'a', 'b' => 6],
    ['rowid' => 9, 'a' => 'b', 'b' => 7],
    ['rowid' => 10, 'a' => 'c', 'b' => 8],
    ['rowid' => 11, 'a' => 'd', 'b' => 9],
    ['rowid' => 12, 'a' => 'e', 'b' => 10],
];

$tests['real upstream window1.test 19 range offsets keep nonnumeric order keys peer-only'] = static function (TestRunner $t) use ($sumByRowid, $window1Rows): void {
    $t->same([1 => 3, 2 => 6, 3 => 9, 4 => 12, 5 => 9, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10], $sumByRowid($window1Rows, 'PRECEDING', 1, 'FOLLOWING', 1));
    $t->same([1 => 3, 2 => 6, 3 => 9, 4 => 12, 5 => 9, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10], $sumByRowid($window1Rows, 'PRECEDING', 1, 'FOLLOWING', 1, [true]));
    $t->same([1 => 3, 2 => 6, 3 => 10, 4 => 14, 5 => 12, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10], $sumByRowid($window1Rows, 'PRECEDING', 2, 'FOLLOWING', 1));
    $t->same([1 => 3, 2 => 6, 3 => 10, 4 => 14, 5 => 12, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10], $sumByRowid($window1Rows, 'PRECEDING', 1, 'FOLLOWING', 2, [true]));
};

$tests['real upstream window1.test 20 range offsets keep null peer groups separate from text and numeric'] = static function (TestRunner $t) use ($sumByRowid, $window1NullRows): void {
    $expectedOneOne = [1 => 200, 2 => 200, 3 => 3, 4 => 6, 5 => 9, 6 => 12, 7 => 9, 8 => 6, 9 => 7, 10 => 8, 11 => 9, 12 => 10];
    $expectedTwoOne = [1 => 200, 2 => 200, 3 => 3, 4 => 6, 5 => 10, 6 => 14, 7 => 12, 8 => 6, 9 => 7, 10 => 8, 11 => 9, 12 => 10];
    $t->same($expectedOneOne, $sumByRowid($window1NullRows, 'PRECEDING', 1, 'FOLLOWING', 1));
    $t->same($expectedOneOne, $sumByRowid($window1NullRows, 'PRECEDING', 1, 'FOLLOWING', 1, [true]));
    $t->same($expectedTwoOne, $sumByRowid($window1NullRows, 'PRECEDING', 2, 'FOLLOWING', 1));
    $t->same($expectedTwoOne, $sumByRowid($window1NullRows, 'PRECEDING', 1, 'FOLLOWING', 2, [true]));
};

$windowBNullRows = [
    ['rowid' => 1, 'a' => null, 'b' => 1],
    ['rowid' => 2, 'a' => null, 'b' => 2],
    ['rowid' => 3, 'a' => null, 'b' => 3],
];

$tests['real upstream windowB.test 1 null range peers survive nulls placement and direction'] = static function (TestRunner $t) use ($sumByRowid, $windowBNullRows): void {
    $expected = [1 => 6, 2 => 6, 3 => 6];
    $t->same($expected, $sumByRowid($windowBNullRows, 'PRECEDING', 1, 'FOLLOWING', 1));
    $t->same($expected, $sumByRowid($windowBNullRows, 'PRECEDING', 1, 'FOLLOWING', 1, [], ['LAST']));
    $t->same($expected, $sumByRowid($windowBNullRows, 'PRECEDING', 1, 'FOLLOWING', 1, [true]));
    $t->same($expected, $sumByRowid($windowBNullRows, 'PRECEDING', 1, 'FOLLOWING', 1, [true], ['FIRST']));
    $t->same($expected, $sumByRowid($windowBNullRows, 'FOLLOWING', 1, 'FOLLOWING', 2, [], ['LAST']));
    $t->same($expected, $sumByRowid($windowBNullRows, 'PRECEDING', 2, 'PRECEDING', 1, [true], ['FIRST']));
};

$windowBMixedRows = [
    ['rowid' => 1, 'a' => null, 'b' => 1],
    ['rowid' => 2, 'a' => 45, 'b' => 2],
    ['rowid' => 3, 'a' => 66.2, 'b' => 3],
    ['rowid' => 4, 'a' => 'hello world', 'b' => 4],
    ['rowid' => 5, 'a' => 'hello world', 'b' => 5],
    ['rowid' => 6, 'a' => "\x12\x34", 'b' => 6],
    ['rowid' => 7, 'a' => "\x12\x34", 'b' => 7],
    ['rowid' => 8, 'a' => null, 'b' => 8],
];

$tests['real upstream windowB.test 2 mixed null numeric text blob offsets use peer fallback'] = static function (TestRunner $t) use ($sumByRowid, $windowBMixedRows): void {
    $expected = [1 => 9, 2 => null, 3 => null, 4 => 9, 5 => 9, 6 => 13, 7 => 13, 8 => 9];
    $t->same($expected, $sumByRowid($windowBMixedRows, 'PRECEDING', 1, 'PRECEDING', 2));
    $t->same($expected, $sumByRowid($windowBMixedRows, 'FOLLOWING', 2, 'FOLLOWING', 2));
    $t->same($expected, $sumByRowid($windowBMixedRows, 'PRECEDING', 1, 'PRECEDING', 2, [], ['LAST']));
    $t->same($expected, $sumByRowid($windowBMixedRows, 'FOLLOWING', 2, 'FOLLOWING', 2, [], ['LAST']));
};

$largeRows = [
    ['rowid' => 1, 'a' => 3029012920382354029, 'b' => 3029012920382354029],
    ['rowid' => 2, 'a' => 3578824042033200656, 'b' => 3578824042033200656],
];

$tests['real upstream window1.test 66 fractional range offsets preserve large integer totals'] = static function (TestRunner $t) use ($sumByRowid, $largeRows): void {
    $expected = [1 => 3029012920382354029, 2 => 3578824042033200656];
    $t->same($expected, $sumByRowid($largeRows, 'PRECEDING', 0.3, 'FOLLOWING', 10));
    $t->same($expected, $sumByRowid($largeRows, 'PRECEDING', 0.3, 'PRECEDING', 0.1));
    $t->same($expected, $sumByRowid($largeRows, 'FOLLOWING', 0.3, 'FOLLOWING', 10));
    $t->same($expected, $sumByRowid($largeRows, 'PRECEDING', 0.3, 'FOLLOWING', 10, [true]));
    $t->same($expected, $sumByRowid($largeRows, 'PRECEDING', 0.3, 'FOLLOWING', 10, [], ['LAST']));
    $t->same($expected, $sumByRowid($largeRows, 'PRECEDING', 1.0, 'PRECEDING', 2.0));
};

$dynamicSpecs = [
    ['PRECEDING', 1, 'FOLLOWING', 1, [], []],
    ['PRECEDING', 2, 'FOLLOWING', 1, [], []],
    ['PRECEDING', 1, 'FOLLOWING', 2, [true], []],
    ['FOLLOWING', 2, 'FOLLOWING', 2, [], ['LAST']],
    ['PRECEDING', 1, 'PRECEDING', 2, [], ['LAST']],
    ['CURRENT ROW', 0, 'CURRENT ROW', 0, [true], ['FIRST']],
    ['UNBOUNDED PRECEDING', 0, 'CURRENT ROW', 0, [], []],
    ['CURRENT ROW', 0, 'UNBOUNDED FOLLOWING', 0, [true], []],
];
$mixedKeys = [null, null, 1, 2, 3, 4, 5, 'a', 'b', 'hello', 'hello', "\x12\x34", "\x12\x34"];

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream window1 windowB dynamic mixed range corpus ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $mixedKeys, $dynamicSpecs, $sumByRowid, $oracleRangeSumByRowid): void {
        $rows = [];
        $rowCount = 6 + ($case % 7);
        for ($row = 0; $row < $rowCount; $row++) {
            $key = $mixedKeys[($case + ($row * 3)) % count($mixedKeys)];
            $rows[] = [
                'rowid' => $row + 1,
                'a' => $key,
                'b' => (($case + 5) * ($row + 3)) % 29 + 1,
            ];
        }

        [$start, $preceding, $end, $following, $descending, $nulls] = $dynamicSpecs[$case % count($dynamicSpecs)];
        $expected = $oracleRangeSumByRowid($rows, $start, $preceding, $end, $following, $descending, $nulls);
        $actual = $sumByRowid($rows, $start, $preceding, $end, $following, $descending, $nulls);
        $t->same($expected, $actual, 'dynamic mixed RANGE frame case ' . $case);
    };
}

return $tests;
