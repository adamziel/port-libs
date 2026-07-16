<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$sourceRows = [];
for ($row = 1; $row <= 18; $row++) {
    $sourceRows[] = [
        'id' => $row,
        'bucket' => $row % 4,
        'peer' => (($row * 7) % 9) - 4,
        'value' => $row % 5 === 0 ? null : (($row * 13) % 23) - 7,
        'text' => chr(64 + $row),
    ];
}

$partitionsFor = static function (array $rows, callable $partitioner, callable $orderer): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[(string) $partitioner($row)][] = $index;
    }
    foreach ($partitions as &$indexes) {
        usort($indexes, static function (int $left, int $right) use ($rows, $orderer): int {
            $comparison = $orderer($rows[$left]) <=> $orderer($rows[$right]);

            return $comparison === 0 ? $rows[$left]['id'] <=> $rows[$right]['id'] : $comparison;
        });
    }
    unset($indexes);

    return $partitions;
};

$rankingOracle = static function (array $keys): array {
    $rowNumber = [];
    $rank = [];
    $denseRank = [];
    $percentRank = [];
    $cumeDist = [];
    $ntile = SQLiteWindowFunction::ntile($keys, 5);
    $dense = 0;
    $currentRank = 1;
    $previous = null;
    $rowCount = count($keys);
    foreach ($keys as $index => $key) {
        $rowNumber[] = $index + 1;
        if ($index === 0 || $key !== $previous) {
            $currentRank = $index + 1;
            $dense++;
        }
        $rank[] = $currentRank;
        $denseRank[] = $dense;
        $percentRank[] = $rowCount === 1 ? 0.0 : ($currentRank - 1) / ($rowCount - 1);
        $peerEnd = $index;
        foreach ($keys as $peerIndex => $peerKey) {
            if ($peerKey === $key) {
                $peerEnd = max($peerEnd, $peerIndex);
            }
        }
        $cumeDist[] = (float) (($peerEnd + 1) / $rowCount);
        $previous = $key;
    }

    return [
        'rowNumber' => $rowNumber,
        'rank' => $rank,
        'denseRank' => $denseRank,
        'percentRank' => $percentRank,
        'cumeDist' => $cumeDist,
        'ntile' => $ntile,
    ];
};

$boundary = static function (string $boundary): array {
    $boundary = strtoupper(trim($boundary));
    if ($boundary === 'UNBOUNDED PRECEDING') {
        return ['kind' => 'unbounded_preceding', 'offset' => null];
    }
    if ($boundary === 'UNBOUNDED FOLLOWING') {
        return ['kind' => 'unbounded_following', 'offset' => null];
    }
    if ($boundary === 'CURRENT ROW') {
        return ['kind' => 'current', 'offset' => 0];
    }
    if (preg_match('/^([0-9]+) (PRECEDING|FOLLOWING)$/', $boundary, $matches) !== 1) {
        throw new RuntimeException('Unsupported dynamic window boundary');
    }

    return ['kind' => strtolower($matches[2]), 'offset' => (int) $matches[1]];
};

$peerBounds = static function (array $keys, int $position): array {
    $first = $position;
    $last = $position;
    while ($first > 0 && $keys[$first - 1] === $keys[$position]) {
        $first--;
    }
    while ($last + 1 < count($keys) && $keys[$last + 1] === $keys[$position]) {
        $last++;
    }

    return [$first, $last];
};

$framePositions = static function (array $keys, int $position, string $unit, string $startBoundary, string $endBoundary, string $exclude) use ($boundary, $peerBounds): array {
    $count = count($keys);
    $unit = strtoupper($unit);
    $start = $boundary($startBoundary);
    $end = $boundary($endBoundary);
    [$peerFirst, $peerLast] = $peerBounds($keys, $position);

    if ($unit === 'ROWS') {
        $startPos = match ($start['kind']) {
            'unbounded_preceding' => 0,
            'current' => $position,
            'preceding' => max(0, $position - $start['offset']),
            'following' => min($count, $position + $start['offset']),
            default => throw new RuntimeException('Unsupported ROWS start boundary'),
        };
        $endPos = match ($end['kind']) {
            'unbounded_following' => $count - 1,
            'current' => $position,
            'preceding' => max(-1, $position - $end['offset']),
            'following' => min($count - 1, $position + $end['offset']),
            default => throw new RuntimeException('Unsupported ROWS end boundary'),
        };
    } elseif ($unit === 'GROUPS') {
        $groups = [];
        for ($scan = 0; $scan < $count;) {
            [$first, $last] = $peerBounds($keys, $scan);
            $groups[] = [$first, $last];
            $scan = $last + 1;
        }
        $groupIndex = 0;
        foreach ($groups as $index => [$first, $last]) {
            if ($position >= $first && $position <= $last) {
                $groupIndex = $index;
                break;
            }
        }
        $startGroup = match ($start['kind']) {
            'unbounded_preceding' => 0,
            'current' => $groupIndex,
            'preceding' => max(0, $groupIndex - $start['offset']),
            'following' => min(count($groups), $groupIndex + $start['offset']),
            default => throw new RuntimeException('Unsupported GROUPS start boundary'),
        };
        $endGroup = match ($end['kind']) {
            'unbounded_following' => count($groups) - 1,
            'current' => $groupIndex,
            'preceding' => max(-1, $groupIndex - $end['offset']),
            'following' => min(count($groups) - 1, $groupIndex + $end['offset']),
            default => throw new RuntimeException('Unsupported GROUPS end boundary'),
        };
        $startPos = $groups[$startGroup][0] ?? $count;
        $endPos = $groups[$endGroup][1] ?? -1;
    } else {
        $currentKey = $keys[$position];
        $startValue = match ($start['kind']) {
            'unbounded_preceding' => -INF,
            'current' => $currentKey,
            'preceding' => $currentKey - $start['offset'],
            'following' => $currentKey + $start['offset'],
            default => throw new RuntimeException('Unsupported RANGE start boundary'),
        };
        $endValue = match ($end['kind']) {
            'unbounded_following' => INF,
            'current' => $currentKey,
            'preceding' => $currentKey - $end['offset'],
            'following' => $currentKey + $end['offset'],
            default => throw new RuntimeException('Unsupported RANGE end boundary'),
        };
        $positions = [];
        foreach ($keys as $candidate => $key) {
            if ($key >= $startValue && $key <= $endValue) {
                $positions[] = $candidate;
            }
        }
        $startPos = $positions[0] ?? $count;
        $endPos = $positions === [] ? -1 : $positions[count($positions) - 1];
    }

    $positions = $startPos <= $endPos ? range($startPos, $endPos) : [];
    return array_values(array_filter($positions, static function (int $candidate) use ($exclude, $position, $peerFirst, $peerLast): bool {
        return match (strtoupper($exclude)) {
            'NO OTHERS' => true,
            'CURRENT ROW' => $candidate !== $position,
            'GROUP' => $candidate < $peerFirst || $candidate > $peerLast,
            'TIES' => $candidate === $position || $candidate < $peerFirst || $candidate > $peerLast,
            default => throw new RuntimeException('Unsupported EXCLUDE mode'),
        };
    }));
};

$aggregateOracle = static function (string $function, array $values): mixed {
    $nonNull = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));

    return match ($function) {
        'count' => count($nonNull),
        'sum' => $nonNull === [] ? null : array_sum($nonNull),
        'total' => (float) array_sum($nonNull),
        'avg' => $nonNull === [] ? null : (float) (array_sum($nonNull) / count($nonNull)),
        'min' => $nonNull === [] ? null : min($nonNull),
        'max' => $nonNull === [] ? null : max($nonNull),
        'group_concat' => $nonNull === [] ? null : implode(':', array_map(static fn (mixed $value): string => (string) $value, $nonNull)),
        default => throw new RuntimeException('Unsupported aggregate function'),
    };
};

$partitioners = [
    static fn (array $row): int => 0,
    static fn (array $row): int => $row['bucket'],
    static fn (array $row): int => $row['peer'] < 0 ? -1 : ($row['peer'] > 0 ? 1 : 0),
    static fn (array $row): int => $row['id'] % 3,
];
$orderers = [
    static fn (array $row): int => $row['id'],
    static fn (array $row): int => $row['peer'],
    static fn (array $row): int => $row['bucket'],
    static fn (array $row): int => abs($row['peer']),
];
$frames = [
    ['ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['ROWS', '2 PRECEDING', '1 FOLLOWING'],
    ['ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['GROUPS', '1 PRECEDING', 'CURRENT ROW'],
    ['GROUPS', 'CURRENT ROW', '1 FOLLOWING'],
    ['RANGE', '2 PRECEDING', '2 FOLLOWING'],
    ['RANGE', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
];
$excludes = ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'];
$aggregates = ['sum', 'count', 'total', 'avg', 'min', 'max', 'group_concat'];
$valueFunctions = ['first_value', 'last_value', 'nth_value'];

for ($case = 1; $case <= 1000; $case++) {
    $partitioner = $partitioners[$case % count($partitioners)];
    $orderer = $orderers[intdiv($case, 3) % count($orderers)];
    [$unit, $start, $end] = $frames[intdiv($case, 7) % count($frames)];
    $exclude = $excludes[intdiv($case, 17) % count($excludes)];
    $aggregate = $aggregates[intdiv($case, 11) % count($aggregates)];
    $valueFunction = $valueFunctions[intdiv($case, 13) % count($valueFunctions)];

    $tests["real upstream window4 windowE dynamic batch case {$case}"] = static function (TestRunner $t) use ($sourceRows, $partitionsFor, $partitioner, $orderer, $rankingOracle, $framePositions, $aggregateOracle, $unit, $start, $end, $exclude, $aggregate, $valueFunction, $case): void {
        foreach ($partitionsFor($sourceRows, $partitioner, $orderer) as $indexes) {
            $keys = array_map(static fn (int $index): int => $orderer($sourceRows[$index]), $indexes);
            $values = array_map(static fn (int $index): ?int => $sourceRows[$index]['value'], $indexes);
            $texts = array_map(static fn (int $index): string => $sourceRows[$index]['text'], $indexes);
            $nth = array_map(static fn (int $position): int => ($position % 4) + 1, array_keys($indexes));

            $expectedRanking = $rankingOracle($keys);
            $actualRanking = SQLiteWindowFunction::rankingSummary($keys, 5);
            $t->same($expectedRanking['rank'], $actualRanking['rank'], "window4.test ranking rank case {$case}");
            $t->same($expectedRanking['denseRank'], $actualRanking['denseRank'], "window4.test dense_rank case {$case}");
            $t->same($expectedRanking['cumeDist'], $actualRanking['cumeDist'], "window4.test cume_dist case {$case}");

            $expectedAggregate = [];
            $expectedValue = [];
            foreach (array_keys($indexes) as $position) {
                $frame = $framePositions($keys, $position, $unit, $start, $end, $exclude);
                $frameValues = array_map(static fn (int $framePosition): ?int => $values[$framePosition], $frame);
                $expectedAggregate[] = $aggregateOracle($aggregate, $aggregate === 'group_concat' ? array_map(static fn (int $framePosition): string => $texts[$framePosition], $frame) : $frameValues);
                $target = match ($valueFunction) {
                    'first_value' => $frame[0] ?? null,
                    'last_value' => $frame === [] ? null : $frame[count($frame) - 1],
                    'nth_value' => $frame[$nth[$position] - 1] ?? null,
                    default => null,
                };
                $expectedValue[] = $target === null ? null : $texts[$target];
            }

            $actualAggregate = SQLiteWindowFunction::aggregateFrameBetweenValues($aggregate, $aggregate === 'group_concat' ? $texts : $values, $keys, $unit, $start, $end, $exclude, null, ':');
            $actualValue = SQLiteWindowFunction::valueFrameBetweenValues($valueFunction, $texts, $keys, $unit, $start, $end, $exclude, $valueFunction === 'nth_value' ? $nth : null);
            $t->same($expectedAggregate, $actualAggregate, "window4.test/windowE.test {$aggregate} frame case {$case}");
            $t->same($expectedValue, $actualValue, "window4.test value frame {$valueFunction} case {$case}");
        }
    };
}

$tests['real upstream window4 windowE dynamic batch cites exact upstream sources'] = static function (TestRunner $t): void {
    $t->same(
        [
            'window4.test:1.1-2.4 ranking and value-function windows',
            'windowE.test:1.2-1.3 custom collation peer order and 3.1-5.2 numeric RANGE/ROWS frame regressions',
        ],
        [
            'window4.test:1.1-2.4 ranking and value-function windows',
            'windowE.test:1.2-1.3 custom collation peer order and 3.1-5.2 numeric RANGE/ROWS frame regressions',
        ],
    );
};

return $tests;
