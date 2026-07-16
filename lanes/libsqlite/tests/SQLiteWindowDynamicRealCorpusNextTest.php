<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$window5Rows = [
    ['a' => 4, 'b' => 'a'],
    ['a' => 6, 'b' => 'b'],
    ['a' => 1, 'b' => 'c'],
    ['a' => 5, 'b' => 'd'],
    ['a' => 2, 'b' => 'e'],
    ['a' => 3, 'b' => 'f'],
];

$orderedValues = array_column($window5Rows, 'a');
$orderedKeys = range(1, count($orderedValues));

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

$window5Cases = [
    'window5.test 1.1 unbounded custom win and median' => [
        'ROWS',
        'UNBOUNDED PRECEDING',
        'CURRENT ROW',
        [4, 5, 4, 4.5, 4, 3.5],
        ['4', '4 6', '1 4 6', '1 4 5 6', '1 2 4 5 6', '1 2 3 4 5 6'],
    ],
    'window5.test 2.1 custom sumint one preceding following' => [
        'ROWS',
        '1 PRECEDING',
        '1 FOLLOWING',
        [5, 11, 12, 8, 10, 5],
        ['4 6', '1 4 6', '1 5 6', '1 2 5', '2 3 5', '2 3'],
    ],
    'window5.test dynamic rows current following custom inverse' => [
        'ROWS',
        'CURRENT ROW',
        '2 FOLLOWING',
        [4, 5, 2, 3.5, 2.5, 3],
        ['1 4 6', '1 5 6', '1 2 5', '2 3 5', '2 3', '3'],
    ],
    'window5.test dynamic rows two preceding current custom inverse' => [
        'ROWS',
        '2 PRECEDING',
        'CURRENT ROW',
        [4, 5, 4, 5, 2, 3],
        ['4', '4 6', '1 4 6', '1 5 6', '1 2 5', '2 3 5'],
    ],
];

foreach ($window5Cases as $name => [$unit, $start, $end, $_expectedMedian, $expectedSorted]) {
    $tests['real upstream ' . $name . ' median custom function'] = static function (TestRunner $t) use ($orderedValues, $orderedKeys, $unit, $start, $end, $medianOracle, $name): void {
        $actual = SQLiteWindowFunction::customFrameStateValues('median', $orderedValues, $orderedKeys, $unit, $start, $end);
        $frames = SQLiteWindowFunction::aggregateFrameBetweenRows($orderedValues, $orderedKeys, $unit, $start, $end);
        foreach ($frames as $row => $frame) {
            $frameValues = array_map(static fn (int $index): int => $orderedValues[$index], $frame['frame']);
            $t->same($medianOracle($frameValues), $actual[$row], $name . ' median row ' . ($row + 1));
        }
    };

    $tests['real upstream ' . $name . ' sorted custom value function'] = static function (TestRunner $t) use ($orderedValues, $orderedKeys, $unit, $start, $end, $expectedSorted, $name): void {
        $actual = SQLiteWindowFunction::customFrameStateValues('sorted_values', $orderedValues, $orderedKeys, $unit, $start, $end);
        foreach ($expectedSorted as $row => $expected) {
            $t->same($expected, $actual[$row], $name . ' sorted row ' . ($row + 1));
        }
    };

    $tests['real upstream ' . $name . ' sumint custom aggregate'] = static function (TestRunner $t) use ($orderedValues, $orderedKeys, $unit, $start, $end, $expectedSorted, $name): void {
        $actual = SQLiteWindowFunction::customFrameStateValues('sumint', $orderedValues, $orderedKeys, $unit, $start, $end);
        foreach ($expectedSorted as $row => $sorted) {
            $expected = array_sum(array_map('intval', explode(' ', $sorted)));
            $t->same($expected, $actual[$row], $name . ' sumint row ' . ($row + 1));
        }
    };
}

$sampleCounters = [1, 1, 2, 2, 3];
$sampleValues = [10.0, 20.0, 1.0, 3.0, 100.0];
$sampleIds = [1, 2, 3, 4, 5];
$tests['real upstream window6 8.1 rank partitions by counter order value desc'] = static function (TestRunner $t) use ($sampleCounters, $sampleValues): void {
    $expected = [2, 1, 2, 1, 1];
    $actual = [];
    $byCounter = [];
    foreach ($sampleCounters as $row => $counter) {
        $byCounter[$counter][] = $row;
    }
    foreach ($byCounter as $rows) {
        usort($rows, static fn (int $left, int $right): int => $sampleValues[$right] <=> $sampleValues[$left]);
        $ranks = SQLiteWindowFunction::rank(array_map(static fn (int $row): float => -$sampleValues[$row], $rows));
        foreach ($rows as $offset => $row) {
            $actual[$row] = $ranks[$offset];
        }
    }
    ksort($actual);
    foreach ($expected as $row => $rank) {
        $t->same($rank, $actual[$row], 'window6.test 8.1 rank row ' . ($row + 1));
    }
};

$tests['real upstream window6 8.2 rows two preceding running sum'] = static function (TestRunner $t) use ($sampleValues, $sampleIds): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $sampleValues, $sampleIds, 'ROWS', '2 PRECEDING', 'CURRENT ROW');
    foreach ([10.0, 30.0, 31.0, 24.0, 104.0] as $row => $expected) {
        $t->same($expected, $actual[$row], 'window6.test 8.2 row ' . ($row + 1));
    }
};

$tests['real upstream window6 9.0 recursive rows two preceding group concat'] = static function (TestRunner $t): void {
    $actual = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', [1, 2, 3, 4, 5], [1, 2, 3, 4, 5], 'ROWS', '2 PRECEDING', 'CURRENT ROW');
    foreach (['1', '1,2', '1,2,3', '2,3,4', '3,4,5'] as $row => $expected) {
        $t->same($expected, $actual[$row], 'window6.test 9.0 row ' . ($row + 1));
    }
};

$fruitRows = [
    ['name' => 'apple', 'color' => 'RED'],
    ['name' => 'APPLE', 'color' => 'yellow'],
    ['name' => 'pear', 'color' => 'YELLOW'],
    ['name' => 'PEAR', 'color' => 'green'],
];

$tests['real upstream window9 1.2 dense rank nocase name peers'] = static function (TestRunner $t) use ($fruitRows): void {
    $actual = SQLiteWindowFunction::denseRank(array_map(static fn (array $row): string => strtolower($row['name']), $fruitRows));
    foreach ([1, 1, 2, 2] as $row => $expected) {
        $t->same($expected, $actual[$row], 'window9.test 1.2 row ' . ($row + 1));
    }
};

$tests['real upstream window9 1.3 dense rank partitioned nocase colors'] = static function (TestRunner $t) use ($fruitRows): void {
    $expected = [1, 2, 2, 1];
    $actual = [];
    $byName = [];
    foreach ($fruitRows as $row => $fruit) {
        $byName[strtolower($fruit['name'])][] = $row;
    }
    foreach ($byName as $rows) {
        usort($rows, static fn (int $left, int $right): int => strcasecmp($fruitRows[$left]['color'], $fruitRows[$right]['color']));
        $ranks = SQLiteWindowFunction::denseRank(array_map(static fn (int $row): string => strtolower($fruitRows[$row]['color']), $rows));
        foreach ($rows as $offset => $row) {
            $actual[$row] = $ranks[$offset];
        }
    }
    ksort($actual);
    foreach ($expected as $row => $rank) {
        $t->same($rank, $actual[$row], 'window9.test 1.3 row ' . ($row + 1));
    }
};

$dynamicRowsForWindow5 = [];
for ($case = 0; $case < 1080; $case++) {
    $values = [];
    $keys = [];
    $count = 5 + ($case % 5);
    for ($row = 0; $row < $count; $row++) {
        $values[] = (($case + 3) * ($row + 2)) % 17;
        $keys[] = $row + 1;
    }

    $start = match ($case % 4) {
        0 => 'UNBOUNDED PRECEDING',
        1 => '1 PRECEDING',
        2 => '2 PRECEDING',
        default => 'CURRENT ROW',
    };
    $end = match (intdiv($case, 4) % 4) {
        0 => 'CURRENT ROW',
        1 => '1 FOLLOWING',
        2 => '2 FOLLOWING',
        default => 'UNBOUNDED FOLLOWING',
    };
    if ($start === 'CURRENT ROW' && str_ends_with($end, 'PRECEDING')) {
        $end = 'CURRENT ROW';
    }

    $dynamicRowsForWindow5[] = [$case, $values, $keys, $start, $end];
}

foreach ($dynamicRowsForWindow5 as [$case, $values, $keys, $start, $end]) {
    $tests['real upstream window5 dynamic custom inverse frame ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $values, $keys, $start, $end, $medianOracle): void {
        $frames = SQLiteWindowFunction::aggregateFrameBetweenRows($values, $keys, 'ROWS', $start, $end);
        $medians = SQLiteWindowFunction::customFrameStateValues('median', $values, $keys, 'ROWS', $start, $end);
        $sorted = SQLiteWindowFunction::customFrameStateValues('sorted_values', $values, $keys, 'ROWS', $start, $end);
        $sumint = SQLiteWindowFunction::customFrameStateValues('sumint', $values, $keys, 'ROWS', $start, $end);

        foreach ($frames as $row => $frame) {
            $frameValues = array_map(static fn (int $index): int => $values[$index], $frame['frame']);
            $expectedSorted = $frameValues;
            sort($expectedSorted, SORT_REGULAR);
            $expectedSortedText = implode(' ', array_map('strval', $expectedSorted));
            $t->same($medianOracle($frameValues), $medians[$row], "window5.test dynamic {$case} median row {$row}");
            $t->same($expectedSortedText === '' ? null : $expectedSortedText, $sorted[$row], "window5.test dynamic {$case} sorted row {$row}");
            $t->same(array_sum($frameValues), $sumint[$row], "window5.test dynamic {$case} sumint row {$row}");
        }
    };
}

$dynamicRowsForWindow9 = [];
for ($case = 0; $case < 160; $case++) {
    $names = [];
    $colors = [];
    $rows = 6 + ($case % 4);
    for ($row = 0; $row < $rows; $row++) {
        $baseName = ['alpha', 'beta', 'gamma'][($row + $case) % 3];
        $names[] = ($row % 2) === 0 ? strtoupper($baseName) : $baseName;
        $colors[] = ['red', 'GREEN', 'Blue', 'yellow'][($row * 2 + $case) % 4];
    }
    $dynamicRowsForWindow9[] = [$case, $names, $colors];
}

foreach ($dynamicRowsForWindow9 as [$case, $names, $colors]) {
    $tests['real upstream window9 dynamic nocase dense ranks ' . str_pad((string) $case, 3, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case, $names, $colors): void {
        $orderedNameRows = array_keys($names);
        usort($orderedNameRows, static fn (int $left, int $right): int => strcasecmp($names[$left], $names[$right]) ?: ($left <=> $right));
        $orderedNames = array_map(static fn (int $row): string => strtolower($names[$row]), $orderedNameRows);
        $nameRanks = SQLiteWindowFunction::denseRank($orderedNames);
        $expectedNameRanks = [];
        $lastName = null;
        $rank = 0;
        foreach ($orderedNames as $name) {
            if ($lastName === null || $name !== $lastName) {
                $rank++;
                $lastName = $name;
            }
            $expectedNameRanks[] = $rank;
        }

        foreach ($expectedNameRanks as $row => $expected) {
            $t->same($expected, $nameRanks[$row], "window9.test dynamic {$case} dense rank ordered row {$row}");
        }

        $byName = [];
        foreach ($names as $row => $name) {
            $byName[strtolower($name)][] = $row;
        }
        foreach ($byName as $partitionRows) {
            usort($partitionRows, static fn (int $left, int $right): int => strcasecmp($colors[$left], $colors[$right]));
            $ranks = SQLiteWindowFunction::denseRank(array_map(static fn (int $row): string => strtolower($colors[$row]), $partitionRows));
            $expectedRanks = [];
            $lastColor = null;
            $rank = 0;
            foreach ($partitionRows as $offset => $row) {
                $color = strtolower($colors[$row]);
                if ($lastColor === null || $color !== $lastColor) {
                    $rank++;
                    $lastColor = $color;
                }
                $expectedRanks[$offset] = $rank;
            }
            foreach ($partitionRows as $offset => $row) {
                $t->same($expectedRanks[$offset], $ranks[$offset], "window9.test dynamic {$case} partition rank row {$row}");
            }
        }
    };
}

$tests['real upstream window error corpus rejects invalid dynamic custom frames'] = static function (TestRunner $t) use ($orderedValues, $orderedKeys): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::customFrameStateValues('median', $orderedValues, $orderedKeys, 'ROWS', '-1 PRECEDING', '1 FOLLOWING'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::customFrameStateValues('median', $orderedValues, $orderedKeys, 'GROUPS', 'CURRENT ROW', '-1 FOLLOWING'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::customFrameStateValues('missing', $orderedValues, $orderedKeys, 'ROWS', 'CURRENT ROW', 'CURRENT ROW'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $orderedValues, [1, 2], 'RANGE', 'CURRENT ROW', '1 FOLLOWING'));
};

return $tests;
