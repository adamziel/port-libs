<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$medianOracle = static function (array $values): int|float|null {
    $numbers = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
    if ($numbers === []) {
        return null;
    }

    sort($numbers, SORT_REGULAR);
    $middle = intdiv(count($numbers), 2);
    if ((count($numbers) % 2) === 1) {
        return $numbers[$middle];
    }

    $sum = $numbers[$middle - 1] + $numbers[$middle];

    return fmod((float) $sum, 2.0) === 0.0 ? (int) ($sum / 2) : $sum / 2.0;
};

$window5Rows = [
    ['a' => 4, 'b' => 'a'],
    ['a' => 6, 'b' => 'b'],
    ['a' => 1, 'b' => 'c'],
    ['a' => 5, 'b' => 'd'],
    ['a' => 2, 'b' => 'e'],
    ['a' => 3, 'b' => 'f'],
];
$window5Values = array_column($window5Rows, 'a');
$window5Order = array_column($window5Rows, 'b');
$window5Sorted = SQLiteWindowFunction::sortedFrameTextValues(
    $window5Values,
    $window5Order,
    'ROWS',
    'UNBOUNDED PRECEDING',
    'CURRENT ROW',
);
$window5Medians = SQLiteWindowFunction::medianFrameBetweenValues(
    $window5Values,
    $window5Order,
    'ROWS',
    'UNBOUNDED PRECEDING',
    'CURRENT ROW',
);
$window5ExpectedSorted = ['4', '4 6', '1 4 6', '1 4 5 6', '1 2 4 5 6', '1 2 3 4 5 6'];
$window5ExpectedMedians = [4, 5, 4, 4.5, 4, 3.5];
foreach ($window5Rows as $index => $row) {
    $tests['real upstream window5.test 1.1 win sorted context row ' . $row['b']] = static function (TestRunner $t) use ($window5Sorted, $window5ExpectedSorted, $index): void {
        $t->same($window5ExpectedSorted[$index], $window5Sorted[$index]);
    };
    $tests['real upstream window5.test 1.1 median context row ' . $row['b']] = static function (TestRunner $t) use ($window5Medians, $window5ExpectedMedians, $index): void {
        $t->same($window5ExpectedMedians[$index], $window5Medians[$index]);
    };
}

$runningSums = SQLiteWindowFunction::aggregateFrameBetweenValues(
    'sum',
    $window5Values,
    range(1, count($window5Values)),
    'ROWS',
    'UNBOUNDED PRECEDING',
    'CURRENT ROW',
);
$neighborSums = SQLiteWindowFunction::aggregateFrameBetweenValues(
    'sum',
    $window5Values,
    range(1, count($window5Values)),
    'ROWS',
    '1 PRECEDING',
    '1 FOLLOWING',
);
foreach ($window5Rows as $index => $row) {
    $tests['real upstream window5.test 2.0 sumint running row ' . $row['b']] = static function (TestRunner $t) use ($runningSums, $index): void {
        $t->same([4, 10, 11, 16, 18, 21][$index], $runningSums[$index]);
    };
    $tests['real upstream window5.test 2.1 sumint sliding row ' . $row['b']] = static function (TestRunner $t) use ($neighborSums, $index): void {
        $t->same([10, 11, 12, 8, 10, 5][$index], $neighborSums[$index]);
    };
}

$dynamicDatasets = [];
foreach (range(0, 23) as $seed) {
    $rows = [];
    foreach (range(1, 8) as $row) {
        $rows[] = [
            'value' => (($seed + 3) * ($row * 5 + 1)) % 17 - 3,
            'order' => sprintf('%02d-%02d', $seed, $row),
            'include' => (($seed + $row) % 3) !== 0,
        ];
    }
    $dynamicDatasets['seed-' . $seed] = $rows;
}

foreach ($dynamicDatasets as $name => $rows) {
    $values = array_column($rows, 'value');
    $orders = array_column($rows, 'order');
    $filters = array_column($rows, 'include');
    $sortedActual = SQLiteWindowFunction::sortedFrameTextValues($values, $orders, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $medianActual = SQLiteWindowFunction::medianFrameBetweenValues($values, $orders, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
    $filteredMedianActual = SQLiteWindowFunction::medianFrameBetweenValues($values, $orders, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $filters);
    $slidingMedianActual = SQLiteWindowFunction::medianFrameBetweenValues($values, $orders, 'ROWS', '2 PRECEDING', '1 FOLLOWING');
    $groupedTextActual = SQLiteWindowFunction::sortedFrameTextValues($values, array_map(static fn (int $index): int => intdiv($index, 2), array_keys($values)), 'GROUPS', '1 PRECEDING', 'CURRENT ROW');

    foreach ($rows as $index => $row) {
        $prefix = array_slice($values, 0, $index + 1);
        $sliding = array_slice($values, max(0, $index - 2), min(count($values) - 1, $index + 1) - max(0, $index - 2) + 1);
        $filteredPrefix = [];
        foreach (range(0, $index) as $filterIndex) {
            if ($filters[$filterIndex]) {
                $filteredPrefix[] = $values[$filterIndex];
            }
        }
        $groupStart = max(0, intdiv($index, 2) - 1) * 2;
        $groupEnd = min(count($values) - 1, intdiv($index, 2) * 2 + 1);
        $groupFrame = array_slice($values, $groupStart, $groupEnd - $groupStart + 1);
        sort($prefix, SORT_REGULAR);
        sort($groupFrame, SORT_REGULAR);

        $tests["real upstream window5.test dynamic $name sorted prefix row $index"] = static function (TestRunner $t) use ($sortedActual, $prefix, $index): void {
            $t->same(implode(' ', array_map(static fn (mixed $value): string => (string) $value, $prefix)), $sortedActual[$index]);
        };
        $tests["real upstream window5.test dynamic $name median prefix row $index"] = static function (TestRunner $t) use ($medianActual, $medianOracle, $values, $index): void {
            $t->same($medianOracle(array_slice($values, 0, $index + 1)), $medianActual[$index]);
        };
        $tests["real upstream window5.test dynamic $name filtered median prefix row $index"] = static function (TestRunner $t) use ($filteredMedianActual, $medianOracle, $filteredPrefix, $index): void {
            $t->same($medianOracle($filteredPrefix), $filteredMedianActual[$index]);
        };
        $tests["real upstream window5.test dynamic $name sliding median row $index"] = static function (TestRunner $t) use ($slidingMedianActual, $medianOracle, $sliding, $index): void {
            $t->same($medianOracle($sliding), $slidingMedianActual[$index]);
        };
        $tests["real upstream window5.test dynamic $name groups sorted text row $index"] = static function (TestRunner $t) use ($groupedTextActual, $groupFrame, $index): void {
            $t->same(implode(' ', array_map(static fn (mixed $value): string => (string) $value, $groupFrame)), $groupedTextActual[$index]);
        };
    }
}

$errorCases = [
    'windowerr.test 1.1 rows negative preceding' => static fn (): array => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], 'ROWS', -1, 1),
    'windowerr.test 1.2 rows negative following' => static fn (): array => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], 'ROWS', 1, -1),
    'windowerr.test 1.3 range negative preceding' => static fn (): array => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], 'RANGE', -1, 1),
    'windowerr.test 1.4 range negative following' => static fn (): array => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], 'RANGE', 1, -1),
    'windowerr.test 1.5 groups negative preceding' => static fn (): array => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], 'GROUPS', -1, 1),
    'windowerr.test 1.6 groups negative following' => static fn (): array => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], 'GROUPS', 1, -1),
    'windowerr.test 3.0 invalid rows boundary text' => static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3], [1, 2, 3], 'ROWS', 'hello PRECEDING', '10 FOLLOWING'),
    'windowerr.test 3.2 invalid blob-like boundary text' => static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3], [1, 2, 3], 'ROWS', '10 PRECEDING', "x'ABCD' FOLLOWING"),
    'windowerr.test unsupported frame unit' => static fn (): array => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], 'BAD', 1, 1),
    'windowerr.test unsupported exclude mode' => static fn (): array => SQLiteWindowFunction::aggregateFrameValues('sum', [1, 2, 3], [1, 2, 3], 'ROWS', 1, 1, 'BAD'),
    'windowerr.test nth zero rejected' => static fn (): array => SQLiteWindowFunction::valueFrameValues('nth_value', [1, 2, 3], [1, 2, 3], 'ROWS', 1, 1, 'NO OTHERS', 0),
    'windowerr.test mismatched filter count rejected' => static fn (): array => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', [1, 2, 3], [1, 2, 3], 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', [true]),
];
foreach ($errorCases as $name => $callback) {
    $tests['real upstream ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
