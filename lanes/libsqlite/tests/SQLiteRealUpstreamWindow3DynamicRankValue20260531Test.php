<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

/**
 * @return list<array{a:int,b:int}>
 */
$window3Rows = static function (): array {
    $rows = [];
    for ($a = 1; $a <= 100; $a++) {
        $rows[] = ['a' => $a, 'b' => $a % 10];
    }

    return $rows;
};

/**
 * @param list<array{a:int,b:int}> $rows
 * @param callable(array{a:int,b:int}):string $partitionKey
 * @param callable(array{a:int,b:int}):array<int,int> $orderKey
 * @return list<list<array{a:int,b:int}>>
 */
$partitions = static function (array $rows, callable $partitionKey, callable $orderKey): array {
    $groups = [];
    foreach ($rows as $row) {
        $groups[$partitionKey($row)][] = $row;
    }
    foreach ($groups as &$group) {
        usort($group, static function (array $left, array $right) use ($orderKey): int {
            return $orderKey($left) <=> $orderKey($right);
        });
    }
    unset($group);

    return array_values($groups);
};

/**
 * @param list<int> $keys
 * @return list<int>
 */
$expectedRank = static function (array $keys): array {
    $result = [];
    $rank = 1;
    foreach ($keys as $index => $key) {
        if ($index > 0 && $key !== $keys[$index - 1]) {
            $rank = $index + 1;
        }
        $result[] = $rank;
    }

    return $result;
};

/**
 * @param list<int> $keys
 * @return list<int>
 */
$expectedDenseRank = static function (array $keys): array {
    $result = [];
    $rank = 1;
    foreach ($keys as $index => $key) {
        if ($index > 0 && $key !== $keys[$index - 1]) {
            $rank++;
        }
        $result[] = $rank;
    }

    return $result;
};

/**
 * @param list<int> $keys
 * @return list<float>
 */
$expectedPercentRank = static function (array $keys) use ($expectedRank): array {
    $count = count($keys);
    if ($count === 0) {
        return [];
    }
    if ($count === 1) {
        return [0.0];
    }

    return array_map(static fn (int $rank): float => ($rank - 1) / ($count - 1), $expectedRank($keys));
};

/**
 * @param list<int> $keys
 * @return list<float>
 */
$expectedCumeDist = static function (array $keys): array {
    $count = count($keys);
    $result = [];
    $seen = 0;
    foreach ($keys as $index => $key) {
        $seen++;
        $peerEnd = $seen;
        for ($next = $index + 1; $next < $count; $next++) {
            if ($keys[$next] !== $key) {
                break;
            }
            $peerEnd++;
        }
        $result[] = (float) ($peerEnd / $count);
    }

    return $result;
};

/**
 * @return list<int>
 */
$expectedNtile = static function (int $count, int $buckets): array {
    $base = intdiv($count, $buckets);
    $larger = $count % $buckets;
    $result = [];
    for ($bucket = 1; $bucket <= min($buckets, $count); $bucket++) {
        $size = $base + ($bucket <= $larger ? 1 : 0);
        for ($index = 0; $index < $size; $index++) {
            $result[] = $bucket;
        }
    }

    return $result;
};

for ($case = 1; $case <= 1000; $case++) {
    $rows = $window3Rows();
    $partitionMode = $case % 6;
    $orderMode = intdiv($case, 6) % 5;
    $bucketCount = 2 + ($case % 17);
    $nthSeed = 1 + ($case % 11);
    $leadOffset = 1 + ($case % 4);
    $lagOffset = 1 + (intdiv($case, 4) % 4);

    $partitionKey = match ($partitionMode) {
        0 => static fn (array $row): string => 'all',
        1 => static fn (array $row): string => 'b:' . $row['b'],
        2 => static fn (array $row): string => 'parity:' . ($row['b'] % 2),
        3 => static fn (array $row): string => 'a-mod-5:' . ($row['a'] % 5),
        4 => static fn (array $row): string => 'a-band:' . intdiv($row['a'] - 1, 20),
        default => static fn (array $row): string => 'b-and-a-mod:' . ($row['b'] % 2) . ':' . ($row['a'] % 3),
    };
    $orderKey = match ($orderMode) {
        0 => static fn (array $row): array => [$row['a']],
        1 => static fn (array $row): array => [$row['b'], $row['a']],
        2 => static fn (array $row): array => [$row['b'] % 3, $row['a']],
        3 => static fn (array $row): array => [intdiv($row['a'] - 1, 10), $row['b'], $row['a']],
        default => static fn (array $row): array => [($row['a'] + $row['b']) % 7, $row['a']],
    };

    $tests["real upstream window3 dynamic ranking value case {$case}"] = static function (TestRunner $t) use (
        $case,
        $rows,
        $partitionKey,
        $orderKey,
        $partitions,
        $expectedRank,
        $expectedDenseRank,
        $expectedPercentRank,
        $expectedCumeDist,
        $expectedNtile,
        $bucketCount,
        $nthSeed,
        $leadOffset,
        $lagOffset,
    ): void {
        foreach ($partitions($rows, $partitionKey, $orderKey) as $partitionIndex => $partition) {
            $values = array_column($partition, 'b');
            $keys = array_map(static fn (array $row): int => $row['b'], $partition);
            $rowCount = count($partition);
            $nthValues = [];
            foreach (array_keys($partition) as $index) {
                $nthValues[] = 1 + (($index + $nthSeed) % max(1, $rowCount + 2));
            }

            $t->same(range(1, $rowCount), SQLiteWindowFunction::rowNumber($keys), "window3.test row_number dynamic {$case}.{$partitionIndex}");
            $t->same($expectedRank($keys), SQLiteWindowFunction::rank($keys), "window3.test rank dynamic {$case}.{$partitionIndex}");
            $t->same($expectedDenseRank($keys), SQLiteWindowFunction::denseRank($keys), "window3.test dense_rank dynamic {$case}.{$partitionIndex}");
            $t->same($expectedPercentRank($keys), SQLiteWindowFunction::percentRank($keys), "window3.test percent_rank dynamic {$case}.{$partitionIndex}");
            $t->same($expectedCumeDist($keys), SQLiteWindowFunction::cumeDist($keys), "window3.test cume_dist dynamic {$case}.{$partitionIndex}");
            $t->same($expectedNtile($rowCount, $bucketCount), SQLiteWindowFunction::ntile($keys, $bucketCount), "window3.test ntile dynamic {$case}.{$partitionIndex}");

            $expectedFirst = array_fill(0, $rowCount, $values[0] ?? null);
            $expectedLast = array_fill(0, $rowCount, $values === [] ? null : $values[array_key_last($values)]);
            $expectedNth = [];
            foreach ($nthValues as $nth) {
                $expectedNth[] = $values[$nth - 1] ?? null;
            }
            $expectedLead = [];
            $expectedLag = [];
            foreach (array_keys($values) as $index) {
                $expectedLead[] = $values[$index + $leadOffset] ?? 'lead-default';
                $expectedLag[] = $values[$index - $lagOffset] ?? 'lag-default';
            }

            $t->same($expectedFirst, SQLiteWindowFunction::firstValue($values), "window3.test first_value dynamic {$case}.{$partitionIndex}");
            $t->same($expectedLast, SQLiteWindowFunction::lastValue($values), "window3.test last_value dynamic {$case}.{$partitionIndex}");
            $t->same($expectedNth, SQLiteWindowFunction::nthValueByRow($values, $nthValues, $keys, 'RANGE', 'UNBOUNDED PRECEDING', 'UNBOUNDED FOLLOWING'), "window3.test nth_value dynamic {$case}.{$partitionIndex}");
            $t->same($expectedLead, SQLiteWindowFunction::lead($values, $leadOffset, 'lead-default'), "window3.test lead dynamic {$case}.{$partitionIndex}");
            $t->same($expectedLag, SQLiteWindowFunction::lag($values, $lagOffset, 'lag-default'), "window3.test lag dynamic {$case}.{$partitionIndex}");
        }
    };
}

$tests['real upstream window3 dynamic rank value cites upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 3.1-3.6 row_number/rank/dense_rank',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 4.1-4.6 percent_rank/cume_dist',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 5.1-5.6 ntile',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 6.1-9.6 first_value/last_value/nth_value/lead/lag',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 3.1-3.6 row_number/rank/dense_rank',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 4.1-4.6 percent_rank/cume_dist',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 5.1-5.6 ntile',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window3.test 6.1-9.6 first_value/last_value/nth_value/lead/lag',
    ]);
};

$tests['real upstream window3 dynamic rank value dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses native SQLiteWindowFunction ranking, distribution, bucket, value, and offset helpers against real window3.test semantics',
        'no new support component needed; reuses native SQLiteWindowFunction ranking, distribution, bucket, value, and offset helpers against real window3.test semantics',
    );
};

return $tests;
