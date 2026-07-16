<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window2BValues = [
    0, 74, 41, 74, 23, 99, 26, 33, 2, 89, 81, 96, 59, 38, 68, 39, 62, 91, 46, 6,
    99, 97, 27, 46, 78, 54, 97, 8, 67, 29, 93, 84, 77, 23, 16, 16, 93, 65, 35, 47,
    7, 86, 74, 61, 91, 85, 24, 85, 43, 59, 12, 32, 56, 3, 91, 22, 90, 55, 15, 28,
    89, 25, 47, 1, 56, 40, 43, 56, 16, 75, 36, 89, 98, 76, 81, 4, 94, 42, 30, 78,
    33, 29, 53, 63, 2, 87, 37, 80, 84, 72, 41, 9, 61, 73, 95, 65, 13, 58, 96, 98,
    1, 21, 74, 65, 35, 5, 73, 11, 51, 87, 41, 12, 8, 20, 31, 31, 15, 95, 22, 73,
    79, 88, 34, 8, 11, 49, 34, 90, 59, 96, 60, 55, 75, 77, 44, 2, 7, 85, 57, 74,
    29, 70, 59, 19, 39, 26, 26, 47, 80, 90, 36, 58, 47, 9, 72, 72, 66, 33, 93, 75,
    64, 81, 9, 23, 37, 13, 12, 14, 62, 91, 36, 91, 33, 15, 34, 36, 99, 3, 95, 69,
    58, 52, 30, 50, 84, 10, 84, 33, 21, 39, 44, 58, 30, 38, 34, 83, 27, 82, 17, 7,
];

$rows = [];
foreach ($window2BValues as $index => $b) {
    $rows[] = ['a' => $index + 1, 'b' => $b, 'partition' => $b % 10];
}

$partitions = [];
foreach ($rows as $row) {
    $partitions[$row['partition']][] = $row;
}
ksort($partitions, SORT_NUMERIC);
foreach ($partitions as &$partitionRows) {
    usort($partitionRows, static fn (array $left, array $right): int => ($left['b'] <=> $right['b']) ?: ($left['a'] <=> $right['a']));
}
unset($partitionRows);

$oracle = static function (array $partitionRows, string $function): array {
    $result = [];
    foreach ($partitionRows as $row) {
        $frame = array_values(array_filter(
            array_column($partitionRows, 'b'),
            static fn (int $value): bool => $value <= $row['b'],
        ));

        $result[$row['a']] = match ($function) {
            'count' => count($frame),
            'sum' => array_sum($frame),
            'total' => (float) array_sum($frame),
            'avg' => (float) (array_sum($frame) / count($frame)),
            'min' => min($frame),
            'max' => max($frame),
        };
    }

    return $result;
};

$actuals = [];
$expected = [];
foreach (['sum', 'count', 'total', 'avg', 'min', 'max'] as $function) {
    $actuals[$function] = [];
    $expected[$function] = [];
    foreach ($partitions as $partitionRows) {
        $values = array_column($partitionRows, 'b');
        $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
            $function,
            $values,
            $values,
            'RANGE',
            'UNBOUNDED PRECEDING',
            'CURRENT ROW',
        );
        foreach ($partitionRows as $index => $row) {
            $actuals[$function][$row['a']] = $actual[$index];
        }
        $expected[$function] += $oracle($partitionRows, $function);
    }
    ksort($actuals[$function], SORT_NUMERIC);
    ksort($expected[$function], SORT_NUMERIC);
}

foreach (range(1, 200) as $rowId) {
    foreach (['sum', 'count', 'total', 'avg', 'min', 'max'] as $function) {
        $tests["real upstream window2.test 4 partition range $function row $rowId"] = static function (TestRunner $t) use ($actuals, $expected, $function, $rowId): void {
            $t->same($expected[$function][$rowId], $actuals[$function][$rowId]);
        };
    }
}

return $tests;
