<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [1, 2, 3, 4, 5, 6];
$order = [1, 2, 3, 4, 5, 6];

$sumCases = [
    'window2.test 2.1 rows 1000 preceding to 1 following' => ['UNBOUNDED PRECEDING', '1 FOLLOWING', [3, 6, 10, 15, 21, 21]],
    'window2.test 2.2 rows unbounded preceding to unbounded following' => ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', [21, 21, 21, 21, 21, 21]],
    'window2.test 2.3 rows 1 preceding to unbounded following' => ['1 PRECEDING', 'UNBOUNDED FOLLOWING', [21, 21, 20, 18, 15, 11]],
    'window2.test 2.4 rows 1 preceding to 1 following' => ['1 PRECEDING', '1 FOLLOWING', [3, 6, 9, 12, 15, 11]],
    'window2.test 2.5 rows 1 preceding to current row' => ['1 PRECEDING', 'CURRENT ROW', [1, 3, 5, 7, 9, 11]],
    'window2.test 2.8 rows current row to 2 following' => ['CURRENT ROW', '2 FOLLOWING', [6, 9, 12, 15, 11, 6]],
    'window2.test 2.9 rows unbounded preceding to 2 following' => ['UNBOUNDED PRECEDING', '2 FOLLOWING', [6, 10, 15, 21, 21, 21]],
    'window2.test 2.11 rows 2 preceding to current row' => ['2 PRECEDING', 'CURRENT ROW', [1, 3, 6, 9, 12, 15]],
    'window2.test 2.13 rows 2 preceding to unbounded following' => ['2 PRECEDING', 'UNBOUNDED FOLLOWING', [21, 21, 21, 20, 18, 15]],
    'window2.test 2.14 rows 3 preceding to 1 preceding' => ['3 PRECEDING', '1 PRECEDING', [null, 1, 3, 6, 9, 12]],
    'window2.test 2.17 rows 1 preceding to 2 preceding empty frame' => ['1 PRECEDING', '2 PRECEDING', [null, null, null, null, null, null]],
    'window2.test 2.20 rows 1 following to 2 following' => ['1 FOLLOWING', '2 FOLLOWING', [5, 7, 9, 11, 6, null]],
    'window2.test 2.21 rows 1 following to unbounded following' => ['1 FOLLOWING', 'UNBOUNDED FOLLOWING', [20, 18, 15, 11, 6, null]],
    'window2.test 2.23 rows current row to unbounded following' => ['CURRENT ROW', 'UNBOUNDED FOLLOWING', [21, 20, 18, 15, 11, 6]],
    'window2.test 2.25 rows current row to current row' => ['CURRENT ROW', 'CURRENT ROW', [1, 2, 3, 4, 5, 6]],
];

foreach ($sumCases as $name => [$start, $end, $expected]) {
    $actual = static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $order, 'ROWS', $start, $end);
    $tests['real upstream corpus window functions dynamic ' . $name . ' full vector'] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
    foreach ($expected as $index => $value) {
        $tests['real upstream corpus window functions dynamic ' . $name . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($actual, $index, $value): void {
            $rows = $actual();
            $t->same($value, $rows[$index]);
        };
    }
}

$rowFrameIndexes = [
    'window2.test 2.1 rows 1000 preceding to 1 following' => ['UNBOUNDED PRECEDING', '1 FOLLOWING', [[0, 1], [0, 1, 2], [0, 1, 2, 3], [0, 1, 2, 3, 4], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5]]],
    'window2.test 2.2 rows unbounded preceding to unbounded following' => ['UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', [[0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5]]],
    'window2.test 2.3 rows 1 preceding to unbounded following' => ['1 PRECEDING', 'UNBOUNDED FOLLOWING', [[0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [1, 2, 3, 4, 5], [2, 3, 4, 5], [3, 4, 5], [4, 5]]],
    'window2.test 2.4 rows 1 preceding to 1 following' => ['1 PRECEDING', '1 FOLLOWING', [[0, 1], [0, 1, 2], [1, 2, 3], [2, 3, 4], [3, 4, 5], [4, 5]]],
    'window2.test 2.5 rows 1 preceding to current row' => ['1 PRECEDING', 'CURRENT ROW', [[0], [0, 1], [1, 2], [2, 3], [3, 4], [4, 5]]],
    'window2.test 2.8 rows current row to 2 following' => ['CURRENT ROW', '2 FOLLOWING', [[0, 1, 2], [1, 2, 3], [2, 3, 4], [3, 4, 5], [4, 5], [5]]],
    'window2.test 2.9 rows unbounded preceding to 2 following' => ['UNBOUNDED PRECEDING', '2 FOLLOWING', [[0, 1, 2], [0, 1, 2, 3], [0, 1, 2, 3, 4], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5]]],
    'window2.test 2.11 rows 2 preceding to current row' => ['2 PRECEDING', 'CURRENT ROW', [[0], [0, 1], [0, 1, 2], [1, 2, 3], [2, 3, 4], [3, 4, 5]]],
    'window2.test 2.13 rows 2 preceding to unbounded following' => ['2 PRECEDING', 'UNBOUNDED FOLLOWING', [[0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [1, 2, 3, 4, 5], [2, 3, 4, 5], [3, 4, 5]]],
    'window2.test 2.14 rows 3 preceding to 1 preceding' => ['3 PRECEDING', '1 PRECEDING', [[], [0], [0, 1], [0, 1, 2], [1, 2, 3], [2, 3, 4]]],
    'window2.test 2.17 rows 1 preceding to 2 preceding empty frame' => ['1 PRECEDING', '2 PRECEDING', [[], [], [], [], [], []]],
    'window2.test 2.20 rows 1 following to 2 following' => ['1 FOLLOWING', '2 FOLLOWING', [[1, 2], [2, 3], [3, 4], [4, 5], [5], []]],
    'window2.test 2.21 rows 1 following to unbounded following' => ['1 FOLLOWING', 'UNBOUNDED FOLLOWING', [[1, 2, 3, 4, 5], [2, 3, 4, 5], [3, 4, 5], [4, 5], [5], []]],
    'window2.test 2.23 rows current row to unbounded following' => ['CURRENT ROW', 'UNBOUNDED FOLLOWING', [[0, 1, 2, 3, 4, 5], [1, 2, 3, 4, 5], [2, 3, 4, 5], [3, 4, 5], [4, 5], [5]]],
    'window2.test 2.25 rows current row to current row' => ['CURRENT ROW', 'CURRENT ROW', [[0], [1], [2], [3], [4], [5]]],
];

$expectedFor = static function (array $frameIndexes, string $function) use ($values): array {
    return array_map(static function (array $indexes) use ($values, $function): mixed {
        $frame = array_map(static fn (int $index): int => $values[$index], $indexes);
        if ($function === 'count') {
            return count($frame);
        }
        if ($frame === []) {
            return $function === 'total' ? 0.0 : null;
        }
        return match ($function) {
            'total' => (float) array_sum($frame),
            'avg' => (float) (array_sum($frame) / count($frame)),
            'min' => min($frame),
            'max' => max($frame),
            'group_concat' => implode(',', array_map(static fn (int $value): string => (string) $value, $frame)),
            default => null,
        };
    }, $frameIndexes);
};

foreach ($rowFrameIndexes as $name => [$start, $end, $frameIndexes]) {
    foreach (['total', 'avg', 'min', 'max', 'group_concat'] as $function) {
        $expected = $expectedFor($frameIndexes, $function);
        $actual = static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $order, 'ROWS', $start, $end);
        foreach ($expected as $index => $value) {
            $tests['real upstream corpus window functions dynamic ' . $name . ' ' . $function . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($actual, $index, $value): void {
                $rows = $actual();
                $t->same($value, $rows[$index]);
            };
        }
    }
}

$countCases = [
    'window2.test 2.14 rows 3 preceding to 1 preceding count' => ['3 PRECEDING', '1 PRECEDING', [0, 1, 2, 3, 3, 3]],
    'window2.test 2.17 rows 1 preceding to 2 preceding empty count' => ['1 PRECEDING', '2 PRECEDING', [0, 0, 0, 0, 0, 0]],
    'window2.test 2.20 rows 1 following to 2 following count' => ['1 FOLLOWING', '2 FOLLOWING', [2, 2, 2, 2, 1, 0]],
    'window2.test 2.21 rows 1 following to unbounded following count' => ['1 FOLLOWING', 'UNBOUNDED FOLLOWING', [5, 4, 3, 2, 1, 0]],
    'window2.test 2.25 rows current row to current row count' => ['CURRENT ROW', 'CURRENT ROW', [1, 1, 1, 1, 1, 1]],
];

foreach ($countCases as $name => [$start, $end, $expected]) {
    $actual = static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('count', $values, $order, 'ROWS', $start, $end);
    foreach ($expected as $index => $value) {
        $tests['real upstream corpus window functions dynamic ' . $name . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($actual, $index, $value): void {
            $rows = $actual();
            $t->same($value, $rows[$index]);
        };
    }
}

$rangeKeys = [1, 1, 2, 3, 3, 4];
$rangeValues = [5, 10, 15, 20, 25, 30];
$rangeCases = [
    'window2.test 2.29 range current row to unbounded following peer sums' => ['CURRENT ROW', 'UNBOUNDED FOLLOWING', [105, 105, 90, 75, 75, 30]],
    'window3.test generated range current row to current row peer sums' => ['CURRENT ROW', 'CURRENT ROW', [15, 15, 15, 45, 45, 30]],
    'window3.test generated range 1 preceding to current row peer sums' => ['1 PRECEDING', 'CURRENT ROW', [15, 15, 30, 60, 60, 75]],
    'window3.test generated range current row to 1 following peer sums' => ['CURRENT ROW', '1 FOLLOWING', [30, 30, 60, 75, 75, 30]],
    'window3.test generated range 1 following to 2 following peer sums' => ['1 FOLLOWING', '2 FOLLOWING', [60, 60, 75, 30, 30, null]],
];

foreach ($rangeCases as $name => [$start, $end, $expected]) {
    $actual = static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $rangeValues, $rangeKeys, 'RANGE', $start, $end);
    $tests['real upstream corpus window functions dynamic ' . $name . ' full vector'] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
    foreach ($expected as $index => $value) {
        $tests['real upstream corpus window functions dynamic ' . $name . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($actual, $index, $value): void {
            $rows = $actual();
            $t->same($value, $rows[$index]);
        };
    }
}

$groupCases = [
    'window3.test generated groups current group to current group concat' => ['CURRENT ROW', 'CURRENT ROW', ['5,10', '5,10', '15', '20,25', '20,25', '30']],
    'window3.test generated groups 1 preceding to current group concat' => ['1 PRECEDING', 'CURRENT ROW', ['5,10', '5,10', '5,10,15', '15,20,25', '15,20,25', '20,25,30']],
    'window3.test generated groups current group to 1 following concat' => ['CURRENT ROW', '1 FOLLOWING', ['5,10,15', '5,10,15', '15,20,25', '20,25,30', '20,25,30', '30']],
    'window3.test generated groups 1 following to 2 following concat' => ['1 FOLLOWING', '2 FOLLOWING', ['15,20,25', '15,20,25', '20,25,30', '30', '30', null]],
    'window3.test generated groups 1 preceding to 2 preceding empty first peer' => ['1 PRECEDING', '2 PRECEDING', [null, null, null, null, null, null]],
];

foreach ($groupCases as $name => [$start, $end, $expected]) {
    $actual = static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $rangeValues, $rangeKeys, 'GROUPS', $start, $end);
    $tests['real upstream corpus window functions dynamic ' . $name . ' full vector'] = static function (TestRunner $t) use ($actual, $expected): void {
        $t->same($expected, $actual());
    };
    foreach ($expected as $index => $value) {
        $tests['real upstream corpus window functions dynamic ' . $name . ' row ' . ($index + 1)] = static function (TestRunner $t) use ($actual, $index, $value): void {
            $rows = $actual();
            $t->same($value, $rows[$index]);
        };
    }
}

$tests['real upstream corpus window functions dynamic window2 empty frame total returns zero'] = static function (TestRunner $t) use ($values, $order): void {
    $t->same([0.0, 0.0, 0.0, 0.0, 0.0, 0.0], SQLiteWindowFunction::aggregateFrameBetweenValues('total', $values, $order, 'ROWS', '1 PRECEDING', '2 PRECEDING'));
};

$tests['real upstream corpus window functions dynamic window2 empty frame avg returns null'] = static function (TestRunner $t) use ($values, $order): void {
    $t->same([null, null, null, null, null, null], SQLiteWindowFunction::aggregateFrameBetweenValues('avg', $values, $order, 'ROWS', '1 PRECEDING', '2 PRECEDING'));
};

$tests['real upstream corpus window functions dynamic window3 excludes group after between boundaries'] = static function (TestRunner $t) use ($rangeValues, $rangeKeys): void {
    $t->same([15, 15, 45, 30, 30, null], SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $rangeValues, $rangeKeys, 'GROUPS', 'CURRENT ROW', '1 FOLLOWING', 'GROUP'));
};

$tests['real upstream corpus window functions dynamic rejects unsupported boundary'] = static function (TestRunner $t) use ($values, $order): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $order, 'ROWS', 'bad boundary', 'CURRENT ROW'));
};

$tests['real upstream corpus window functions dynamic rejects fractional rows boundary'] = static function (TestRunner $t) use ($values, $order): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $values, $order, 'ROWS', '1.5 PRECEDING', 'CURRENT ROW'));
};

$tests['real upstream corpus window functions dynamic rejects text range order key'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1], ['text'], 'RANGE', 'CURRENT ROW', '1 FOLLOWING'));
};

return $tests;
