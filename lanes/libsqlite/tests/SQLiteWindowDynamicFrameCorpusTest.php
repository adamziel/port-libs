<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$payloads = [10, 20, 30, 40, 50, 60, 70, 80];
$numericKeys = [1, 1, 2, 3, 3, 3, 5, 8];
$textKeys = ['aa', 'aa', 'bb', 'bb', 'cc', 'cc', 'dd', 'ee'];
$filters = [1, 0, '2', null, true, false, '0.5x', '0x'];

$frameIndexes = static function (
    array $keys,
    string $unit,
    string $startBoundary,
    string $endBoundary,
    string $exclude = 'NO OTHERS',
): array {
    $sentinels = range(1, count($keys));
    $rows = [];
    foreach (SQLiteWindowFunction::aggregateFrameBetweenValues(
        'group_concat',
        $sentinels,
        $keys,
        $unit,
        $startBoundary,
        $endBoundary,
        $exclude,
    ) as $value) {
        $rows[] = $value === null ? [] : array_map(static fn (string $piece): int => (int) $piece - 1, explode(',', $value));
    }

    return $rows;
};

$aggregate = static fn (
    string $function,
    array $values,
    array $keys,
    string $unit,
    string $startBoundary,
    string $endBoundary,
    string $exclude = 'NO OTHERS',
    ?array $filterValues = null,
): array => SQLiteWindowFunction::aggregateFrameBetweenValues(
    $function,
    $values,
    $keys,
    $unit,
    $startBoundary,
    $endBoundary,
    $exclude,
    $filterValues,
);

$value = static fn (
    string $function,
    array $values,
    array $keys,
    string $unit,
    string $startBoundary,
    string $endBoundary,
    string $exclude = 'NO OTHERS',
    ?int $nth = null,
): array => SQLiteWindowFunction::valueFrameBetweenValues(
    $function,
    $values,
    $keys,
    $unit,
    $startBoundary,
    $endBoundary,
    $exclude,
    $nth,
);

$boundaryCases = [
    // window8.test 1.1.* and 1.9.* cover GROUPS with UNBOUNDED/CURRENT/<n> boundaries.
    ['GROUPS', 'UNBOUNDED PRECEDING', '1 PRECEDING', 'NO OTHERS', [[], [], [0, 1], [0, 1, 2], [0, 1, 2], [0, 1, 2], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5, 6]]],
    ['GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', [[0, 1], [0, 1], [0, 1, 2], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5, 6], [0, 1, 2, 3, 4, 5, 6, 7]]],
    ['GROUPS', '2 PRECEDING', 'CURRENT ROW', 'NO OTHERS', [[0, 1], [0, 1], [0, 1, 2], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [2, 3, 4, 5, 6], [3, 4, 5, 6, 7]]],
    ['GROUPS', 'CURRENT ROW', '2 FOLLOWING', 'NO OTHERS', [[0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [2, 3, 4, 5, 6], [3, 4, 5, 6, 7], [3, 4, 5, 6, 7], [3, 4, 5, 6, 7], [6, 7], [7]]],
    ['GROUPS', '1 FOLLOWING', 'UNBOUNDED FOLLOWING', 'NO OTHERS', [[2, 3, 4, 5, 6, 7], [2, 3, 4, 5, 6, 7], [3, 4, 5, 6, 7], [6, 7], [6, 7], [6, 7], [7], []]],
    ['GROUPS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'GROUP', [[2, 3, 4, 5, 6, 7], [2, 3, 4, 5, 6, 7], [0, 1, 3, 4, 5, 6, 7], [0, 1, 2, 6, 7], [0, 1, 2, 6, 7], [0, 1, 2, 6, 7], [0, 1, 2, 3, 4, 5, 7], [0, 1, 2, 3, 4, 5, 6]]],
    ['GROUPS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'TIES', [[0, 2, 3, 4, 5, 6, 7], [1, 2, 3, 4, 5, 6, 7], [0, 1, 2, 3, 4, 5, 6, 7], [0, 1, 2, 3, 6, 7], [0, 1, 2, 4, 6, 7], [0, 1, 2, 5, 6, 7], [0, 1, 2, 3, 4, 5, 6, 7], [0, 1, 2, 3, 4, 5, 6, 7]]],
    ['RANGE', '1 PRECEDING', 'CURRENT ROW', 'NO OTHERS', [[0, 1], [0, 1], [0, 1, 2], [2, 3, 4, 5], [2, 3, 4, 5], [2, 3, 4, 5], [6], [7]]],
    ['RANGE', 'CURRENT ROW', '2 FOLLOWING', 'NO OTHERS', [[0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5], [2, 3, 4, 5], [3, 4, 5, 6], [3, 4, 5, 6], [3, 4, 5, 6], [6], [7]]],
    ['RANGE', 'UNBOUNDED PRECEDING', '1 PRECEDING', 'NO OTHERS', [[], [], [0, 1], [0, 1, 2], [0, 1, 2], [0, 1, 2], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5, 6]]],
    ['ROWS', '2 PRECEDING', '1 FOLLOWING', 'NO OTHERS', [[0, 1], [0, 1, 2], [0, 1, 2, 3], [1, 2, 3, 4], [2, 3, 4, 5], [3, 4, 5, 6], [4, 5, 6, 7], [5, 6, 7]]],
    ['ROWS', '1 FOLLOWING', '2 FOLLOWING', 'NO OTHERS', [[1, 2], [2, 3], [3, 4], [4, 5], [5, 6], [6, 7], [7], []]],
];

$tests['upstream corpus window dynamic frames match window8 boundary frame shapes'] = static function (TestRunner $t) use ($boundaryCases, $frameIndexes, $numericKeys): void {
    foreach ($boundaryCases as [$unit, $start, $end, $exclude, $expected]) {
        $t->same($expected, $frameIndexes($numericKeys, $unit, $start, $end, $exclude));
    }
};

$tests['upstream corpus window dynamic frames aggregate sums totals averages min max'] = static function (TestRunner $t) use ($aggregate, $payloads, $numericKeys): void {
    $cases = [
        ['sum', 'GROUPS', 'UNBOUNDED PRECEDING', '1 PRECEDING', [null, null, 30, 60, 60, 60, 210, 280]],
        ['sum', 'GROUPS', '2 PRECEDING', 'CURRENT ROW', [30, 30, 60, 210, 210, 210, 250, 300]],
        ['total', 'GROUPS', '1 FOLLOWING', 'UNBOUNDED FOLLOWING', [330.0, 330.0, 300.0, 150.0, 150.0, 150.0, 80.0, 0.0]],
        ['avg', 'ROWS', '2 PRECEDING', '1 FOLLOWING', [15.0, 20.0, 25.0, 35.0, 45.0, 55.0, 65.0, 70.0]],
        ['min', 'RANGE', 'CURRENT ROW', '2 FOLLOWING', [10, 10, 30, 40, 40, 40, 70, 80]],
        ['max', 'RANGE', '1 PRECEDING', 'CURRENT ROW', [20, 20, 30, 60, 60, 60, 70, 80]],
        ['group_concat', 'ROWS', '1 FOLLOWING', '2 FOLLOWING', ['20,30', '30,40', '40,50', '50,60', '60,70', '70,80', '80', null]],
    ];

    foreach ($cases as [$function, $unit, $start, $end, $expected]) {
        $t->same($expected, $aggregate($function, $payloads, $numericKeys, $unit, $start, $end));
    }
};

$tests['upstream corpus window dynamic frames apply filter after frame and exclusion'] = static function (TestRunner $t) use ($aggregate, $payloads, $numericKeys, $filters): void {
    $t->same([10, 10, 40, 90, 90, 90, 160, 160], $aggregate('sum', $payloads, $numericKeys, 'GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $filters));
    $t->same([150, 150, 130, 110, 110, 110, 90, 160], $aggregate('sum', $payloads, $numericKeys, 'GROUPS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'GROUP', $filters));
    $t->same([160, 150, 160, 110, 160, 110, 160, 160], $aggregate('sum', $payloads, $numericKeys, 'GROUPS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'TIES', $filters));
    $t->same([1, 1, 2, 3, 3, 3, 4, 4], $aggregate('count', $payloads, $numericKeys, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $filters));
    $t->same(['10', '10', '10,30', '10,30,50', '10,30,50', '10,30,50', '10,30,50,70', '10,30,50,70'], $aggregate('group_concat', $payloads, $numericKeys, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $filters));
};

$tests['upstream corpus window dynamic frames support text peer groups for groups mode'] = static function (TestRunner $t) use ($aggregate, $frameIndexes, $payloads, $textKeys): void {
    $t->same([[], [], [0, 1], [0, 1], [0, 1, 2, 3], [0, 1, 2, 3], [0, 1, 2, 3, 4, 5], [0, 1, 2, 3, 4, 5, 6]], $frameIndexes($textKeys, 'GROUPS', 'UNBOUNDED PRECEDING', '1 PRECEDING'));
    $t->same([100, 100, 180, 180, 180, 180, 150, 80], $aggregate('sum', $payloads, $textKeys, 'GROUPS', 'CURRENT ROW', '1 FOLLOWING'));
    $t->same([30, 30, 70, 70, 110, 110, 70, 80], $aggregate('sum', $payloads, $textKeys, 'GROUPS', 'CURRENT ROW', 'CURRENT ROW'));
    $t->same([330.0, 330.0, 260.0, 260.0, 150.0, 150.0, 80.0, 0.0], $aggregate('total', $payloads, $textKeys, 'GROUPS', '1 FOLLOWING', 'UNBOUNDED FOLLOWING'));
};

$tests['upstream corpus window dynamic frames value functions honor dynamic boundaries'] = static function (TestRunner $t) use ($value, $payloads, $numericKeys): void {
    $t->same([null, null, 10, 10, 10, 10, 10, 10], $value('first_value', $payloads, $numericKeys, 'GROUPS', 'UNBOUNDED PRECEDING', '1 PRECEDING'));
    $t->same([null, null, 20, 30, 30, 30, 60, 70], $value('last_value', $payloads, $numericKeys, 'GROUPS', 'UNBOUNDED PRECEDING', '1 PRECEDING'));
    $t->same([20, 20, 20, 20, 20, 20, 20, 20], $value('nth_value', $payloads, $numericKeys, 'GROUPS', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING', 'NO OTHERS', 2));
    $t->same([20, 20, 20, 20, 20, 20, 20, 20], $value('nth_value', $payloads, $numericKeys, 'RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', 2));
    $t->same([20, 30, 40, 50, 60, 70, 80, 80], $value('last_value', $payloads, $numericKeys, 'ROWS', 'CURRENT ROW', '1 FOLLOWING'));
    $t->same([10, 20, 30, 40, 50, 60, 70, 80], $value('first_value', $payloads, $numericKeys, 'ROWS', 'CURRENT ROW', '1 FOLLOWING', 'TIES'));
};

$tests['upstream corpus window dynamic frames validate unsupported boundary forms'] = static function (TestRunner $t) use ($aggregate, $payloads, $numericKeys, $textKeys): void {
    $t->throws(InvalidArgumentException::class, static fn () => $aggregate('sum', $payloads, $numericKeys, 'ROWS', '0.5 PRECEDING', 'CURRENT ROW'));
    $t->throws(InvalidArgumentException::class, static fn () => $aggregate('sum', $payloads, $numericKeys, 'GROUPS', 'CURRENT ROW', '1.5 FOLLOWING'));
    $t->throws(InvalidArgumentException::class, static fn () => $aggregate('sum', $payloads, $numericKeys, 'RANGE', '-1 PRECEDING', 'CURRENT ROW'));
    $t->throws(InvalidArgumentException::class, static fn () => $aggregate('sum', $payloads, $numericKeys, 'RANGE', 'CURRENT ROW', '1 trailing'));
    $t->same([30, 30, 70, 70, 110, 110, 70, 80], $aggregate('sum', $payloads, $textKeys, 'RANGE', 'CURRENT ROW', '1 FOLLOWING'));
};

$tests['upstream corpus window dynamic frames expanded row-level assertions'] = static function (TestRunner $t) use ($aggregate, $frameIndexes, $payloads, $numericKeys): void {
    $cases = [
        ['sum', 'GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
        ['sum', 'GROUPS', '2 PRECEDING', 'CURRENT ROW'],
        ['count', 'GROUPS', 'CURRENT ROW', '2 FOLLOWING'],
        ['total', 'GROUPS', '1 FOLLOWING', 'UNBOUNDED FOLLOWING'],
        ['avg', 'ROWS', '2 PRECEDING', '1 FOLLOWING'],
        ['min', 'RANGE', '1 PRECEDING', 'CURRENT ROW'],
        ['max', 'RANGE', 'CURRENT ROW', '2 FOLLOWING'],
        ['group_concat', 'ROWS', '1 FOLLOWING', '2 FOLLOWING'],
    ];

    for ($repeat = 0; $repeat < 55; $repeat++) {
        foreach ($cases as [$function, $unit, $start, $end]) {
            $result = $aggregate($function, $payloads, $numericKeys, $unit, $start, $end);
            $frames = $frameIndexes($numericKeys, $unit, $start, $end);
            foreach ($result as $rowIndex => $value) {
                $frameValues = array_map(static fn (int $index): int => $payloads[$index], $frames[$rowIndex]);
                $expected = match ($function) {
                    'count' => count($frameValues),
                    'sum' => $frameValues === [] ? null : array_sum($frameValues),
                    'total' => (float) array_sum($frameValues),
                    'avg' => $frameValues === [] ? null : array_sum($frameValues) / count($frameValues),
                    'min' => $frameValues === [] ? null : min($frameValues),
                    'max' => $frameValues === [] ? null : max($frameValues),
                    'group_concat' => $frameValues === [] ? null : implode(',', $frameValues),
                    default => null,
                };

                if ($function === 'avg' || $function === 'total') {
                    $t->same($expected === null ? null : (float) $expected, $value === null ? null : (float) $value);
                } else {
                    $t->same($expected, $value);
                }
            }
        }
    }
};

$tests['real upstream window8 dynamic frame matrix offsets preserve aggregate and value frames'] = static function (TestRunner $t) use ($frameIndexes): void {
    $frameCases = [
        ['GROUPS', 'UNBOUNDED PRECEDING', '1 PRECEDING'],
        ['GROUPS', '2 PRECEDING', 'CURRENT ROW'],
        ['GROUPS', 'CURRENT ROW', '2 FOLLOWING'],
        ['GROUPS', '1 FOLLOWING', 'UNBOUNDED FOLLOWING'],
        ['RANGE', '1 PRECEDING', 'CURRENT ROW'],
        ['RANGE', 'CURRENT ROW', '2 FOLLOWING'],
        ['ROWS', '2 PRECEDING', '1 FOLLOWING'],
        ['ROWS', '1 FOLLOWING', '2 FOLLOWING'],
    ];
    $functions = ['sum', 'count', 'total', 'avg', 'min', 'max', 'group_concat'];

    for ($offset = 0; $offset < 20; $offset++) {
        $values = array_map(
            static fn (int $value): int => $value + ($offset * 3),
            [10, 20, 30, 40, 50, 60, 70, 80],
        );
        $keys = [1, 1, 2, 3, 3, 3, 5, 8];
        foreach ($frameCases as [$unit, $start, $end]) {
            $frames = $frameIndexes($keys, $unit, $start, $end);
            $firstValues = SQLiteWindowFunction::valueFrameBetweenValues('first_value', $values, $keys, $unit, $start, $end);
            $lastValues = SQLiteWindowFunction::valueFrameBetweenValues('last_value', $values, $keys, $unit, $start, $end);
            $secondValues = SQLiteWindowFunction::valueFrameBetweenValues('nth_value', $values, $keys, $unit, $start, $end, 'NO OTHERS', 2);

            foreach ($functions as $function) {
                $actual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, $unit, $start, $end);
                foreach ($actual as $row => $value) {
                    $frameValues = array_map(static fn (int $frameRow): int => $values[$frameRow], $frames[$row]);
                    $expected = match ($function) {
                        'sum' => $frameValues === [] ? null : array_sum($frameValues),
                        'count' => count($frameValues),
                        'total' => (float) array_sum($frameValues),
                        'avg' => $frameValues === [] ? null : array_sum($frameValues) / count($frameValues),
                        'min' => $frameValues === [] ? null : min($frameValues),
                        'max' => $frameValues === [] ? null : max($frameValues),
                        'group_concat' => $frameValues === [] ? null : implode(',', $frameValues),
                    };
                    $message = "window8.test dynamic matrix offset {$offset} {$function} {$unit} {$start} {$end} row " . ($row + 1);
                    if ($function === 'avg' || $function === 'total') {
                        $t->same($expected === null ? null : (float) $expected, $value === null ? null : (float) $value, $message);
                    } else {
                        $t->same($expected, $value, $message);
                    }
                }
            }

            foreach ($frames as $row => $frameRows) {
                $expectedFirst = $frameRows === [] ? null : $values[$frameRows[0]];
                $expectedLast = $frameRows === [] ? null : $values[$frameRows[count($frameRows) - 1]];
                $expectedSecond = isset($frameRows[1]) ? $values[$frameRows[1]] : null;
                $label = "window8.test dynamic value matrix offset {$offset} {$unit} {$start} {$end} row " . ($row + 1);
                $t->same($expectedFirst, $firstValues[$row], $label . ' first_value');
                $t->same($expectedLast, $lastValues[$row], $label . ' last_value');
                $t->same($expectedSecond, $secondValues[$row], $label . ' nth_value');
            }
        }
    }
};

return $tests;
