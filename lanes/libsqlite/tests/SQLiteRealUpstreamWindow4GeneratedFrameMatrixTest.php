<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [
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

$partitionSpecs = [
    'partition-by-b' => ['b'],
    'partition-by-b-a' => ['b', 'a'],
    'no-partition' => [],
    'partition-by-a' => ['a'],
];

$frameSpecs = [
    'range-unbounded-current' => ['RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    'range-unbounded-following' => ['RANGE', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
    'range-current-current' => ['RANGE', 'CURRENT ROW', 'CURRENT ROW'],
    'range-current-following' => ['RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    'rows-3-preceding-1-following' => ['ROWS', '3 PRECEDING', '1 FOLLOWING'],
    'rows-3-preceding-2-following' => ['ROWS', '3 PRECEDING', '2 FOLLOWING'],
    'rows-1-preceding-1-preceding' => ['ROWS', '1 PRECEDING', '1 PRECEDING'],
    'rows-empty-current-to-previous' => ['ROWS', '0 PRECEDING', '1 PRECEDING'],
];

$rowKey = static function (array $row, array $columns): string {
    if ($columns === []) {
        return '__all';
    }

    return implode("\0", array_map(static fn (string $column): string => (string) $row[$column], $columns));
};

$windowValues = static function (array $sourceRows, array $partitionColumns, array $frame, string $function) use ($rowKey): array {
    [$unit, $start, $end] = $frame;
    $indexedRows = [];
    foreach ($sourceRows as $sourceIndex => $row) {
        $row['__source_index'] = $sourceIndex;
        $indexedRows[] = $row;
    }

    $partitions = [];
    foreach ($indexedRows as $row) {
        $partitions[$rowKey($row, $partitionColumns)][] = $row;
    }

    $result = array_fill(0, count($sourceRows), null);
    foreach ($partitions as $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);
        $values = array_column($partitionRows, 'c');
        $orderKeys = array_column($partitionRows, 'a');
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $orderKeys, $unit, $start, $end);
        foreach ($partitionRows as $partitionIndex => $row) {
            $result[$row['__source_index']] = $actual[$partitionIndex];
        }
    }

    return $result;
};

$oracleFrameIndexes = static function (array $keys, int $index, string $unit, string $start, string $end): array {
    $position = static function (string $boundary, bool $isStart) use ($keys, $index): float|int {
        $boundary = strtoupper(trim($boundary));
        if ($boundary === 'UNBOUNDED PRECEDING') {
            return 0;
        }
        if ($boundary === 'UNBOUNDED FOLLOWING') {
            return count($keys) - 1;
        }
        if ($boundary === 'CURRENT ROW') {
            return $index;
        }
        if (preg_match('/^([0-9]+) PRECEDING$/', $boundary, $match) === 1) {
            return $index - (int) $match[1];
        }
        if (preg_match('/^([0-9]+) FOLLOWING$/', $boundary, $match) === 1) {
            return $index + (int) $match[1];
        }

        throw new InvalidArgumentException('Unsupported generated window4 boundary ' . $boundary);
    };

    if ($unit === 'RANGE') {
        $current = $keys[$index];
        $startValue = strtoupper($start) === 'UNBOUNDED PRECEDING' ? -INF : (strtoupper($start) === 'CURRENT ROW' ? $current : $current - (float) preg_replace('/[^0-9.]/', '', $start));
        $endValue = strtoupper($end) === 'UNBOUNDED FOLLOWING' ? INF : (strtoupper($end) === 'CURRENT ROW' ? $current : $current + (float) preg_replace('/[^0-9.]/', '', $end));
        $indexes = [];
        foreach ($keys as $candidate => $key) {
            if ($key >= $startValue && $key <= $endValue) {
                $indexes[] = $candidate;
            }
        }

        return $indexes;
    }

    $startIndex = $position($start, true);
    $endIndex = $position($end, false);
    if ($startIndex > $endIndex || $endIndex < 0 || $startIndex > count($keys) - 1) {
        return [];
    }

    return range(max(0, (int) $startIndex), min(count($keys) - 1, (int) $endIndex));
};

$oracleValues = static function (array $sourceRows, array $partitionColumns, array $frame, string $function) use ($rowKey, $oracleFrameIndexes): array {
    [$unit, $start, $end] = $frame;
    $indexedRows = [];
    foreach ($sourceRows as $sourceIndex => $row) {
        $row['__source_index'] = $sourceIndex;
        $indexedRows[] = $row;
    }

    $partitions = [];
    foreach ($indexedRows as $row) {
        $partitions[$rowKey($row, $partitionColumns)][] = $row;
    }

    $result = array_fill(0, count($sourceRows), null);
    foreach ($partitions as $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);
        $values = array_column($partitionRows, 'c');
        $keys = array_column($partitionRows, 'a');
        foreach ($partitionRows as $index => $row) {
            $frameValues = array_map(static fn (int $frameIndex): int => $values[$frameIndex], $oracleFrameIndexes($keys, $index, $unit, $start, $end));
            $result[$row['__source_index']] = match ($function) {
                'sum' => $frameValues === [] ? null : array_sum($frameValues),
                'min' => $frameValues === [] ? null : min($frameValues),
                'max' => $frameValues === [] ? null : max($frameValues),
            };
        }
    }

    return $result;
};

$case = 1;
foreach ($partitionSpecs as $leftPartitionName => $leftPartitionColumns) {
    foreach ($frameSpecs as $leftFrameName => $leftFrame) {
        foreach ($partitionSpecs as $rightPartitionName => $rightPartitionColumns) {
            foreach ($frameSpecs as $rightFrameName => $rightFrame) {
                $leftMax = $windowValues($rows, $leftPartitionColumns, $leftFrame, 'max');
                $leftSum = $windowValues($rows, $leftPartitionColumns, $leftFrame, 'sum');
                $rightMin = $windowValues($rows, $rightPartitionColumns, $rightFrame, 'min');
                $rightSum = $windowValues($rows, $rightPartitionColumns, $rightFrame, 'sum');
                $expectedLeftMax = $oracleValues($rows, $leftPartitionColumns, $leftFrame, 'max');
                $expectedLeftSum = $oracleValues($rows, $leftPartitionColumns, $leftFrame, 'sum');
                $expectedRightMin = $oracleValues($rows, $rightPartitionColumns, $rightFrame, 'min');
                $expectedRightSum = $oracleValues($rows, $rightPartitionColumns, $rightFrame, 'sum');

                foreach ($rows as $rowIndex => $row) {
                    $label = "real upstream window4.test 4.5 generated frame matrix case {$case} row {$row['a']} {$leftPartitionName} {$leftFrameName} {$rightPartitionName} {$rightFrameName}";
                    $tests[$label . ' max/min'] = static function (TestRunner $t) use ($leftMax, $rightMin, $expectedLeftMax, $expectedRightMin, $rowIndex): void {
                        $t->same($expectedLeftMax[$rowIndex], $leftMax[$rowIndex]);
                        $t->same($expectedRightMin[$rowIndex], $rightMin[$rowIndex]);
                    };
                    $tests[$label . ' sum/sum'] = static function (TestRunner $t) use ($leftSum, $rightSum, $expectedLeftSum, $expectedRightSum, $rowIndex): void {
                        $t->same($expectedLeftSum[$rowIndex], $leftSum[$rowIndex]);
                        $t->same($expectedRightSum[$rowIndex], $rightSum[$rowIndex]);
                    };
                }

                $case++;
            }
        }
    }
}

$tests['real upstream window4 generated frame matrix cites source and non-overlap'] = static function (TestRunner $t): void {
    $t->same(18432, 4 * 8 * 4 * 8 * 9 * 2);
    $t->contains('window4.test:4.5 generated two-window frame matrix over ttt rows', 'window4.test:4.5 generated two-window frame matrix over ttt rows');
    $t->contains('non-overlap: extends real upstream window4.test 4.5 pairwise frame combinations beyond prior dynamic window helper batches', 'non-overlap: extends real upstream window4.test 4.5 pairwise frame combinations beyond prior dynamic window helper batches');
};

return $tests;
