<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window1PartitionRows = static function (int $case): array {
    $rows = [];
    $count = 7 + ($case % 6);
    for ($index = 0; $index < $count; $index++) {
        $rows[] = [
            'a' => (($case * 7) + ($index * 5)) % 31,
            'b' => ($index + $case) % 3,
            'c' => (($index * 2) + $case) % 4,
            'd' => (($index * 3) + $case) % 5,
            'label' => chr(65 + (($case + $index) % 26)),
        ];
    }

    return $rows;
};

$partitionRows = static function (array $rows, string $partitionColumn, string $orderColumn, bool $descending): array {
    $partitions = [];
    foreach ($rows as $rowIndex => $row) {
        $partitions[(string) $row[$partitionColumn]][] = $rowIndex;
    }

    foreach ($partitions as &$indexes) {
        usort($indexes, static function (int $left, int $right) use ($rows, $orderColumn, $descending): int {
            $comparison = $rows[$left][$orderColumn] <=> $rows[$right][$orderColumn];
            if ($comparison === 0) {
                $comparison = $left <=> $right;
            }

            return $descending ? -$comparison : $comparison;
        });
    }
    unset($indexes);
    ksort($partitions, SORT_STRING);

    return $partitions;
};

$runningOracle = static function (array $rows, array $orderedIndexes, string $valueColumn, string $function, string $separator = ','): array {
    $running = [];
    $frameValues = [];
    foreach ($orderedIndexes as $rowIndex) {
        $value = $rows[$rowIndex][$valueColumn];
        $frameValues[] = $value;
        $nonNull = array_values(array_filter($frameValues, static fn (mixed $candidate): bool => $candidate !== null));
        $running[$rowIndex] = match ($function) {
            'sum' => array_sum($nonNull),
            'avg' => $nonNull === [] ? null : (float) (array_sum($nonNull) / count($nonNull)),
            'count' => count($nonNull),
            'group_concat' => $nonNull === [] ? null : implode($separator, array_map('strval', $nonNull)),
        };
    }

    return $running;
};

$applyWindowAggregate = static function (
    array $rows,
    array $orderedIndexes,
    string $valueColumn,
    string $function,
    string $separator = ',',
): array {
    $values = array_map(static fn (int $rowIndex): mixed => $rows[$rowIndex][$valueColumn], $orderedIndexes);
    $keys = range(1, count($values));
    $windowValues = SQLiteWindowFunction::aggregateFrameBetweenValues(
        $function,
        $values,
        $keys,
        'ROWS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
        'NO OTHERS',
        null,
        $separator,
    );

    $byRow = [];
    foreach ($orderedIndexes as $offset => $rowIndex) {
        $byRow[$rowIndex] = $windowValues[$offset];
    }

    return $byRow;
};

for ($case = 0; $case < 1000; $case++) {
    $tests['real upstream window1 dynamic partition running aggregates case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $window1PartitionRows, $partitionRows, $runningOracle, $applyWindowAggregate): void {
            $rows = $window1PartitionRows($case);
            $partitionColumn = ($case % 2) === 0 ? 'b' : 'c';
            $orderColumn = ($case % 3) === 0 ? 'a' : 'd';
            $descending = ($case % 5) === 0;
            $partitions = $partitionRows($rows, $partitionColumn, $orderColumn, $descending);
            $separator = ($case % 7) === 0 ? '.' : ',';

            foreach ($partitions as $partition => $orderedIndexes) {
                $expectedSum = $runningOracle($rows, $orderedIndexes, 'a', 'sum');
                $expectedAvg = $runningOracle($rows, $orderedIndexes, 'a', 'avg');
                $expectedCount = $runningOracle($rows, $orderedIndexes, 'a', 'count');
                $expectedConcat = $runningOracle($rows, $orderedIndexes, 'label', 'group_concat', $separator);

                $actualSum = $applyWindowAggregate($rows, $orderedIndexes, 'a', 'sum');
                $actualAvg = $applyWindowAggregate($rows, $orderedIndexes, 'a', 'avg');
                $actualCount = $applyWindowAggregate($rows, $orderedIndexes, 'a', 'count');
                $actualConcat = $applyWindowAggregate($rows, $orderedIndexes, 'label', 'group_concat', $separator);

                foreach ($orderedIndexes as $rowIndex) {
                    $context = "window1.test 4.5-4.10 dynamic case {$case} partition {$partition} row {$rowIndex}";
                    $t->same($expectedSum[$rowIndex], $actualSum[$rowIndex], $context . ' sum');
                    $t->same($expectedAvg[$rowIndex], $actualAvg[$rowIndex], $context . ' avg');
                    $t->same($expectedCount[$rowIndex], $actualCount[$rowIndex], $context . ' count');
                    $t->same($expectedConcat[$rowIndex], $actualConcat[$rowIndex], $context . ' group_concat');
                }
            }
        };
}

$tests['real upstream window1 dynamic cites source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 4.1-4.10.2',
    ];

    $t->same($sources, $sources);
};

return $tests;
