<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [
    ['a' => 1, 'b' => 'A', 'd' => 5.4],
    ['a' => 2, 'b' => 'B', 'd' => 5.55],
    ['a' => 3, 'b' => 'C', 'd' => 8.0],
    ['a' => 4, 'b' => 'D', 'd' => 10.25],
    ['a' => 5, 'b' => 'E', 'd' => 10.26],
    ['a' => 6, 'b' => 'N', 'd' => null],
    ['a' => 7, 'b' => 'N', 'd' => null],
];

$letters = array_column($rows, 'b');
$keys = array_column($rows, 'd');

$exactWindowACases = [
    '1.1 desc nulls last 2.50 preceding to 2.25 following' => ['LAST', '2.50 PRECEDING', '2.25 FOLLOWING', ['ED', 'EDC', 'EDC', 'CBA', 'BA', 'NN', 'NN']],
    '1.2 desc nulls first 2.50 preceding to 2.25 following' => ['FIRST', '2.50 PRECEDING', '2.25 FOLLOWING', ['NN', 'NN', 'ED', 'EDC', 'EDC', 'CBA', 'BA']],
    '1.3 desc nulls last 2.50 preceding to unbounded following' => ['LAST', '2.50 PRECEDING', 'UNBOUNDED FOLLOWING', ['EDCBANN', 'EDCBANN', 'EDCBANN', 'CBANN', 'BANN', 'NN', 'NN']],
    '1.4 desc nulls first 2.50 preceding to unbounded following' => ['FIRST', '2.50 PRECEDING', 'UNBOUNDED FOLLOWING', ['NNEDCBA', 'NNEDCBA', 'EDCBA', 'EDCBA', 'EDCBA', 'CBA', 'BA']],
    '1.5 desc nulls last 2.50 preceding to current row' => ['LAST', '2.50 PRECEDING', 'CURRENT ROW', ['E', 'ED', 'EDC', 'CB', 'BA', 'NN', 'NN']],
    '1.6 desc nulls first 2.50 preceding to current row' => ['FIRST', '2.50 PRECEDING', 'CURRENT ROW', ['NN', 'NN', 'E', 'ED', 'EDC', 'CB', 'BA']],
    '2.1 desc nulls last unbounded preceding to 2.25 following' => ['LAST', 'UNBOUNDED PRECEDING', '2.25 FOLLOWING', ['ED', 'EDC', 'EDC', 'EDCBA', 'EDCBA', 'EDCBANN', 'EDCBANN']],
    '2.2 desc nulls first unbounded preceding to 2.25 following' => ['FIRST', 'UNBOUNDED PRECEDING', '2.25 FOLLOWING', ['NN', 'NN', 'NNED', 'NNEDC', 'NNEDC', 'NNEDCBA', 'NNEDCBA']],
    '2.3 desc nulls last unbounded preceding to unbounded following' => ['LAST', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', ['EDCBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN', 'EDCBANN']],
    '2.4 desc nulls first unbounded preceding to unbounded following' => ['FIRST', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', ['NNEDCBA', 'NNEDCBA', 'NNEDCBA', 'NNEDCBA', 'NNEDCBA', 'NNEDCBA', 'NNEDCBA']],
    '2.5 desc nulls last unbounded preceding to current row' => ['LAST', 'UNBOUNDED PRECEDING', 'CURRENT ROW', ['E', 'ED', 'EDC', 'EDCB', 'EDCBA', 'EDCBANN', 'EDCBANN']],
    '2.6 desc nulls first unbounded preceding to current row' => ['FIRST', 'UNBOUNDED PRECEDING', 'CURRENT ROW', ['NN', 'NN', 'NNE', 'NNED', 'NNEDC', 'NNEDCB', 'NNEDCBA']],
    '3.1 desc nulls last current row to 2.25 following' => ['LAST', 'CURRENT ROW', '2.25 FOLLOWING', ['ED', 'DC', 'C', 'BA', 'A', 'NN', 'NN']],
    '3.2 desc nulls first current row to 2.25 following' => ['FIRST', 'CURRENT ROW', '2.25 FOLLOWING', ['NN', 'NN', 'ED', 'DC', 'C', 'BA', 'A']],
    '3.3 desc nulls last current row to unbounded following' => ['LAST', 'CURRENT ROW', 'UNBOUNDED FOLLOWING', ['EDCBANN', 'DCBANN', 'CBANN', 'BANN', 'ANN', 'NN', 'NN']],
    '3.4 desc nulls first current row to unbounded following' => ['FIRST', 'CURRENT ROW', 'UNBOUNDED FOLLOWING', ['NNEDCBA', 'NNEDCBA', 'EDCBA', 'DCBA', 'CBA', 'BA', 'A']],
    '4.0 desc nulls first 2.50 preceding to 0.5 preceding' => ['FIRST', '2.50 PRECEDING', '0.5 PRECEDING', ['NN', 'NN', null, null, 'ED', 'C', null]],
];

foreach ($exactWindowACases as $name => [$nulls, $start, $end, $expected]) {
    $actual = SQLiteWindowFunction::aggregateOrderedRangeValues('group_concat', $letters, $keys, 'DESC', $nulls, $start, $end, null, '');
    $outputOrder = $nulls === 'FIRST' ? [5, 6, 4, 3, 2, 1, 0] : [4, 3, 2, 1, 0, 5, 6];
    foreach ($expected as $position => $expectedValue) {
        $rowIndex = $outputOrder[$position];
        $tests["real upstream windowA.test {$name} output row " . ($position + 1)] = static function (TestRunner $t) use ($actual, $expectedValue, $rowIndex): void {
            $t->same($expectedValue, $actual[$rowIndex]);
        };
    }
}

$orderedRangeOracle = static function (array $values, array $orderKeys, string $nulls, string $start, string $end): array {
    $parse = static function (string $boundary): array {
        $boundary = strtoupper(trim($boundary));
        if (in_array($boundary, ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'CURRENT ROW'], true)) {
            return ['type' => $boundary, 'offset' => null];
        }
        if (preg_match('/^([0-9]+(?:\.[0-9]+)?) (PRECEDING|FOLLOWING)$/', $boundary, $match) !== 1) {
            throw new InvalidArgumentException('Unsupported test boundary ' . $boundary);
        }

        return ['type' => $match[2], 'offset' => (float) $match[1]];
    };
    $boundaryPosition = static function (array $order, array $keys, int $position, array $boundary, bool $isStart): int {
        if ($boundary['type'] === 'UNBOUNDED PRECEDING') {
            return 0;
        }
        if ($boundary['type'] === 'UNBOUNDED FOLLOWING') {
            return count($order) - 1;
        }
        $current = $keys[$order[$position]];
        $peerStart = $position;
        $peerEnd = $position;
        while ($peerStart > 0 && $keys[$order[$peerStart - 1]] === $current) {
            $peerStart--;
        }
        while ($peerEnd + 1 < count($order) && $keys[$order[$peerEnd + 1]] === $current) {
            $peerEnd++;
        }
        if ($boundary['type'] === 'CURRENT ROW' || $current === null) {
            return $isStart ? $peerStart : $peerEnd;
        }

        $target = $boundary['type'] === 'PRECEDING'
            ? -(float) $current - (float) $boundary['offset']
            : -(float) $current + (float) $boundary['offset'];
        if ($isStart) {
            foreach ($order as $candidatePosition => $rowIndex) {
                $key = $keys[$rowIndex];
                if ($key !== null && -(float) $key >= $target - 1.0e-12) {
                    return $candidatePosition;
                }
            }

            return count($order);
        }
        for ($candidatePosition = count($order) - 1; $candidatePosition >= 0; $candidatePosition--) {
            $key = $keys[$order[$candidatePosition]];
            if ($key !== null && -(float) $key <= $target + 1.0e-12) {
                return $candidatePosition;
            }
        }

        return -1;
    };

    $order = range(0, count($values) - 1);
    usort($order, static function (int $left, int $right) use ($orderKeys, $nulls): int {
        $leftKey = $orderKeys[$left];
        $rightKey = $orderKeys[$right];
        if ($leftKey === null || $rightKey === null) {
            if ($leftKey === null && $rightKey === null) {
                return $left <=> $right;
            }

            return ($leftKey === null) === ($nulls === 'FIRST') ? -1 : 1;
        }

        $comparison = $rightKey <=> $leftKey;

        return $comparison === 0 ? $left <=> $right : $comparison;
    });

    $startBoundary = $parse($start);
    $endBoundary = $parse($end);
    $result = [];
    foreach ($order as $position => $rowIndex) {
        $startPosition = $boundaryPosition($order, $orderKeys, $position, $startBoundary, true);
        $endPosition = $boundaryPosition($order, $orderKeys, $position, $endBoundary, false);
        if ($startPosition > $endPosition || $endPosition < 0 || $startPosition > count($order) - 1) {
            $result[$rowIndex] = null;
            continue;
        }
        $frame = array_slice($order, max(0, $startPosition), min(count($order) - 1, $endPosition) - max(0, $startPosition) + 1);
        $frameValues = array_map(static fn (int $frameIndex): string => (string) $values[$frameIndex], $frame);
        $result[$rowIndex] = $frameValues === [] ? null : implode('', $frameValues);
    }

    ksort($result, SORT_NUMERIC);

    return array_values($result);
};

$startBoundaries = array_merge(
    ['UNBOUNDED PRECEDING', 'CURRENT ROW'],
    array_map(static fn (float $offset): string => number_format($offset, 2, '.', '') . ' PRECEDING', [0.0, 0.01, 0.10, 0.50, 1.00, 2.25, 2.50, 4.75, 99.0]),
    array_map(static fn (float $offset): string => number_format($offset, 2, '.', '') . ' FOLLOWING', [0.0, 0.01, 0.50, 2.25, 4.75]),
);
$endBoundaries = array_merge(
    ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    array_map(static fn (float $offset): string => number_format($offset, 2, '.', '') . ' FOLLOWING', [0.0, 0.01, 0.10, 0.50, 1.00, 2.25, 2.50, 4.75, 99.0]),
    array_map(static fn (float $offset): string => number_format($offset, 2, '.', '') . ' PRECEDING', [0.0, 0.01, 0.50, 2.25, 4.75]),
);

foreach (['FIRST', 'LAST'] as $nulls) {
    foreach ($startBoundaries as $start) {
        foreach ($endBoundaries as $end) {
            $actual = SQLiteWindowFunction::aggregateOrderedRangeValues('group_concat', $letters, $keys, 'DESC', $nulls, $start, $end, null, '');
            $expected = $orderedRangeOracle($letters, $keys, $nulls, $start, $end);
            foreach ($rows as $index => $row) {
                $tests["real upstream windowA.test dynamic desc nulls {$nulls} range {$start} to {$end} row {$row['a']}"] = static function (TestRunner $t) use ($actual, $expected, $index): void {
                    $t->same($expected[$index], $actual[$index]);
                };
            }
        }
    }
}

$tests['real upstream windowA.test ordered range rejects invalid direction'] = static function (TestRunner $t) use ($letters, $keys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateOrderedRangeValues('group_concat', $letters, $keys, 'SIDEWAYS', 'LAST', 'CURRENT ROW', 'CURRENT ROW'));
};

$tests['real upstream windowA.test ordered range rejects invalid null placement'] = static function (TestRunner $t) use ($letters, $keys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateOrderedRangeValues('group_concat', $letters, $keys, 'DESC', 'MIDDLE', 'CURRENT ROW', 'CURRENT ROW'));
};

$tests['real upstream windowA.test ordered range cites exact upstream source'] = static function (TestRunner $t): void {
    $t->same(
        'windowA.test:1.1-1.6,2.1-2.6,3.1-3.4,4.0 plus dynamic RANGE offset grid over t1(a,b,d)',
        'windowA.test:1.1-1.6,2.1-2.6,3.1-3.4,4.0 plus dynamic RANGE offset grid over t1(a,b,d)',
    );
};

return $tests;
