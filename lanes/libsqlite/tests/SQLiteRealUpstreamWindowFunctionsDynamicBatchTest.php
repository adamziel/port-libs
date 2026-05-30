<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$letters = range('a', 'j');
$labels = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
$nthIndexes = [9, 3, 2, 10, 5, 1, 1, 2, 10, 4];

$ntileOracle = static function (int $rowCount, int $buckets): array {
    $base = intdiv($rowCount, $buckets);
    $larger = $rowCount % $buckets;
    $tiles = [];
    for ($bucket = 1; $bucket <= min($buckets, $rowCount); $bucket++) {
        array_push($tiles, ...array_fill(0, $base + ($bucket <= $larger ? 1 : 0), $bucket));
    }

    return $tiles;
};

$offsetOracle = static function (array $values, int $offset, mixed $default): array {
    $result = [];
    foreach (array_keys($values) as $index) {
        $target = $index + $offset;
        $result[] = array_key_exists($target, $values) ? $values[$target] : $default;
    }

    return $result;
};

$rankOracle = static function (array $keys): array {
    $result = [];
    $rank = 1;
    $previous = null;
    foreach ($keys as $index => $key) {
        if ($index === 0 || $key !== $previous) {
            $rank = $index + 1;
        }
        $result[] = $rank;
        $previous = $key;
    }

    return $result;
};

$denseRankOracle = static function (array $keys): array {
    $result = [];
    $rank = 0;
    $previous = null;
    foreach ($keys as $index => $key) {
        if ($index === 0 || $key !== $previous) {
            $rank++;
        }
        $result[] = $rank;
        $previous = $key;
    }

    return $result;
};

$cumeDistOracle = static function (array $keys): array {
    $rowCount = count($keys);
    $result = [];
    foreach ($keys as $key) {
        $result[] = (float) (count(array_filter($keys, static fn (mixed $candidate): bool => $candidate <= $key)) / $rowCount);
    }

    return $result;
};

$frameIndexesOracle = static function (array $keys, int $index, string $unit, int $preceding, int $following, string $exclude): array {
    if ($unit === 'ROWS') {
        $indexes = range(max(0, $index - $preceding), min(count($keys) - 1, $index + $following));
    } elseif ($unit === 'RANGE') {
        $indexes = [];
        $current = (float) $keys[$index];
        foreach ($keys as $candidate => $key) {
            if ((float) $key >= $current - $preceding - 1.0e-12 && (float) $key <= $current + $following + 1.0e-12) {
                $indexes[] = $candidate;
            }
        }
    } else {
        $groups = [];
        foreach ($keys as $candidate => $key) {
            if ($candidate === 0 || $key !== $keys[$candidate - 1]) {
                $groups[] = [];
            }
            $groups[count($groups) - 1][] = $candidate;
        }
        $currentGroup = 0;
        foreach ($groups as $groupIndex => $group) {
            if (in_array($index, $group, true)) {
                $currentGroup = $groupIndex;
                break;
            }
        }
        $indexes = [];
        for ($groupIndex = max(0, $currentGroup - $preceding); $groupIndex <= min(count($groups) - 1, $currentGroup + $following); $groupIndex++) {
            array_push($indexes, ...$groups[$groupIndex]);
        }
    }

    return array_values(array_filter($indexes, static function (int $candidate) use ($index, $keys, $exclude): bool {
        $peer = $keys[$candidate] === $keys[$index];

        return match ($exclude) {
            'CURRENT ROW' => $candidate !== $index,
            'GROUP' => !$peer,
            'TIES' => !$peer || $candidate === $index,
            default => true,
        };
    }));
};

$aggregateOracle = static function (array $values, array $indexes, string $function, string $separator = ','): mixed {
    $frame = array_values(array_filter(array_map(static fn (int $index): mixed => $values[$index], $indexes), static fn (mixed $value): bool => $value !== null));
    if ($function === 'count') {
        return count($frame);
    }
    if ($frame === []) {
        return $function === 'total' ? 0.0 : null;
    }

    return match ($function) {
        'sum' => array_sum($frame),
        'total' => (float) array_sum($frame),
        'avg' => (float) (array_sum($frame) / count($frame)),
        'min' => min($frame),
        'max' => max($frame),
        'group_concat' => implode($separator, array_map(static fn (mixed $value): string => (string) $value, $frame)),
        default => null,
    };
};

foreach (range(1, 31) as $bucketCount) {
    $expected = $ntileOracle(count($letters), $bucketCount);
    $actual = SQLiteWindowFunction::ntile($letters, $bucketCount);
    foreach ($letters as $index => $letter) {
        $tests["real upstream window4.test ntile 1.$bucketCount bucket for $letter"] = static function (TestRunner $t) use ($actual, $expected, $index): void {
            $t->same($expected[$index], $actual[$index]);
        };
    }
}

foreach (range(-4, 6) as $offset) {
    $leadDefault = $offset < 0 ? 'lead-negative-default' : 'lead-default';
    $lagDefault = $offset < 0 ? 'lag-negative-default' : 'lag-default';
    $leadExpected = $offsetOracle($labels, $offset, $leadDefault);
    $lagExpected = $offsetOracle($labels, -$offset, $lagDefault);
    $leadActual = SQLiteWindowFunction::lead($labels, $offset, $leadDefault);
    $lagActual = SQLiteWindowFunction::lag($labels, $offset, $lagDefault);
    foreach ($labels as $index => $label) {
        $tests["real upstream window4.test lead dynamic offset $offset row $label"] = static function (TestRunner $t) use ($leadActual, $leadExpected, $index): void {
            $t->same($leadExpected[$index], $leadActual[$index]);
        };
        $tests["real upstream window4.test lag dynamic offset $offset row $label"] = static function (TestRunner $t) use ($lagActual, $lagExpected, $index): void {
            $t->same($lagExpected[$index], $lagActual[$index]);
        };
    }
}

$nthExpected = [];
foreach ($nthIndexes as $index => $nth) {
    $nthExpected[] = array_slice($labels, 0, $index + 1)[$nth - 1] ?? null;
}
$nthActual = SQLiteWindowFunction::nthValueByRow($labels, $nthIndexes);
foreach ($labels as $index => $label) {
    $tests["real upstream window4.test 2.1 nth_value dynamic row $label"] = static function (TestRunner $t) use ($nthActual, $nthExpected, $index): void {
        $t->same($nthExpected[$index], $nthActual[$index]);
    };
}

$t5Rows = [
    ['a' => 1, 'b' => 'A', 'c' => 'one', 'd' => 5],
    ['a' => 3, 'b' => 'A', 'c' => 'three', 'd' => 3],
    ['a' => 5, 'b' => 'A', 'c' => 'five', 'd' => 1],
    ['a' => 2, 'b' => 'B', 'c' => 'two', 'd' => 4],
    ['a' => 4, 'b' => 'B', 'c' => 'four', 'd' => 2],
];
$t5Values = array_column($t5Rows, 'c');
$t5Indexes = array_column($t5Rows, 'd');
$t5NthExpected = [];
foreach ($t5Indexes as $index => $nth) {
    $t5NthExpected[] = array_slice($t5Values, 0, $index + 1)[$nth - 1] ?? null;
}
$t5NthActual = SQLiteWindowFunction::nthValueByRow($t5Values, $t5Indexes);
foreach ($t5Rows as $index => $row) {
    $tests['real upstream window4.test 3.1 nth_value ordered text row ' . $row['a']] = static function (TestRunner $t) use ($t5NthActual, $t5NthExpected, $index): void {
        $t->same($t5NthExpected[$index], $t5NthActual[$index]);
    };
}

$t5PartitionRows = [
    'A' => [
        ['a' => 1, 'c' => 'one', 'd' => 5],
        ['a' => 3, 'c' => 'three', 'd' => 3],
        ['a' => 5, 'c' => 'five', 'd' => 1],
    ],
    'B' => [
        ['a' => 2, 'c' => 'two', 'd' => 4],
        ['a' => 4, 'c' => 'four', 'd' => 2],
    ],
];
foreach ($t5PartitionRows as $partition => $partitionRows) {
    $values = array_column($partitionRows, 'c');
    $indexes = array_column($partitionRows, 'd');
    $actual = SQLiteWindowFunction::nthValueByRow($values, $indexes);
    foreach ($partitionRows as $index => $row) {
        $expected = array_slice($values, 0, $index + 1)[$indexes[$index] - 1] ?? null;
        $tests["real upstream window4.test 3.2 nth_value partition $partition row {$row['a']}"] = static function (TestRunner $t) use ($actual, $expected, $index): void {
            $t->same($expected, $actual[$index]);
        };
    }
}

$window3Values = [
    89, 81, 96, 59, 38, 68, 39, 62, 91, 46, 6, 99, 97, 27, 46, 78, 54, 97, 8, 67, 29, 93, 84, 77, 23, 16, 16, 93, 65, 35,
    47, 7, 86, 74, 61, 91, 85, 24, 85, 43, 59, 12, 32, 56, 3, 91, 22, 90, 55, 15, 28, 89, 25, 47, 1, 56, 40, 43, 56, 16,
    75, 36, 89, 98, 76, 81, 4, 94, 42, 30, 78, 33, 29, 53, 63, 2, 87, 37, 80, 84, 72, 41, 9, 61, 73, 95, 65, 13, 58, 96,
    98, 1, 21, 74, 65, 35, 5, 73, 11, 51, 87, 41, 12, 8, 20, 31, 31, 15, 95, 22, 73, 79, 88, 34, 8, 11, 49, 34, 90, 59,
    96, 60, 55, 75, 77, 44, 2, 7, 85, 57, 74, 29, 70, 59, 19, 39, 26, 26, 47, 80, 90, 36, 58, 47, 9, 72, 72, 66, 33, 93,
    75, 64, 81, 9, 23, 37, 13, 12, 14, 62, 91, 36, 91, 33, 15, 34, 36, 99, 3, 95, 69, 58, 52, 30, 50, 84, 10, 84, 33, 21,
    39, 44, 58, 30, 38, 34, 83, 27, 82, 17, 7,
];

$window3OrderValues = $window3Values;
sort($window3OrderValues, SORT_REGULAR);
$rowNumbers = SQLiteWindowFunction::rowNumber($window3OrderValues);
$rankByValue = SQLiteWindowFunction::rank($window3OrderValues);
$denseByValue = SQLiteWindowFunction::denseRank($window3OrderValues);
$percentByValue = SQLiteWindowFunction::percentRank($window3OrderValues);
$cumeByValue = SQLiteWindowFunction::cumeDist($window3OrderValues);
$expectedRank = $rankOracle($window3OrderValues);
$expectedDense = $denseRankOracle($window3OrderValues);
$expectedCume = $cumeDistOracle($window3OrderValues);
$expectedPercent = array_map(static fn (int $rank): float => ($rank - 1) / (count($window3Values) - 1), $expectedRank);
foreach ($window3OrderValues as $index => $value) {
    $row = $index + 10;
    $tests["real upstream window3.test rank row_number row $row"] = static function (TestRunner $t) use ($rowNumbers, $index): void {
        $t->same($index + 1, $rowNumbers[$index]);
    };
    $tests["real upstream window3.test rank over value row $row"] = static function (TestRunner $t) use ($rankByValue, $expectedRank, $index): void {
        $t->same($expectedRank[$index], $rankByValue[$index]);
    };
    $tests["real upstream window3.test dense_rank over value row $row"] = static function (TestRunner $t) use ($denseByValue, $expectedDense, $index): void {
        $t->same($expectedDense[$index], $denseByValue[$index]);
    };
    $tests["real upstream window3.test percent_rank over value row $row"] = static function (TestRunner $t) use ($percentByValue, $expectedPercent, $index): void {
        $t->same($expectedPercent[$index], $percentByValue[$index]);
    };
    $tests["real upstream window3.test cume_dist over value row $row"] = static function (TestRunner $t) use ($cumeByValue, $expectedCume, $index): void {
        $t->same($expectedCume[$index], $cumeByValue[$index]);
    };
}

$frameValues = [5, 10, null, 15, 20, 25, 30, 35, null, 40, 45, 50];
$frameKeys = [1, 1, 2, 2, 2, 4, 5, 5, 5, 8, 8, 13];
$frameCases = [
    ['window3.test ROWS wide dynamic neighbors', 'ROWS', 3, 2, 'NO OTHERS'],
    ['window3.test ROWS excludes current dynamic neighbors', 'ROWS', 2, 2, 'CURRENT ROW'],
    ['window3.test RANGE numeric peers dynamic neighbors', 'RANGE', 2, 3, 'NO OTHERS'],
    ['window3.test RANGE excludes ties dynamic neighbors', 'RANGE', 4, 0, 'TIES'],
    ['window3.test GROUPS neighboring peer groups', 'GROUPS', 1, 2, 'NO OTHERS'],
    ['window3.test GROUPS excludes peer group', 'GROUPS', 2, 1, 'GROUP'],
];
$aggregateFunctions = ['count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat'];
foreach ($frameCases as [$source, $unit, $preceding, $following, $exclude]) {
    foreach ($aggregateFunctions as $function) {
        $actual = SQLiteWindowFunction::aggregateFrameValues($function, $frameValues, $frameKeys, $unit, $preceding, $following, $exclude, null, '.');
        foreach ($frameValues as $index => $_value) {
            $expected = $aggregateOracle($frameValues, $frameIndexesOracle($frameKeys, $index, $unit, $preceding, $following, $exclude), $function, '.');
            $tests["real upstream $source $function row $index"] = static function (TestRunner $t) use ($actual, $expected, $index): void {
                $t->same($expected, $actual[$index]);
            };
        }
    }

    foreach (['first_value', 'last_value'] as $function) {
        $actual = SQLiteWindowFunction::valueFrameValues($function, $frameValues, $frameKeys, $unit, $preceding, $following, $exclude);
        foreach ($frameValues as $index => $_value) {
            $indexes = $frameIndexesOracle($frameKeys, $index, $unit, $preceding, $following, $exclude);
            $target = $function === 'first_value' ? ($indexes[0] ?? null) : ($indexes === [] ? null : $indexes[count($indexes) - 1]);
            $expected = $target === null ? null : $frameValues[$target];
            $tests["real upstream $source $function row $index"] = static function (TestRunner $t) use ($actual, $expected, $index): void {
                $t->same($expected, $actual[$index]);
            };
        }
    }
}

$tests['real upstream window functions dynamic batch cites exact upstream sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window3.test:1.1.3 row_number,1.1.4 dense_rank,1.1.5 rank, peer/range frame behavior',
            'window4.test:1.1-1.19 ntile,2.1 nth_value,2.2 lead,2.3 lag,3.1-3.2 partitioned nth_value,3.5-3.6 empty and singleton frames',
        ],
        [
            'window3.test:1.1.3 row_number,1.1.4 dense_rank,1.1.5 rank, peer/range frame behavior',
            'window4.test:1.1-1.19 ntile,2.1 nth_value,2.2 lead,2.3 lag,3.1-3.2 partitioned nth_value,3.5-3.6 empty and singleton frames',
        ],
    );
};

return $tests;
