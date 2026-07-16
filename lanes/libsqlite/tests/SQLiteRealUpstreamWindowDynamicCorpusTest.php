<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$appValues = range('a', 'j');
$settingValues = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
$settingNth = [9, 3, 2, 10, 5, 1, 1, 2, 10, 4];
$accountIds = [1, 2, 3, 4, 5, 6];
$accountGroups = [1, 1, 1, 3, 3, 3];
$accountValues = [2, null, 4, null, 8, 1];

$ntileOracle = static function (int $rows, int $buckets): array {
    $base = intdiv($rows, $buckets);
    $larger = $rows % $buckets;
    $result = [];
    for ($bucket = 1; $bucket <= min($buckets, $rows); $bucket++) {
        array_push($result, ...array_fill(0, $base + ($bucket <= $larger ? 1 : 0), $bucket));
    }

    return $result;
};

$leadOracle = static function (array $values, int $offset = 1, mixed $default = null): array {
    $result = [];
    foreach (array_keys($values) as $index) {
        $target = $index + $offset;
        $result[] = array_key_exists($target, $values) ? $values[$target] : $default;
    }

    return $result;
};

$lagOracle = static function (array $values, int $offset = 1, mixed $default = null): array {
    $result = [];
    foreach (array_keys($values) as $index) {
        $target = $index - $offset;
        $result[] = array_key_exists($target, $values) ? $values[$target] : $default;
    }

    return $result;
};

$rankOracle = static function (array $keys): array {
    $result = [];
    $rank = 1;
    $previous = null;
    $seen = 0;
    foreach ($keys as $key) {
        $seen++;
        if ($seen === 1 || $key !== $previous) {
            $rank = $seen;
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
    $count = count($keys);
    $result = [];
    foreach ($keys as $key) {
        $result[] = (float) (count(array_filter($keys, static fn (mixed $candidate): bool => $candidate <= $key)) / $count);
    }

    return $result;
};

$frameIndexesOracle = static function (array $keys, int $index, string $unit, int|float $preceding, int|float $following, string $exclude = 'NO OTHERS'): array {
    $count = count($keys);
    $unit = strtoupper($unit);
    if ($unit === 'ROWS') {
        $indexes = range(max(0, $index - (int) $preceding), min($count - 1, $index + (int) $following));
    } elseif ($unit === 'RANGE') {
        $indexes = [];
        $current = (float) $keys[$index];
        foreach ($keys as $candidateIndex => $key) {
            $value = (float) $key;
            if ($value >= $current - $preceding - 1.0e-12 && $value <= $current + $following + 1.0e-12) {
                $indexes[] = $candidateIndex;
            }
        }
    } else {
        $groups = [];
        foreach ($keys as $candidateIndex => $key) {
            if ($candidateIndex === 0 || $key !== $keys[$candidateIndex - 1]) {
                $groups[] = [];
            }
            $groups[count($groups) - 1][] = $candidateIndex;
        }
        $currentGroup = 0;
        foreach ($groups as $groupIndex => $group) {
            if (in_array($index, $group, true)) {
                $currentGroup = $groupIndex;
                break;
            }
        }
        $indexes = [];
        for ($groupIndex = max(0, $currentGroup - (int) $preceding); $groupIndex <= min(count($groups) - 1, $currentGroup + (int) $following); $groupIndex++) {
            array_push($indexes, ...$groups[$groupIndex]);
        }
    }

    return array_values(array_filter($indexes, static function (int $candidateIndex) use ($index, $keys, $exclude): bool {
        $peer = $keys[$candidateIndex] === $keys[$index];

        return match ($exclude) {
            'CURRENT ROW' => $candidateIndex !== $index,
            'GROUP' => !$peer,
            'TIES' => !$peer || $candidateIndex === $index,
            default => true,
        };
    }));
};

$sumOracle = static function (array $values, array $indexes): int|float|null {
    $sum = null;
    foreach ($indexes as $index) {
        if ($values[$index] !== null) {
            $sum = ($sum ?? 0) + $values[$index];
        }
    }

    return $sum;
};

foreach (range(1, 19) as $bucketCount) {
    $expected = $ntileOracle(count($appValues), $bucketCount);
    foreach ($appValues as $index => $label) {
        $tests["real upstream window4.test 1.$bucketCount ntile bucket for $label"] = static function (TestRunner $t) use ($appValues, $bucketCount, $expected, $index): void {
            $t->same($expected[$index], SQLiteWindowFunction::ntile($appValues, $bucketCount)[$index]);
        };
    }
}

$offsetCases = [
    'window4.test 2.2.1 lead default' => [SQLiteWindowFunction::lead(...), $leadOracle($settingValues), $settingValues],
    'window4.test 2.2.2 lead offset two' => [static fn (array $rows): array => SQLiteWindowFunction::lead($rows, 2), $leadOracle($settingValues, 2), $settingValues],
    'window4.test 2.2.3 lead offset three default' => [static fn (array $rows): array => SQLiteWindowFunction::lead($rows, 3, 'abc'), $leadOracle($settingValues, 3, 'abc'), $settingValues],
    'window4.test 2.3.1 lag default' => [SQLiteWindowFunction::lag(...), $lagOracle($settingValues), $settingValues],
    'window4.test 2.3.2 lag offset two' => [static fn (array $rows): array => SQLiteWindowFunction::lag($rows, 2), $lagOracle($settingValues, 2), $settingValues],
    'window4.test 2.3.3 lag offset three default' => [static fn (array $rows): array => SQLiteWindowFunction::lag($rows, 3, 'abc'), $lagOracle($settingValues, 3, 'abc'), $settingValues],
    'window4.test 7.1 lead integer default' => [SQLiteWindowFunction::lead(...), $leadOracle([2, 4, 6, 8, 10]), [2, 4, 6, 8, 10]],
    'window4.test 7.2 lead integer offset two' => [static fn (array $rows): array => SQLiteWindowFunction::lead($rows, 2), $leadOracle([2, 4, 6, 8, 10], 2), [2, 4, 6, 8, 10]],
    'window4.test 7.3 lead integer offset three default' => [static fn (array $rows): array => SQLiteWindowFunction::lead($rows, 3, -1), $leadOracle([2, 4, 6, 8, 10], 3, -1), [2, 4, 6, 8, 10]],
    'window4.test 10.2 lead negative offset acts like lag' => [static fn (array $rows): array => SQLiteWindowFunction::lead($rows, -1), $lagOracle($accountValues, 1), $accountValues],
    'window4.test 10.3 lag negative offset acts like lead' => [static fn (array $rows): array => SQLiteWindowFunction::lag($rows, -1), $leadOracle($accountValues, 1), $accountValues],
];

foreach ($offsetCases as $source => [$windowRunner, $expected, $rows]) {
    foreach ($expected as $index => $value) {
        $tests["real upstream $source row $index"] = static function (TestRunner $t) use ($windowRunner, $rows, $expected, $index): void {
            $t->same($expected[$index], $windowRunner($rows)[$index]);
        };
    }
}

foreach ($settingNth as $index => $nth) {
    $expected = $settingValues[$nth - 1] ?? null;
    $tests["real upstream window4.test 2.1 nth_value dynamic row $index"] = static function (TestRunner $t) use ($settingValues, $settingNth, $index, $expected): void {
        $t->same($expected, SQLiteWindowFunction::nthValue($settingValues, $settingNth[$index])[$index]);
    };
}

$rankingKeys = [1, 1, 1, 4, 4, 6, 7];
$rankingCases = [
    'window4.test 9.1 rank empty over clause' => [SQLiteWindowFunction::rank(array_fill(0, 7, 0)), array_fill(0, 7, 1)],
    'window4.test 9.2 dense_rank partition singleton' => [SQLiteWindowFunction::denseRank(array_fill(0, 7, 0)), array_fill(0, 7, 1)],
    'window4.test 9.4 rank ordered peers' => [SQLiteWindowFunction::rank($rankingKeys), $rankOracle($rankingKeys)],
    'window4.test 9.6 percent_rank empty over clause' => [SQLiteWindowFunction::percentRank(array_fill(0, 3, 0)), [0.0, 0.0, 0.0]],
    'window4.test 9.7 cume_dist empty over clause' => [SQLiteWindowFunction::cumeDist(array_fill(0, 3, 0)), [1.0, 1.0, 1.0]],
    'window3.test percent_rank range peers' => [SQLiteWindowFunction::percentRank($rankingKeys), array_map(static fn (int $rank): float => ($rank - 1) / 6, $rankOracle($rankingKeys))],
    'window3.test cume_dist range peers' => [SQLiteWindowFunction::cumeDist($rankingKeys), $cumeDistOracle($rankingKeys)],
    'window3.test ntile large bucket range peers' => [SQLiteWindowFunction::ntile($rankingKeys, 100), $ntileOracle(7, 100)],
];

foreach ($rankingCases as $source => [$actual, $expected]) {
    foreach ($expected as $index => $value) {
        $tests["real upstream $source row $index"] = static function (TestRunner $t) use ($actual, $expected, $index): void {
            $t->same($expected[$index], $actual[$index]);
        };
    }
}

$frameValues = [3, 1, 4, 1, 5, 9, 2, 6];
$frameKeys = [1, 1, 2, 3, 3, 3, 5, 8];
$frameCases = [
    ['window3.test ROWS current-to-following', 'ROWS', 0, 2, 'NO OTHERS'],
    ['window3.test ROWS preceding-to-current exclude current', 'ROWS', 2, 0, 'CURRENT ROW'],
    ['window3.test RANGE peer numeric bounds', 'RANGE', 1, 1, 'NO OTHERS'],
    ['window3.test RANGE exclude ties', 'RANGE', 2, 0, 'TIES'],
    ['window3.test GROUPS neighbor peer groups', 'GROUPS', 1, 1, 'NO OTHERS'],
    ['window3.test GROUPS exclude peer group', 'GROUPS', 1, 1, 'GROUP'],
];

foreach ($frameCases as [$source, $unit, $preceding, $following, $exclude]) {
    $countActual = SQLiteWindowFunction::aggregateFrameValues('count', $frameValues, $frameKeys, $unit, $preceding, $following, $exclude);
    $sumActual = SQLiteWindowFunction::aggregateFrameValues('sum', $frameValues, $frameKeys, $unit, $preceding, $following, $exclude);
    $groupActual = SQLiteWindowFunction::aggregateFrameValues('group_concat', $frameValues, $frameKeys, $unit, $preceding, $following, $exclude);
    $firstActual = SQLiteWindowFunction::valueFrameValues('first_value', $frameValues, $frameKeys, $unit, $preceding, $following, $exclude);
    $lastActual = SQLiteWindowFunction::valueFrameValues('last_value', $frameValues, $frameKeys, $unit, $preceding, $following, $exclude);
    $nthActual = SQLiteWindowFunction::valueFrameValues('nth_value', $frameValues, $frameKeys, $unit, $preceding, $following, $exclude, 2);

    foreach ($frameValues as $index => $_value) {
        $indexes = $frameIndexesOracle($frameKeys, $index, $unit, $preceding, $following, $exclude);
        $expectedCount = count($indexes);
        $expectedSum = $sumOracle($frameValues, $indexes);
        $expectedGroup = $indexes === [] ? null : implode(',', array_map(static fn (int $frameIndex): int => $frameValues[$frameIndex], $indexes));
        $expectedFirst = $indexes === [] ? null : $frameValues[$indexes[0]];
        $expectedLast = $indexes === [] ? null : $frameValues[$indexes[count($indexes) - 1]];
        $expectedNth = isset($indexes[1]) ? $frameValues[$indexes[1]] : null;

        $tests["real upstream $source count row $index"] = static function (TestRunner $t) use ($countActual, $expectedCount, $index): void {
            $t->same($expectedCount, $countActual[$index]);
        };
        $tests["real upstream $source sum row $index"] = static function (TestRunner $t) use ($sumActual, $expectedSum, $index): void {
            $t->same($expectedSum, $sumActual[$index]);
        };
        $tests["real upstream $source group concat row $index"] = static function (TestRunner $t) use ($groupActual, $expectedGroup, $index): void {
            $t->same($expectedGroup, $groupActual[$index]);
        };
        $tests["real upstream $source first value row $index"] = static function (TestRunner $t) use ($firstActual, $expectedFirst, $index): void {
            $t->same($expectedFirst, $firstActual[$index]);
        };
        $tests["real upstream $source last value row $index"] = static function (TestRunner $t) use ($lastActual, $expectedLast, $index): void {
            $t->same($expectedLast, $lastActual[$index]);
        };
        $tests["real upstream $source nth value row $index"] = static function (TestRunner $t) use ($nthActual, $expectedNth, $index): void {
            $t->same($expectedNth, $nthActual[$index]);
        };
    }
}

$partitionCases = [
    'window4.test 10 min partition a=1' => [[2, null, 4], [2, 2, 2]],
    'window4.test 10 min partition a=3' => [[null, 8, 1], [null, 8, 1]],
];

foreach ($partitionCases as $source => [$values, $expected]) {
    $actual = SQLiteWindowFunction::aggregateFrameValues('min', $values, range(1, count($values)), 'ROWS', 100, 0);
    foreach ($expected as $index => $value) {
        $tests["real upstream $source row $index"] = static function (TestRunner $t) use ($actual, $expected, $index): void {
            $t->same($expected[$index], $actual[$index]);
        };
    }
}

$tests['real upstream window4.test 1.x rejects zero ntile bucket'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::ntile([1, 2, 3], 0));
};

$tests['real upstream window1.test rejects negative ntile bucket'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::ntile([1, 2, 3], -1));
};

$tests['real upstream window6.test 10.1 rejects zero nth_value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValue([2, 3, 4], 0));
};

$tests['real upstream window6.test 10.1 rejects negative nth_value'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWindowFunction::nthValue([2, 3, 4], -1));
};

$tests['real upstream window4.test lead zero offset returns current row'] = static function (TestRunner $t): void {
    $t->same([1, 2, 3], SQLiteWindowFunction::lead([1, 2, 3], 0));
};

$tests['real upstream window4.test lag zero offset returns current row'] = static function (TestRunner $t): void {
    $t->same([1, 2, 3], SQLiteWindowFunction::lag([1, 2, 3], 0));
};

return $tests;
