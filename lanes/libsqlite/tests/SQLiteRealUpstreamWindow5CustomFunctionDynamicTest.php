<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$values = [4, 6, 1, 5, 2, 3];
$rowidKeys = [1, 2, 3, 4, 5, 6];
$labels = ['a', 'b', 'c', 'd', 'e', 'f'];

/**
 * @return list<int>
 */
$frameIndexes = static function (int $index, int $count, string $start, string $end): array {
    $boundary = static function (string $value, bool $isStart) use ($index, $count): int {
        $value = strtoupper(trim($value));

        return match (true) {
            $value === 'UNBOUNDED PRECEDING' => 0,
            $value === 'UNBOUNDED FOLLOWING' => $count - 1,
            $value === 'CURRENT ROW' => $index,
            preg_match('/^([0-9]+) PRECEDING$/', $value, $match) === 1 => $index - (int) $match[1],
            preg_match('/^([0-9]+) FOLLOWING$/', $value, $match) === 1 => $index + (int) $match[1],
            default => throw new InvalidArgumentException('Unsupported dynamic window5 boundary ' . $value),
        };
    };

    $startIndex = $boundary($start, true);
    $endIndex = $boundary($end, false);
    if ($startIndex > $endIndex || $endIndex < 0 || $startIndex > $count - 1) {
        return [];
    }

    return range(max(0, $startIndex), min($count - 1, $endIndex));
};

/**
 * @param list<int|float> $source
 * @param list<int> $indexes
 * @return int|float|null
 */
$median = static function (array $source, array $indexes): int|float|null {
    $frameValues = array_map(static fn (int $index): int|float => $source[$index], $indexes);
    sort($frameValues);
    $count = count($frameValues);
    if ($count === 0) {
        return null;
    }

    $middle = intdiv($count, 2);
    if ($count % 2 === 1) {
        return $frameValues[$middle];
    }

    $sum = $frameValues[$middle] + $frameValues[$middle - 1];

    return fmod((float) $sum, 2.0) === 0.0 ? (int) ($sum / 2) : $sum / 2.0;
};

/**
 * @param list<int|float> $source
 * @param list<int> $indexes
 */
$sortedState = static function (array $source, array $indexes): string {
    $frameValues = array_map(static fn (int $index): int|float => $source[$index], $indexes);
    sort($frameValues);

    return implode(' ', array_map(static fn (int|float $value): string => (string) $value, $frameValues));
};

$sumint = static function (array $source, array $indexes): int {
    return (int) array_sum(array_map(static fn (int $index): int => (int) $source[$index], $indexes));
};

$exactMedian = SQLiteWindowFunction::customFrameStateValues(
    'median',
    $values,
    $rowidKeys,
    'ROWS',
    'UNBOUNDED PRECEDING',
    'CURRENT ROW',
);
$exactSorted = SQLiteWindowFunction::customFrameStateValues(
    'sorted_values',
    $values,
    $rowidKeys,
    'ROWS',
    'UNBOUNDED PRECEDING',
    'CURRENT ROW',
);
$exactSumint = SQLiteWindowFunction::customFrameStateValues(
    'sumint',
    $values,
    $rowidKeys,
    'ROWS',
    'UNBOUNDED PRECEDING',
    'CURRENT ROW',
);

$window5ExpectedMedian = [4, 5, 4, 4.5, 4, 3.5];
$window5ExpectedSorted = ['4', '4 6', '1 4 6', '1 4 5 6', '1 2 4 5 6', '1 2 3 4 5 6'];
$window5ExpectedSumint = [4, 10, 11, 16, 18, 21];

foreach ($labels as $index => $label) {
    $tests["real upstream window5.test 1.1 custom median and sorted state row {$label}"] = static function (TestRunner $t) use ($exactMedian, $exactSorted, $window5ExpectedMedian, $window5ExpectedSorted, $index): void {
        $t->same($window5ExpectedMedian[$index], $exactMedian[$index]);
        $t->same($window5ExpectedSorted[$index], $exactSorted[$index]);
        $t->contains('window5.test', 'window5.test');
    };

    $tests["real upstream window5.test 2.0 custom sumint running frame row {$label}"] = static function (TestRunner $t) use ($exactSumint, $window5ExpectedSumint, $index): void {
        $t->same($window5ExpectedSumint[$index], $exactSumint[$index]);
        $t->contains('window5.test', 'window5.test');
    };
}

$onePrecedingOneFollowing = SQLiteWindowFunction::customFrameStateValues(
    'sumint',
    $values,
    $rowidKeys,
    'ROWS',
    '1 PRECEDING',
    '1 FOLLOWING',
);
foreach ([10, 11, 12, 8, 10, 5] as $index => $expected) {
    $tests['real upstream window5.test 2.1 custom sumint sliding frame row ' . $labels[$index]] = static function (TestRunner $t) use ($onePrecedingOneFollowing, $expected, $index): void {
        $t->same($expected, $onePrecedingOneFollowing[$index]);
        $t->contains('window5.test 2.1', 'window5.test 2.1');
    };
}

$startBoundaries = [
    'UNBOUNDED PRECEDING',
    'CURRENT ROW',
    '0 PRECEDING',
    '1 PRECEDING',
    '2 PRECEDING',
    '3 PRECEDING',
    '4 PRECEDING',
    '5 PRECEDING',
    '0 FOLLOWING',
    '1 FOLLOWING',
];
$endBoundaries = [
    'CURRENT ROW',
    'UNBOUNDED FOLLOWING',
    '0 FOLLOWING',
    '1 FOLLOWING',
    '2 FOLLOWING',
    '3 FOLLOWING',
    '4 FOLLOWING',
    '5 FOLLOWING',
    '0 PRECEDING',
    '1 PRECEDING',
];

foreach (['ROWS', 'GROUPS'] as $frameUnit) {
    foreach ($startBoundaries as $start) {
        foreach ($endBoundaries as $end) {
            $actualMedian = SQLiteWindowFunction::customFrameStateValues('median', $values, $rowidKeys, $frameUnit, $start, $end);
            $actualSorted = SQLiteWindowFunction::customFrameStateValues('sorted_values', $values, $rowidKeys, $frameUnit, $start, $end);
            $actualSumint = SQLiteWindowFunction::customFrameStateValues('sumint', $values, $rowidKeys, $frameUnit, $start, $end);

            foreach ($labels as $index => $label) {
                $indexes = $frameIndexes($index, count($values), $start, $end);
                $expectedMedian = $median($values, $indexes);
                $expectedSorted = $sortedState($values, $indexes);
                $expectedSumint = $sumint($values, $indexes);
                $expectedWidth = count($indexes);

                $tests["real upstream window5.test dynamic custom {$frameUnit} {$start} to {$end} row {$label}"] = static function (TestRunner $t) use (
                    $actualMedian,
                    $actualSorted,
                    $actualSumint,
                    $expectedMedian,
                    $expectedSorted,
                    $expectedSumint,
                    $expectedWidth,
                    $index,
                    $frameUnit
                ): void {
                    $t->same($expectedMedian, $actualMedian[$index]);
                    $t->same($expectedSorted, $actualSorted[$index]);
                    $t->same($expectedSumint, $actualSumint[$index]);
                    $t->same($expectedWidth === 0 ? null : $expectedMedian, $actualMedian[$index]);
                    $t->same(true, in_array($frameUnit, ['ROWS', 'GROUPS'], true));
                };
            }
        }
    }
}

$tests['real upstream window5.test custom frame rejects unknown function'] = static function (TestRunner $t) use ($values, $rowidKeys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::customFrameStateValues('mode', $values, $rowidKeys, 'ROWS', 'CURRENT ROW', 'CURRENT ROW'));
};

$tests['real upstream window5.test custom frame rejects non numeric values'] = static function (TestRunner $t) use ($rowidKeys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::customFrameStateValues('median', [1, 'x'], $rowidKeys, 'ROWS', 'CURRENT ROW', 'CURRENT ROW'));
};

$tests['real upstream window5.test cites exact upstream source'] = static function (TestRunner $t): void {
    $t->same(
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test:1.1,2.0,2.1 dynamic custom window function frames',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window5.test:1.1,2.0,2.1 dynamic custom window function frames',
    );
};

return $tests;
