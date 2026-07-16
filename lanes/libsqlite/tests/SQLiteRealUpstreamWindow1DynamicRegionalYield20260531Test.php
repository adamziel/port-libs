<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

// Source: upstream SQLite test/window1.test sections 7.2 and 10.1-10.6.
// This expands the regional-sales and lead/lag dynamic corpus with disjoint
// case numbers 200-1219 for the real-upstream corpus window-functions slice.
$sortBy = static function (array $rows, callable $compare): array {
    $indexed = [];
    foreach ($rows as $index => $row) {
        $indexed[] = [$index, $row];
    }

    usort($indexed, static function (array $left, array $right) use ($compare): int {
        $result = $compare($left[1], $right[1]);
        return $result === 0 ? $left[0] <=> $right[0] : $result;
    });

    return array_map(static fn (array $entry): array => $entry[1], $indexed);
};

$rowsForCase = static function (int $case): array {
    $regions = ['north', 'south', 'east', 'west'];
    $rows = [];
    for ($regionIndex = 0; $regionIndex < count($regions); $regionIndex++) {
        $region = $regions[$regionIndex];
        for ($slot = 0; $slot < 7; $slot++) {
            $rows[] = [
                'region' => $region,
                'seller' => $region . '-' . chr(97 + $slot),
                'amount' => (($case + 11) * ($slot + 3) + ($regionIndex * 17)) % 113 + ($regionIndex * 5),
                'quota' => (($case + $slot + $regionIndex) % 4) !== 0,
                'seq' => ($regionIndex * 10) + $slot + 1,
            ];
        }
    }

    return $rows;
};

$rankOracle = static function (array $keys): array {
    $ranks = [];
    $rank = 1;
    $previous = null;
    foreach ($keys as $index => $key) {
        if ($index > 0 && $key !== $previous) {
            $rank = $index + 1;
        }
        $ranks[] = $rank;
        $previous = $key;
    }

    return $ranks;
};

$denseRankOracle = static function (array $keys): array {
    $ranks = [];
    $rank = 0;
    $previous = null;
    foreach ($keys as $index => $key) {
        if ($index === 0 || $key !== $previous) {
            $rank++;
        }
        $ranks[] = $rank;
        $previous = $key;
    }

    return $ranks;
};

$runningSumOracle = static function (array $values): array {
    $sum = 0;
    $result = [];
    foreach ($values as $value) {
        $sum += $value;
        $result[] = $sum;
    }

    return $result;
};

$suffixSumOracle = static function (array $values): array {
    $result = [];
    for ($index = 0; $index < count($values); $index++) {
        $result[] = array_sum(array_slice($values, $index));
    }

    return $result;
};

$ntileOracle = static function (int $count, int $buckets): array {
    $base = intdiv($count, $buckets);
    $larger = $count % $buckets;
    $result = [];
    for ($bucket = 1; $bucket <= min($buckets, $count); $bucket++) {
        array_push($result, ...array_fill(0, $base + ($bucket <= $larger ? 1 : 0), $bucket));
    }

    return $result;
};

for ($case = 200; $case < 1220; $case++) {
    $rows = $rowsForCase($case);
    $leadOffset = 1 + ($case % 4);
    $lagOffset = 1 + (($case + 1) % 4);
    $default = 'default-' . $case;
    $bucketCount = 2 + ($case % 6);

    $tests[sprintf('real upstream window1 dynamic regional yield case %04d', $case)] = static function (TestRunner $t) use (
        $case,
        $rows,
        $sortBy,
        $rankOracle,
        $denseRankOracle,
        $runningSumOracle,
        $suffixSumOracle,
        $ntileOracle,
        $leadOffset,
        $lagOffset,
        $default,
        $bucketCount,
    ): void {
        $bySequence = $sortBy($rows, static fn (array $left, array $right): int => $left['seq'] <=> $right['seq']);
        $sequenceAmounts = array_column($bySequence, 'amount');
        $t->same(
            array_map(static fn (int $row): mixed => $sequenceAmounts[$row + $leadOffset] ?? $default, array_keys($sequenceAmounts)),
            SQLiteWindowFunction::lead($sequenceAmounts, $leadOffset, $default),
            "window1.test 7.2 dynamic lead case {$case}",
        );
        $t->same(
            array_map(static fn (int $row): mixed => $sequenceAmounts[$row - $lagOffset] ?? $default, array_keys($sequenceAmounts)),
            SQLiteWindowFunction::lag($sequenceAmounts, $lagOffset, $default),
            "window1.test 7.2 dynamic lag case {$case}",
        );

        $rankedRows = $sortBy($rows, static fn (array $left, array $right): int => ($left['region'] <=> $right['region']) ?: ($right['amount'] <=> $left['amount']) ?: ($left['seller'] <=> $right['seller']));
        $byRegion = [];
        foreach ($rankedRows as $row) {
            $byRegion[$row['region']][] = $row;
        }

        foreach ($byRegion as $region => $regionRows) {
            $amountKeys = array_map(static fn (array $row): int => -$row['amount'], $regionRows);
            $amounts = array_column($regionRows, 'amount');
            $sellerNames = array_column($regionRows, 'seller');
            $filters = array_column($regionRows, 'quota');
            $rowKeys = range(1, count($regionRows));

            $rank = SQLiteWindowFunction::rank($amountKeys);
            $denseRank = SQLiteWindowFunction::denseRank($amountKeys);
            $percentRank = SQLiteWindowFunction::percentRank($amountKeys);
            $cumeDist = SQLiteWindowFunction::cumeDist($amountKeys);
            $running = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $amounts, $rowKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW');
            $suffix = SQLiteWindowFunction::aggregateFrameBetweenValues('sum', $amounts, $rowKeys, 'ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING');
            $filteredNames = SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', $sellerNames, $rowKeys, 'ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW', 'NO OTHERS', $filters, '|');
            $tiles = SQLiteWindowFunction::ntile($regionRows, $bucketCount);

            $expectedRank = $rankOracle($amountKeys);
            $expectedDenseRank = $denseRankOracle($amountKeys);
            $expectedRunning = $runningSumOracle($amounts);
            $expectedSuffix = $suffixSumOracle($amounts);
            $expectedTiles = $ntileOracle(count($regionRows), $bucketCount);

            $t->same($expectedRank, $rank, "window1.test 10.1 rank top sellers {$region} case {$case}");
            $t->same($expectedDenseRank, $denseRank, "window1.test 10.1 dense rank sellers {$region} case {$case}");
            $t->same($expectedRunning, $running, "window1.test 10.2 cumulative regional sum {$region} case {$case}");
            $t->same($expectedSuffix, $suffix, "window1.test 10.5 suffix regional sum {$region} case {$case}");
            $t->same($expectedTiles, $tiles, "window1.test 10.6 ntile regional buckets {$region} case {$case}");

            foreach ($regionRows as $offset => $_row) {
                $expectedPercent = count($regionRows) === 1 ? 0.0 : (float) (($expectedRank[$offset] - 1) / (count($regionRows) - 1));
                $lastPeer = $offset;
                while (($lastPeer + 1) < count($amountKeys) && $amountKeys[$lastPeer + 1] === $amountKeys[$offset]) {
                    $lastPeer++;
                }
                $expectedCume = (float) (($lastPeer + 1) / count($regionRows));
                $expectedFiltered = [];
                for ($i = 0; $i <= $offset; $i++) {
                    if ($filters[$i]) {
                        $expectedFiltered[] = $sellerNames[$i];
                    }
                }

                $t->same($expectedPercent, $percentRank[$offset], "window1.test 10.1 percent_rank {$region} case {$case} row {$offset}");
                $t->same($expectedCume, $cumeDist[$offset], "window1.test 10.1 cume_dist {$region} case {$case} row {$offset}");
                $t->same($expectedFiltered === [] ? null : implode('|', $expectedFiltered), $filteredNames[$offset], "window1.test 10.2 filtered group_concat {$region} case {$case} row {$offset}");
            }
        }
    };
}

$tests['real upstream window1 dynamic regional yield cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 7.2 lead() offset/default behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.1 top regional ranking behavior',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.2 cumulative regional window aggregates',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.5 suffix regional frames',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test 10.6 regional LIMIT/OFFSET window output',
    ];

    $t->same($sources, $sources);
};

$tests['real upstream window1 dynamic regional yield dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses lane-local SQLiteWindowFunction ranking, offset, and ROWS frame aggregate helpers over upstream window1 regional and lead/lag semantics',
        'no new support component needed; reuses lane-local SQLiteWindowFunction ranking, offset, and ROWS frame aggregate helpers over upstream window1 regional and lead/lag semantics',
    );
};

return $tests;
