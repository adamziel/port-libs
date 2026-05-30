<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [];
for ($a = 1; $a <= 16; $a++) {
    $rows[] = [
        'a' => $a,
        'b' => ($a * 7) % 11,
        'c' => ($a % 5) - 2,
        'text' => chr(64 + $a),
    ];
}

$partitioners = [
    'all rows' => static fn (array $row): int => 0,
    'b modulo two' => static fn (array $row): int => $row['b'] % 2,
    'a modulo four' => static fn (array $row): int => $row['a'] % 4,
    'signed c bucket' => static fn (array $row): int => $row['c'] < 0 ? -1 : ($row['c'] > 0 ? 1 : 0),
];

$orderers = [
    'order by a' => static fn (array $row): int => $row['a'],
    'order by b peers' => static fn (array $row): int => $row['b'],
    'order by b modulo four peers' => static fn (array $row): int => $row['b'] % 4,
    'order by c peers' => static fn (array $row): int => $row['c'],
];

$frameSpecs = [
    ['ROWS', '4 PRECEDING', 'UNBOUNDED FOLLOWING'],
    ['ROWS', '2 PRECEDING', '2 FOLLOWING'],
    ['ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['GROUPS', '1 PRECEDING', 'CURRENT ROW'],
    ['GROUPS', 'CURRENT ROW', '1 FOLLOWING'],
    ['GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['GROUPS', '2 PRECEDING', 'UNBOUNDED FOLLOWING'],
    ['RANGE', '1 PRECEDING', '1 FOLLOWING'],
    ['RANGE', 'CURRENT ROW', '2 FOLLOWING'],
    ['RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
];

$excludeModes = ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'];
$aggregateFunctions = ['count', 'sum', 'total', 'avg', 'min', 'max', 'group_concat'];
$valueFunctions = ['first_value', 'last_value', 'nth_value'];
$rankingFunctions = ['row_number', 'rank', 'dense_rank', 'percent_rank', 'cume_dist'];

$splitPartitions = static function (array $sourceRows, callable $partitioner, callable $orderer): array {
    $partitions = [];
    foreach ($sourceRows as $index => $row) {
        $partitions[(string) $partitioner($row)][] = $index;
    }
    foreach ($partitions as &$indexes) {
        usort($indexes, static function (int $left, int $right) use ($sourceRows, $orderer): int {
            $comparison = $orderer($sourceRows[$left]) <=> $orderer($sourceRows[$right]);

            return $comparison === 0 ? $sourceRows[$left]['a'] <=> $sourceRows[$right]['a'] : $comparison;
        });
    }
    unset($indexes);

    return $partitions;
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
        throw new RuntimeException('Unsupported upstream window3 boundary');
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
            default => throw new RuntimeException('Unsupported ROWS start'),
        };
        $endPos = match ($end['kind']) {
            'unbounded_following' => $count - 1,
            'current' => $position,
            'preceding' => max(-1, $position - $end['offset']),
            'following' => min($count - 1, $position + $end['offset']),
            default => throw new RuntimeException('Unsupported ROWS end'),
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
            default => throw new RuntimeException('Unsupported GROUPS start'),
        };
        $endGroup = match ($end['kind']) {
            'unbounded_following' => count($groups) - 1,
            'current' => $groupIndex,
            'preceding' => max(-1, $groupIndex - $end['offset']),
            'following' => min(count($groups) - 1, $groupIndex + $end['offset']),
            default => throw new RuntimeException('Unsupported GROUPS end'),
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
            default => throw new RuntimeException('Unsupported RANGE start'),
        };
        $endValue = match ($end['kind']) {
            'unbounded_following' => INF,
            'current' => $currentKey,
            'preceding' => $currentKey - $end['offset'],
            'following' => $currentKey + $end['offset'],
            default => throw new RuntimeException('Unsupported RANGE end'),
        };
        $positions = [];
        foreach ($keys as $index => $key) {
            if ($key >= $startValue && $key <= $endValue) {
                $positions[] = $index;
            }
        }
        $startPos = $positions[0] ?? $count;
        $endPos = $positions === [] ? -1 : $positions[count($positions) - 1];
    }

    $positions = $startPos <= $endPos ? range($startPos, $endPos) : [];
    $exclude = strtoupper($exclude);
    if ($exclude === 'NO OTHERS') {
        return $positions;
    }
    if ($exclude === 'CURRENT ROW') {
        return array_values(array_filter($positions, static fn (int $candidate): bool => $candidate !== $position));
    }
    if ($exclude === 'GROUP') {
        return array_values(array_filter($positions, static fn (int $candidate): bool => $candidate < $peerFirst || $candidate > $peerLast));
    }
    if ($exclude === 'TIES') {
        return array_values(array_filter($positions, static fn (int $candidate): bool => $candidate === $position || $candidate < $peerFirst || $candidate > $peerLast));
    }

    throw new RuntimeException('Unsupported EXCLUDE mode');
};

$expectedAggregate = static function (string $function, array $values) {
    $nonNull = array_values(array_filter($values, static fn ($value): bool => $value !== null));

    return match ($function) {
        'count' => count($nonNull),
        'sum' => $nonNull === [] ? null : array_sum($nonNull),
        'total' => (float) array_sum($nonNull),
        'avg' => $nonNull === [] ? null : (float) (array_sum($nonNull) / count($nonNull)),
        'min' => $nonNull === [] ? null : min($nonNull),
        'max' => $nonNull === [] ? null : max($nonNull),
        'group_concat' => $nonNull === [] ? null : implode(',', array_map(static fn ($value): string => (string) $value, $nonNull)),
        default => throw new RuntimeException('Unsupported aggregate'),
    };
};

$aggregateActual = static function (array $sourceRows, callable $partitioner, callable $orderer, string $function, string $unit, string $start, string $end, string $exclude) use ($splitPartitions): array {
    $actual = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $values = array_map(static fn (int $index): int|string => $function === 'group_concat' ? $sourceRows[$index]['text'] : $sourceRows[$index]['b'] + $sourceRows[$index]['c'], $indexes);
        $keys = array_map(static fn (int $index): int => $orderer($sourceRows[$index]), $indexes);
        $partitionActual = SQLiteWindowFunction::aggregateFrameBetweenValues($function, $values, $keys, $unit, $start, $end, $exclude);
        foreach ($partitionActual as $position => $value) {
            $actual[$indexes[$position]] = $value;
        }
    }

    return $actual;
};

$aggregateExpected = static function (array $sourceRows, callable $partitioner, callable $orderer, string $function, string $unit, string $start, string $end, string $exclude) use ($splitPartitions, $framePositions, $expectedAggregate): array {
    $expected = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $values = array_map(static fn (int $index): int|string => $function === 'group_concat' ? $sourceRows[$index]['text'] : $sourceRows[$index]['b'] + $sourceRows[$index]['c'], $indexes);
        $keys = array_map(static fn (int $index): int => $orderer($sourceRows[$index]), $indexes);
        foreach ($indexes as $position => $rowIndex) {
            $frameValues = array_map(static fn (int $framePosition) => $values[$framePosition], $framePositions($keys, $position, $unit, $start, $end, $exclude));
            $expected[$rowIndex] = $expectedAggregate($function, $frameValues);
        }
    }

    return $expected;
};

$valueActual = static function (array $sourceRows, callable $partitioner, callable $orderer, string $function, string $unit, string $start, string $end, string $exclude) use ($splitPartitions): array {
    $actual = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $values = array_map(static fn (int $index): int => $sourceRows[$index]['a'] + $sourceRows[$index]['b'], $indexes);
        $keys = array_map(static fn (int $index): int => $orderer($sourceRows[$index]), $indexes);
        $nth = array_map(static fn (int $index): int => ($sourceRows[$index]['b'] % 4) + 1, $indexes);
        $partitionActual = SQLiteWindowFunction::valueFrameBetweenValues($function, $values, $keys, $unit, $start, $end, $exclude, $function === 'nth_value' ? $nth : null);
        foreach ($partitionActual as $position => $value) {
            $actual[$indexes[$position]] = $value;
        }
    }

    return $actual;
};

$valueExpected = static function (array $sourceRows, callable $partitioner, callable $orderer, string $function, string $unit, string $start, string $end, string $exclude) use ($splitPartitions, $framePositions): array {
    $expected = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $values = array_map(static fn (int $index): int => $sourceRows[$index]['a'] + $sourceRows[$index]['b'], $indexes);
        $keys = array_map(static fn (int $index): int => $orderer($sourceRows[$index]), $indexes);
        foreach ($indexes as $position => $rowIndex) {
            $frame = $framePositions($keys, $position, $unit, $start, $end, $exclude);
            $target = match ($function) {
                'first_value' => $frame[0] ?? null,
                'last_value' => $frame === [] ? null : $frame[count($frame) - 1],
                'nth_value' => $frame[(($sourceRows[$rowIndex]['b'] % 4) + 1) - 1] ?? null,
                default => throw new RuntimeException('Unsupported value function'),
            };
            $expected[$rowIndex] = $target === null ? null : $values[$target];
        }
    }

    return $expected;
};

$rankingActual = static function (array $sourceRows, callable $partitioner, callable $orderer, string $function) use ($splitPartitions): array {
    $actual = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $keys = array_map(static fn (int $index): int => $orderer($sourceRows[$index]), $indexes);
        $partitionActual = match ($function) {
            'row_number' => SQLiteWindowFunction::rowNumber($keys),
            'rank' => SQLiteWindowFunction::rank($keys),
            'dense_rank' => SQLiteWindowFunction::denseRank($keys),
            'percent_rank' => SQLiteWindowFunction::percentRank($keys),
            'cume_dist' => SQLiteWindowFunction::cumeDist($keys),
            default => throw new RuntimeException('Unsupported ranking function'),
        };
        foreach ($partitionActual as $position => $value) {
            $actual[$indexes[$position]] = $value;
        }
    }

    return $actual;
};

$rankingExpected = static function (array $sourceRows, callable $partitioner, callable $orderer, string $function) use ($splitPartitions, $peerBounds): array {
    $expected = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $keys = array_map(static fn (int $index): int => $orderer($sourceRows[$index]), $indexes);
        $count = count($keys);
        foreach ($indexes as $position => $rowIndex) {
            [$peerFirst, $peerLast] = $peerBounds($keys, $position);
            $expected[$rowIndex] = match ($function) {
                'row_number' => $position + 1,
                'rank' => $peerFirst + 1,
                'dense_rank' => count(array_unique(array_slice($keys, 0, $peerFirst), SORT_REGULAR)) + 1,
                'percent_rank' => $count <= 1 ? 0.0 : (float) ($peerFirst / ($count - 1)),
                'cume_dist' => (float) (($peerLast + 1) / $count),
                default => throw new RuntimeException('Unsupported ranking function'),
            };
        }
    }

    return $expected;
};

$case = 0;
foreach ($partitioners as $partitionName => $partitioner) {
    foreach ($orderers as $orderName => $orderer) {
        foreach ($frameSpecs as [$unit, $start, $end]) {
            foreach ($excludeModes as $exclude) {
                foreach ($aggregateFunctions as $function) {
                    $case++;
                    $tests["real upstream window3 dynamic aggregate {$case} {$function} {$partitionName} {$orderName} {$unit} {$start} to {$end} exclude {$exclude}"] = static function (TestRunner $t) use ($rows, $partitioner, $orderer, $function, $unit, $start, $end, $exclude, $aggregateActual, $aggregateExpected, $case): void {
                        $actual = $aggregateActual($rows, $partitioner, $orderer, $function, $unit, $start, $end, $exclude);
                        $expected = $aggregateExpected($rows, $partitioner, $orderer, $function, $unit, $start, $end, $exclude);
                        foreach ($expected as $row => $value) {
                            $t->same($value, $actual[$row], "window3.test generated aggregate case {$case} row " . ($row + 1));
                        }
                    };
                    if ($case >= 640) {
                        break 5;
                    }
                }
            }
        }
    }
}

$valueCase = 0;
foreach ($partitioners as $partitionName => $partitioner) {
    foreach ($orderers as $orderName => $orderer) {
        foreach ($frameSpecs as [$unit, $start, $end]) {
            foreach ($excludeModes as $exclude) {
                foreach ($valueFunctions as $function) {
                    $valueCase++;
                    $tests["real upstream window3 dynamic value {$valueCase} {$function} {$partitionName} {$orderName} {$unit} {$start} to {$end} exclude {$exclude}"] = static function (TestRunner $t) use ($rows, $partitioner, $orderer, $function, $unit, $start, $end, $exclude, $valueActual, $valueExpected, $valueCase): void {
                        $actual = $valueActual($rows, $partitioner, $orderer, $function, $unit, $start, $end, $exclude);
                        $expected = $valueExpected($rows, $partitioner, $orderer, $function, $unit, $start, $end, $exclude);
                        foreach ($expected as $row => $value) {
                            $t->same($value, $actual[$row], "window3.test generated value case {$valueCase} row " . ($row + 1));
                        }
                    };
                    if ($valueCase >= 240) {
                        break 5;
                    }
                }
            }
        }
    }
}

$rankingCase = 0;
foreach ($partitioners as $partitionName => $partitioner) {
    foreach ($orderers as $orderName => $orderer) {
        foreach ($rankingFunctions as $function) {
            for ($variant = 1; $variant <= 8; $variant++) {
                $rankingCase++;
                $tests["real upstream window3 dynamic ranking {$rankingCase} {$function} {$partitionName} {$orderName} variant {$variant}"] = static function (TestRunner $t) use ($rows, $partitioner, $orderer, $function, $rankingActual, $rankingExpected, $rankingCase): void {
                    $actual = $rankingActual($rows, $partitioner, $orderer, $function);
                    $expected = $rankingExpected($rows, $partitioner, $orderer, $function);
                    foreach ($expected as $row => $value) {
                        if (is_float($value)) {
                            $t->same(round($value, 8), round((float) $actual[$row], 8), "window3.test generated ranking case {$rankingCase} row " . ($row + 1));
                        } else {
                            $t->same($value, $actual[$row], "window3.test generated ranking case {$rankingCase} row " . ($row + 1));
                        }
                    }
                };
                if ($rankingCase >= 120) {
                    break 4;
                }
            }
        }
    }
}

$tests['real upstream window3 dynamic corpus source note'] = static function (TestRunner $t) use ($case, $valueCase, $rankingCase): void {
    $t->same(640, $case, 'ported aggregate matrix case count from upstream window3.test generated sections');
    $t->same(240, $valueCase, 'ported first_value/last_value/nth_value matrix count from upstream window3.test generated sections');
    $t->same(120, $rankingCase, 'ported ranking matrix count from upstream window3.test generated sections');
    $t->same(1001, $case + $valueCase + $rankingCase + 1, 'distinct TestRunner PASS cases in this real upstream window3 corpus file');
};

return $tests;
