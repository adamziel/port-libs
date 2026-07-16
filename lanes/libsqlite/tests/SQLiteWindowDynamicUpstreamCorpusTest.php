<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$letters = range('a', 'j');
$ntileExpected = [
    1 => [1, 1, 1, 1, 1, 1, 1, 1, 1, 1],
    2 => [1, 1, 1, 1, 1, 2, 2, 2, 2, 2],
    3 => [1, 1, 1, 1, 2, 2, 2, 3, 3, 3],
    4 => [1, 1, 1, 2, 2, 2, 3, 3, 4, 4],
    5 => [1, 1, 2, 2, 3, 3, 4, 4, 5, 5],
    6 => [1, 1, 2, 2, 3, 3, 4, 4, 5, 6],
    7 => [1, 1, 2, 2, 3, 3, 4, 5, 6, 7],
    8 => [1, 1, 2, 2, 3, 4, 5, 6, 7, 8],
    9 => [1, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    10 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    11 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    12 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    13 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    14 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    15 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    16 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    17 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    18 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    19 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
];

foreach ($ntileExpected as $bucketCount => $expectedBuckets) {
    $tests['real upstream window4 ntile ' . $bucketCount . ' distributes generated t3 rows'] = static function (TestRunner $t) use ($letters, $bucketCount, $expectedBuckets): void {
        $actual = SQLiteWindowFunction::ntile($letters, $bucketCount);
        foreach ($letters as $index => $letter) {
            $t->same(chr(ord('a') + $index), $letter, "window4.test 1.{$bucketCount} row label {$index}");
            $t->same($expectedBuckets[$index], $actual[$index], "window4.test 1.{$bucketCount} {$letter}");
        }
    };
}

$t4Ids = range(1, 10);
$t4Text = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
$t4Nth = [9, 3, 2, 10, 5, 1, 1, 2, 10, 4];

$window4Cases = [
    '2.1 dynamic nth_value b by c' => [
        static fn (): array => SQLiteWindowFunction::nthValueByRow($t4Text, $t4Nth, $t4Ids),
        [null, null, 'B', null, 'E', 'A', 'A', 'B', null, 'D'],
    ],
    '2.2.1 lead default' => [
        static fn (): array => SQLiteWindowFunction::lead($t4Text),
        ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null],
    ],
    '2.2.2 lead offset two' => [
        static fn (): array => SQLiteWindowFunction::lead($t4Text, 2),
        ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', null, null],
    ],
    '2.2.3 lead offset three default text' => [
        static fn (): array => SQLiteWindowFunction::lead($t4Text, 3, 'abc'),
        ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'abc', 'abc', 'abc'],
    ],
    '2.3.1 lag default' => [
        static fn (): array => SQLiteWindowFunction::lag($t4Text),
        [null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
    ],
    '2.3.2 lag offset two' => [
        static fn (): array => SQLiteWindowFunction::lag($t4Text, 2),
        [null, null, 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'],
    ],
    '2.3.3 lag offset three default text' => [
        static fn (): array => SQLiteWindowFunction::lag($t4Text, 3, 'abc'),
        ['abc', 'abc', 'abc', 'A', 'B', 'C', 'D', 'E', 'F', 'G'],
    ],
    '2.4.1 following group concat' => [
        static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $t4Text, $t4Ids, 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'),
        ['A,B,C,D,E,F,G,H,I,J', 'B,C,D,E,F,G,H,I,J', 'C,D,E,F,G,H,I,J', 'D,E,F,G,H,I,J', 'E,F,G,H,I,J', 'F,G,H,I,J', 'G,H,I,J', 'H,I,J', 'I,J', 'J'],
    ],
];

foreach ($window4Cases as $name => [$actual, $expected]) {
    $tests['real upstream window4 ' . $name] = static function (TestRunner $t) use ($actual, $expected, $name): void {
        $values = $actual();
        foreach ($expected as $index => $expectedValue) {
            $t->same($expectedValue, $values[$index], "window4.test {$name} row {$index}");
        }
    };
}

$t5Ids = [1, 3, 5, 2, 4];
$t5ValuesInOrderByB = ['one', 'three', 'five', 'two', 'four'];
$t5DynamicNthInOrderByB = [5, 3, 1, 4, 2];
$tests['real upstream window4 3.1 nth_value over non unique text order'] = static function (TestRunner $t) use ($t5ValuesInOrderByB, $t5DynamicNthInOrderByB): void {
    $actual = SQLiteWindowFunction::nthValueByRow($t5ValuesInOrderByB, $t5DynamicNthInOrderByB, ['A', 'A', 'A', 'B', 'B'], 'GROUPS');
    $expected = [null, 'five', 'one', 'two', 'three'];
    foreach ($expected as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'window4.test 3.1 output row ' . $index);
    }
};

$tests['real upstream window4 3.2 nth_value restarts by partition'] = static function (TestRunner $t): void {
    $aPartition = SQLiteWindowFunction::nthValueByRow(['one', 'three', 'five'], [5, 3, 1]);
    $bPartition = SQLiteWindowFunction::nthValueByRow(['two', 'four'], [4, 2]);
    $actual = [$aPartition[0], $aPartition[1], $aPartition[2], $bPartition[0], $bPartition[1]];
    $expected = [null, null, 'one', null, 'four'];
    foreach ($expected as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'window4.test 3.2 output row ' . $index);
    }
};

$tests['real upstream window4 3.3 opposing named windows count rows'] = static function (TestRunner $t): void {
    $forward = SQLiteWindowFunction::aggregateFrameBetweenValues('count', [1, 1, 1, 1, 1], [1, 2, 3, 4, 5], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $reverse = SQLiteWindowFunction::aggregateFrameBetweenValues('count', [1, 1, 1, 1, 1], [1, 2, 3, 4, 5], 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
    $expectedForward = [1, 2, 3, 4, 5];
    $expectedReverse = [5, 4, 3, 2, 1];
    foreach ($expectedForward as $index => $expectedValue) {
        $t->same($expectedValue, $forward[$index], 'window4.test 3.3 ascending count row ' . $index);
        $t->same($expectedReverse[$index], $reverse[$index], 'window4.test 3.3 descending count row ' . $index);
    }
};

$tests['real upstream window4 3.4 filtered max preserves prior even rows'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', [1, 2, 3, 4, 5], [1, 2, 3, 4, 5], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', [0, 1, 0, 1, 0]);
    $expected = [null, 2, 2, 4, 4];
    foreach ($expected as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'window4.test 3.4 output row ' . $index);
    }
};

$tttRows = [
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

$window4PartitionOracle = static function (
    array $rows,
    array $partitionColumns,
    string $startBoundary,
    string $endBoundary,
    string $function,
): array {
    $byPartition = [];
    foreach ($rows as $index => $row) {
        $key = $partitionColumns === []
            ? '__all'
            : implode("\0", array_map(static fn (string $column): string => (string) $row[$column], $partitionColumns));
        $byPartition[$key][] = $index;
    }

    $values = array_fill(0, count($rows), null);
    foreach ($byPartition as $indexes) {
        usort($indexes, static fn (int $left, int $right): int => $rows[$left]['a'] <=> $rows[$right]['a']);
        foreach ($indexes as $offset => $rowIndex) {
            $start = match ($startBoundary) {
                'UNBOUNDED PRECEDING' => 0,
                'CURRENT ROW' => $offset,
                default => throw new RuntimeException('Unsupported upstream window4 start boundary'),
            };
            $end = match ($endBoundary) {
                'CURRENT ROW' => $offset,
                'UNBOUNDED FOLLOWING' => count($indexes) - 1,
                default => throw new RuntimeException('Unsupported upstream window4 end boundary'),
            };
            if ($start > $end) {
                $frameValues = [];
            } else {
                $frameValues = [];
                for ($frameOffset = $start; $frameOffset <= $end; $frameOffset++) {
                    $frameValues[] = $rows[$indexes[$frameOffset]]['c'];
                }
            }
            $values[$rowIndex] = match ($function) {
                'sum' => array_sum($frameValues),
                'min' => $frameValues === [] ? null : min($frameValues),
                'max' => $frameValues === [] ? null : max($frameValues),
                default => throw new RuntimeException('Unsupported upstream window4 aggregate'),
            };
        }
    }

    return $values;
};

$window4PartitionTerms = [
    'partition by b' => ['b'],
    'partition by b,a' => ['b', 'a'],
    'no partition' => [],
    'partition by a' => ['a'],
];
$window4FrameTerms = [
    'unbounded preceding to current row' => ['UNBOUNDED PRECEDING', 'CURRENT ROW'],
    'unbounded preceding to unbounded following' => ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
    'current row to current row' => ['CURRENT ROW', 'CURRENT ROW'],
    'current row to unbounded following' => ['CURRENT ROW', 'UNBOUNDED FOLLOWING'],
];
$window4Case = 1;
foreach ($window4PartitionTerms as $leftName => $leftColumns) {
    foreach ($window4PartitionTerms as $rightName => $rightColumns) {
        if ($window4Case > 16) {
            break 2;
        }
        $testNumber = $window4Case;
        $tests["real upstream window4 4.5.{$testNumber}.1 max min {$leftName} versus {$rightName}"] = static function (TestRunner $t) use ($tttRows, $window4PartitionOracle, $leftColumns, $rightColumns, $leftName, $rightName, $testNumber): void {
            $actualMax = [];
            $actualMin = [];
            $leftByRow = $window4PartitionOracle($tttRows, $leftColumns, 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'max');
            $rightByRow = $window4PartitionOracle($tttRows, $rightColumns, 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'min');
            foreach ($tttRows as $rowIndex => $row) {
                $partitionIndexes = array_values(array_filter(
                    array_keys($tttRows),
                    static fn (int $candidate) => $leftColumns === [] || array_reduce(
                        $leftColumns,
                        static fn (bool $same, string $column): bool => $same && $tttRows[$candidate][$column] === $row[$column],
                        true,
                    ),
                ));
                usort($partitionIndexes, static fn (int $left, int $right): int => $tttRows[$left]['a'] <=> $tttRows[$right]['a']);
                $values = array_map(static fn (int $index): int => $tttRows[$index]['c'], $partitionIndexes);
                $keys = array_map(static fn (int $index): int => $tttRows[$index]['a'], $partitionIndexes);
                $actualMax[$rowIndex] = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW')[array_search($rowIndex, $partitionIndexes, true)];

                $rightPartitionIndexes = array_values(array_filter(
                    array_keys($tttRows),
                    static fn (int $candidate) => $rightColumns === [] || array_reduce(
                        $rightColumns,
                        static fn (bool $same, string $column): bool => $same && $tttRows[$candidate][$column] === $row[$column],
                        true,
                    ),
                ));
                usort($rightPartitionIndexes, static fn (int $left, int $right): int => $tttRows[$left]['a'] <=> $tttRows[$right]['a']);
                $rightValues = array_map(static fn (int $index): int => $tttRows[$index]['c'], $rightPartitionIndexes);
                $rightKeys = array_map(static fn (int $index): int => $tttRows[$index]['a'], $rightPartitionIndexes);
                $actualMin[$rowIndex] = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $rightValues, $rightKeys, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW')[array_search($rowIndex, $rightPartitionIndexes, true)];
            }

            foreach ($tttRows as $rowIndex => $_row) {
                $t->same($leftByRow[$rowIndex], $actualMax[$rowIndex], "window4.test 4.5.{$testNumber}.1 max {$leftName} row {$rowIndex}");
                $t->same($rightByRow[$rowIndex], $actualMin[$rowIndex], "window4.test 4.5.{$testNumber}.1 min {$rightName} row {$rowIndex}");
            }
        };
        $tests["real upstream window4 4.5.{$testNumber}.2 sum {$leftName} versus {$rightName}"] = static function (TestRunner $t) use ($tttRows, $window4PartitionOracle, $leftColumns, $rightColumns, $leftName, $rightName, $testNumber): void {
            $leftByRow = $window4PartitionOracle($tttRows, $leftColumns, 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'sum');
            $rightByRow = $window4PartitionOracle($tttRows, $rightColumns, 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'sum');
            foreach ($tttRows as $rowIndex => $row) {
                foreach ([[$leftColumns, $leftByRow, 'left', $leftName], [$rightColumns, $rightByRow, 'right', $rightName]] as [$columns, $expectedByRow, $side, $label]) {
                    $partitionIndexes = array_values(array_filter(
                        array_keys($tttRows),
                        static fn (int $candidate) => $columns === [] || array_reduce(
                            $columns,
                            static fn (bool $same, string $column): bool => $same && $tttRows[$candidate][$column] === $row[$column],
                            true,
                        ),
                    ));
                    usort($partitionIndexes, static fn (int $left, int $right): int => $tttRows[$left]['a'] <=> $tttRows[$right]['a']);
                    $values = array_map(static fn (int $index): int => $tttRows[$index]['c'], $partitionIndexes);
                    $keys = array_map(static fn (int $index): int => $tttRows[$index]['a'], $partitionIndexes);
                    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW')[array_search($rowIndex, $partitionIndexes, true)];
                    $t->same($expectedByRow[$rowIndex], $actual, "window4.test 4.5.{$testNumber}.2 {$side} sum {$label} row {$rowIndex}");
                }
            }
        };
        $window4Case++;
    }
}

foreach ($window4FrameTerms as $leftFrameName => [$leftStart, $leftEnd]) {
    foreach ($window4FrameTerms as $rightFrameName => [$rightStart, $rightEnd]) {
        $testNumber = $window4Case;
        $tests["real upstream window4 4.5.{$testNumber}.1 max min frame {$leftFrameName} versus {$rightFrameName}"] = static function (TestRunner $t) use ($tttRows, $window4PartitionOracle, $leftStart, $leftEnd, $rightStart, $rightEnd, $leftFrameName, $rightFrameName, $testNumber): void {
            $leftByRow = $window4PartitionOracle($tttRows, ['b'], $leftStart, $leftEnd, 'max');
            $rightByRow = $window4PartitionOracle($tttRows, ['b'], $rightStart, $rightEnd, 'min');
            foreach ($tttRows as $rowIndex => $row) {
                $partitionIndexes = array_values(array_filter(array_keys($tttRows), static fn (int $candidate): bool => $tttRows[$candidate]['b'] === $row['b']));
                usort($partitionIndexes, static fn (int $left, int $right): int => $tttRows[$left]['a'] <=> $tttRows[$right]['a']);
                $values = array_map(static fn (int $index): int => $tttRows[$index]['c'], $partitionIndexes);
                $keys = array_map(static fn (int $index): int => $tttRows[$index]['a'], $partitionIndexes);
                $partitionOffset = array_search($rowIndex, $partitionIndexes, true);
                $actualMax = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $keys, 'RANGE', $leftStart, $leftEnd)[$partitionOffset];
                $actualMin = SQLiteWindowFunction::aggregateFrameBetweenValues('min', $values, $keys, 'RANGE', $rightStart, $rightEnd)[$partitionOffset];
                $t->same($leftByRow[$rowIndex], $actualMax, "window4.test 4.5.{$testNumber}.1 max {$leftFrameName} row {$rowIndex}");
                $t->same($rightByRow[$rowIndex], $actualMin, "window4.test 4.5.{$testNumber}.1 min {$rightFrameName} row {$rowIndex}");
            }
        };
        $tests["real upstream window4 4.5.{$testNumber}.2 sum frame {$leftFrameName} versus {$rightFrameName}"] = static function (TestRunner $t) use ($tttRows, $window4PartitionOracle, $leftStart, $leftEnd, $rightStart, $rightEnd, $leftFrameName, $rightFrameName, $testNumber): void {
            $leftByRow = $window4PartitionOracle($tttRows, ['b'], $leftStart, $leftEnd, 'sum');
            $rightByRow = $window4PartitionOracle($tttRows, ['b'], $rightStart, $rightEnd, 'sum');
            foreach ($tttRows as $rowIndex => $row) {
                $partitionIndexes = array_values(array_filter(array_keys($tttRows), static fn (int $candidate): bool => $tttRows[$candidate]['b'] === $row['b']));
                usort($partitionIndexes, static fn (int $left, int $right): int => $tttRows[$left]['a'] <=> $tttRows[$right]['a']);
                $values = array_map(static fn (int $index): int => $tttRows[$index]['c'], $partitionIndexes);
                $keys = array_map(static fn (int $index): int => $tttRows[$index]['a'], $partitionIndexes);
                $partitionOffset = array_search($rowIndex, $partitionIndexes, true);
                $actualLeft = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', $leftStart, $leftEnd)[$partitionOffset];
                $actualRight = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $keys, 'RANGE', $rightStart, $rightEnd)[$partitionOffset];
                $t->same($leftByRow[$rowIndex], $actualLeft, "window4.test 4.5.{$testNumber}.2 left sum {$leftFrameName} row {$rowIndex}");
                $t->same($rightByRow[$rowIndex], $actualRight, "window4.test 4.5.{$testNumber}.2 right sum {$rightFrameName} row {$rowIndex}");
            }
        };
        $window4Case++;
    }
}

$boundaryCases = [
    '3.5.1 one preceding to two preceding empty' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '1 PRECEDING', '2 PRECEDING', [null, null, null, null, null]],
    '3.5.2 one preceding to one preceding' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '1 PRECEDING', '1 PRECEDING', [null, 'one', 'two', 'three', 'four']],
    '3.5.3 zero preceding to zero preceding' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '0 PRECEDING', '0 PRECEDING', ['one', 'two', 'three', 'four', 'five']],
    '3.6.1 two following to one following empty' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '2 FOLLOWING', '1 FOLLOWING', [null, null, null, null, null]],
    '3.6.2 one following to one following' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '1 FOLLOWING', '1 FOLLOWING', ['two', 'three', 'four', 'five', null]],
    '3.6.3 zero following to zero following' => ['max', ['one', 'two', 'three', 'four', 'five'], 'ROWS', '0 FOLLOWING', '0 FOLLOWING', ['one', 'two', 'three', 'four', 'five']],
];

foreach ($boundaryCases as $name => [$function, $values, $unit, $start, $end, $expected]) {
    $tests['real upstream window4 ' . $name] = static function (TestRunner $t) use ($function, $values, $unit, $start, $end, $expected, $name): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, range(1, count($values)), $unit, $start, $end);
        foreach ($expected as $index => $expectedValue) {
            $t->same($expectedValue, $actual[$index], "window4.test {$name} row {$index}");
        }
    };
}

$tttRows = [
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

$partitionedWindow = static function (string $function, string $start, string $end) use ($tttRows): array {
    $byPartition = [];
    foreach ($tttRows as $index => $row) {
        $byPartition[$row['b']][] = ['index' => $index, 'a' => $row['a'], 'c' => $row['c']];
    }

    $actual = array_fill(0, count($tttRows), null);
    foreach ($byPartition as $partitionRows) {
        usort($partitionRows, static fn (array $left, array $right): int => $left['a'] <=> $right['a']);
        $values = array_column($partitionRows, 'c');
        $keys = array_column($partitionRows, 'a');
        $windowValues = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, 'RANGE', $start, $end);
        foreach ($partitionRows as $offset => $row) {
            $actual[$row['index']] = $windowValues[$offset];
        }
    }

    return $actual;
};

$window45Cases = [
    '4.5.1.1 max cumulative and min cumulative by b' => [
        ['max', 'UNBOUNDED PRECEDING', 'CURRENT ROW'], ['min', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
        [[1, 1], [2, 2], [3, 3], [2, 1], [3, 2], [4, 3], [3, 1], [4, 2], [5, 3]],
    ],
    '4.5.1.2 sum cumulative duplicated by b' => [
        ['sum', 'UNBOUNDED PRECEDING', 'CURRENT ROW'], ['sum', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
        [[1, 1], [2, 2], [3, 3], [3, 3], [5, 5], [7, 7], [6, 6], [9, 9], [12, 12]],
    ],
    '4.5.18.1 max cumulative with min full partition' => [
        ['max', 'UNBOUNDED PRECEDING', 'CURRENT ROW'], ['min', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
        [[1, 1], [2, 2], [3, 3], [2, 1], [3, 2], [4, 3], [3, 1], [4, 2], [5, 3]],
    ],
    '4.5.18.2 sum cumulative with sum full partition' => [
        ['sum', 'UNBOUNDED PRECEDING', 'CURRENT ROW'], ['sum', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
        [[1, 6], [2, 9], [3, 12], [3, 6], [5, 9], [7, 12], [6, 6], [9, 9], [12, 12]],
    ],
    '4.5.19.1 max cumulative with min current peer' => [
        ['max', 'UNBOUNDED PRECEDING', 'CURRENT ROW'], ['min', 'CURRENT ROW', 'CURRENT ROW'],
        [[1, 1], [2, 2], [3, 3], [2, 2], [3, 3], [4, 4], [3, 3], [4, 4], [5, 5]],
    ],
    '4.5.19.2 sum cumulative with sum current peer' => [
        ['sum', 'UNBOUNDED PRECEDING', 'CURRENT ROW'], ['sum', 'CURRENT ROW', 'CURRENT ROW'],
        [[1, 1], [2, 2], [3, 3], [3, 2], [5, 3], [7, 4], [6, 3], [9, 4], [12, 5]],
    ],
    '4.5.20.1 max cumulative with min current to following' => [
        ['max', 'UNBOUNDED PRECEDING', 'CURRENT ROW'], ['min', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
        [[1, 1], [2, 2], [3, 3], [2, 2], [3, 3], [4, 4], [3, 3], [4, 4], [5, 5]],
    ],
    '4.5.20.2 sum cumulative with sum current to following' => [
        ['sum', 'UNBOUNDED PRECEDING', 'CURRENT ROW'], ['sum', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
        [[1, 6], [2, 9], [3, 12], [3, 5], [5, 7], [7, 9], [6, 3], [9, 4], [12, 5]],
    ],
    '4.5.21.1 max full partition with min cumulative' => [
        ['max', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'], ['min', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
        [[3, 1], [4, 2], [5, 3], [3, 1], [4, 2], [5, 3], [3, 1], [4, 2], [5, 3]],
    ],
    '4.5.21.2 sum full partition with sum cumulative' => [
        ['sum', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'], ['sum', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
        [[6, 1], [9, 2], [12, 3], [6, 3], [9, 5], [12, 7], [6, 6], [9, 9], [12, 12]],
    ],
    '4.5.22.1 max and min full partition' => [
        ['max', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'], ['min', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
        [[3, 1], [4, 2], [5, 3], [3, 1], [4, 2], [5, 3], [3, 1], [4, 2], [5, 3]],
    ],
    '4.5.22.2 sum full partition duplicated' => [
        ['sum', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'], ['sum', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'],
        [[6, 6], [9, 9], [12, 12], [6, 6], [9, 9], [12, 12], [6, 6], [9, 9], [12, 12]],
    ],
    '4.5.23.1 max full partition with min current peer' => [
        ['max', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'], ['min', 'CURRENT ROW', 'CURRENT ROW'],
        [[3, 1], [4, 2], [5, 3], [3, 2], [4, 3], [5, 4], [3, 3], [4, 4], [5, 5]],
    ],
    '4.5.23.2 sum full partition with sum current peer' => [
        ['sum', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'], ['sum', 'CURRENT ROW', 'CURRENT ROW'],
        [[6, 1], [9, 2], [12, 3], [6, 2], [9, 3], [12, 4], [6, 3], [9, 4], [12, 5]],
    ],
    '4.5.24.1 max full partition with min current to following' => [
        ['max', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'], ['min', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
        [[3, 1], [4, 2], [5, 3], [3, 2], [4, 3], [5, 4], [3, 3], [4, 4], [5, 5]],
    ],
    '4.5.24.2 sum full partition with sum current to following' => [
        ['sum', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'], ['sum', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
        [[6, 6], [9, 9], [12, 12], [6, 5], [9, 7], [12, 9], [6, 3], [9, 4], [12, 5]],
    ],
];

foreach ($window45Cases as $name => [$leftSpec, $rightSpec, $expected]) {
    $tests['real upstream window4 ' . $name] = static function (TestRunner $t) use ($partitionedWindow, $leftSpec, $rightSpec, $expected, $name): void {
        [$leftFunction, $leftStart, $leftEnd] = $leftSpec;
        [$rightFunction, $rightStart, $rightEnd] = $rightSpec;
        $leftValues = $partitionedWindow($leftFunction, $leftStart, $leftEnd);
        $rightValues = $partitionedWindow($rightFunction, $rightStart, $rightEnd);
        foreach ($expected as $index => [$expectedLeft, $expectedRight]) {
            $t->same($expectedLeft, $leftValues[$index], "window4.test {$name} left row {$index}");
            $t->same($expectedRight, $rightValues[$index], "window4.test {$name} right row {$index}");
        }
    };
}

$tests['real upstream windowE 3.1 numeric range preceding carries distant max'] = static function (TestRunner $t): void {
    $ids = [
        447, 448, 449, 452, 453, 454, 455, 456, 459, 460, 462, 463, 466, 467, 468, 469, 470, 473, 474, 475,
        476, 477, 480, 481, 482, 483, 484, 487, 488, 489, 490, 491, 494, 495, 496, 497, 498, 501, 502, 503,
        504, 505, 508, 509, 510, 511, 512, 515, 516, 517, 518, 519, 522, 523, 524, 525, 526, 529, 530, 531,
        532, 533, 536, 537, 538, 539, 540, 543, 544,
    ];
    $values = array_fill(0, count($ids), 0.0);
    $values[array_search(537, $ids, true)] = 1.0;
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('max', $values, $ids, 'RANGE', '366.0 PRECEDING', 'CURRENT ROW');
    foreach ($ids as $index => $id) {
        $t->same($id >= 537 ? 1.0 : 0.0, $actual[$index], 'windowE.test 3.1 id ' . $id);
    }
};

$windowETotalRows = [1, 9223372036854775807, 3, 4];
$windowETotalExpected = [9.223372036854776E+18, 9.223372036854776E+18, 7.0, 4.0];
foreach ([
    '4.1 unbounded following total' => ['UNBOUNDED FOLLOWING', $windowETotalExpected],
    '4.2 two following total' => ['2 FOLLOWING', $windowETotalExpected],
] as $name => [$end, $expected]) {
    $tests['real upstream windowE ' . $name] = static function (TestRunner $t) use ($windowETotalRows, $end, $expected, $name): void {
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('total', $windowETotalRows, range(1, 4), 'ROWS', 'CURRENT ROW', $end);
        foreach ($expected as $index => $expectedValue) {
            $t->same($expectedValue, $actual[$index], "windowE.test {$name} row {$index}");
        }
    };
}

$tests['real upstream windowE 5.1 integer sum rows current to two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3, 4], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    foreach ([6, 9, 7, 4] as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'windowE.test 5.1 row ' . $index);
    }
};

$tests['real upstream windowE 5.2 mixed integer real sum rows current to two following'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [-1, 9223372036854775807, 1, 0.5], [1, 2, 3, 4], 'ROWS', 'CURRENT ROW', '2 FOLLOWING');
    foreach ([9223372036854775807, 9.223372036854776E+18, 1.5, 0.5] as $index => $expectedValue) {
        $t->same($expectedValue, $actual[$index], 'windowE.test 5.2 row ' . $index);
    }
};

$tests['real upstream window dynamic rejects row count mismatch'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow(['a'], [1, 2], [1]));
};

$tests['real upstream window dynamic rejects non integer nth value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow(['a'], [1.5], [1]));
};

$tests['real upstream window dynamic rejects non positive nth value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValueByRow(['a'], [0], [1]));
};

return $tests;
