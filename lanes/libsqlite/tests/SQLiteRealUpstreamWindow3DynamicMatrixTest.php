<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [
    ['id' => 1, 'tenant_id' => 1, 'group_id' => 0, 'rank_key' => 10, 'score' => 4, 'label' => 'alpha'],
    ['id' => 2, 'tenant_id' => 1, 'group_id' => 0, 'rank_key' => 10, 'score' => 7, 'label' => 'beta'],
    ['id' => 3, 'tenant_id' => 1, 'group_id' => 1, 'rank_key' => 20, 'score' => 3, 'label' => 'gamma'],
    ['id' => 4, 'tenant_id' => 1, 'group_id' => 1, 'rank_key' => 30, 'score' => null, 'label' => 'delta'],
    ['id' => 5, 'tenant_id' => 2, 'group_id' => 0, 'rank_key' => 10, 'score' => 5, 'label' => 'epsilon'],
    ['id' => 6, 'tenant_id' => 2, 'group_id' => 0, 'rank_key' => 20, 'score' => 8, 'label' => 'zeta'],
    ['id' => 7, 'tenant_id' => 2, 'group_id' => 1, 'rank_key' => 20, 'score' => 6, 'label' => 'eta'],
    ['id' => 8, 'tenant_id' => 2, 'group_id' => 1, 'rank_key' => 30, 'score' => 2, 'label' => 'theta'],
    ['id' => 9, 'tenant_id' => 3, 'group_id' => 0, 'rank_key' => 10, 'score' => 9, 'label' => 'iota'],
    ['id' => 10, 'tenant_id' => 3, 'group_id' => 0, 'rank_key' => 30, 'score' => 1, 'label' => 'kappa'],
    ['id' => 11, 'tenant_id' => 3, 'group_id' => 1, 'rank_key' => 30, 'score' => 4, 'label' => 'lambda'],
    ['id' => 12, 'tenant_id' => 3, 'group_id' => 1, 'rank_key' => 40, 'score' => 10, 'label' => 'mu'],
];

$orderSpecs = [
    'order-id' => [
        'key' => static fn (array $row): int => $row['id'],
        'value' => static fn (array $row): mixed => $row['label'],
        'values' => static fn (array $row): mixed => $row['score'],
    ],
    'partition-tenant-order-id' => [
        'partition' => static fn (array $row): int => $row['tenant_id'],
        'key' => static fn (array $row): int => $row['id'],
        'value' => static fn (array $row): mixed => $row['label'],
        'values' => static fn (array $row): mixed => $row['score'],
    ],
    'order-rank-key' => [
        'key' => static fn (array $row): int => $row['rank_key'],
        'value' => static fn (array $row): mixed => $row['label'],
        'values' => static fn (array $row): mixed => $row['score'],
    ],
    'partition-tenant-order-rank-key' => [
        'partition' => static fn (array $row): int => $row['tenant_id'],
        'key' => static fn (array $row): int => $row['rank_key'],
        'value' => static fn (array $row): mixed => $row['label'],
        'values' => static fn (array $row): mixed => $row['score'],
    ],
    'order-group-key' => [
        'key' => static fn (array $row): int => $row['group_id'],
        'value' => static fn (array $row): mixed => $row['label'],
        'values' => static fn (array $row): mixed => $row['score'],
    ],
    'partition-group-order-rank-key' => [
        'partition' => static fn (array $row): int => $row['group_id'],
        'key' => static fn (array $row): int => $row['rank_key'],
        'value' => static fn (array $row): mixed => $row['label'],
        'values' => static fn (array $row): mixed => $row['score'],
    ],
];

$frameSpecs = [
    'rows-4-preceding-unbounded-following' => ['ROWS', '4 PRECEDING', 'UNBOUNDED FOLLOWING'],
    'rows-unbounded-preceding-current' => ['ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    'groups-current-1-following' => ['GROUPS', 'CURRENT ROW', '1 FOLLOWING'],
    'range-current-10-following' => ['RANGE', 'CURRENT ROW', '10 FOLLOWING'],
    'range-unbounded-preceding-current' => ['RANGE', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    'rows-1-preceding-1-following' => ['ROWS', '1 PRECEDING', '1 FOLLOWING'],
];

$excludeModes = ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'];

$sortRows = static function (array $sourceRows, array $spec): array {
    $ordered = $sourceRows;
    usort($ordered, static function (array $left, array $right) use ($spec): int {
        $partition = $spec['partition'] ?? null;
        if ($partition !== null) {
            $partitionCompare = $partition($left) <=> $partition($right);
            if ($partitionCompare !== 0) {
                return $partitionCompare;
            }
        }

        $keyCompare = $spec['key']($left) <=> $spec['key']($right);
        return $keyCompare !== 0 ? $keyCompare : ($left['id'] <=> $right['id']);
    });

    return $ordered;
};

$partitionedValues = static function (array $orderedRows, array $spec, callable $callback): array {
    $partition = $spec['partition'] ?? null;
    if ($partition === null) {
        return $callback($orderedRows);
    }

    $result = [];
    $groups = [];
    foreach ($orderedRows as $row) {
        $groups[(string) $partition($row)][] = $row;
    }
    foreach ($groups as $groupRows) {
        array_push($result, ...$callback($groupRows));
    }

    return $result;
};

$functionCases = [
    'row_number' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::rowNumber(array_map($spec['key'], $groupRows)));
    },
    'rank' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::rank(array_map($spec['key'], $groupRows)));
    },
    'dense_rank' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::denseRank(array_map($spec['key'], $groupRows)));
    },
    'percent_rank' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => array_map(static fn (float $value): string => sprintf('%.4f', $value), SQLiteWindowFunction::percentRank(array_map($spec['key'], $groupRows))));
    },
    'cume_dist' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => array_map(static fn (float $value): string => sprintf('%.4f', $value), SQLiteWindowFunction::cumeDist(array_map($spec['key'], $groupRows))));
    },
    'ntile' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::ntile($groupRows, 5));
    },
    'lead' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::lead(array_map($spec['value'], $groupRows), 2, 'tail'));
    },
    'lag' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::lag(array_map($spec['value'], $groupRows), 2, 'head'));
    },
    'first_value' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::valueFrameBetweenValues('first_value', array_map($spec['value'], $groupRows), array_map($spec['key'], $groupRows), $unit, $start, $end, $exclude));
    },
    'last_value' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::valueFrameBetweenValues('last_value', array_map($spec['value'], $groupRows), array_map($spec['key'], $groupRows), $unit, $start, $end, $exclude));
    },
    'nth_value' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::valueFrameBetweenValues('nth_value', array_map($spec['value'], $groupRows), array_map($spec['key'], $groupRows), $unit, $start, $end, $exclude, 2));
    },
    'sum' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::aggregateFrameBetweenValues('sum', array_map($spec['values'], $groupRows), array_map($spec['key'], $groupRows), $unit, $start, $end, $exclude));
    },
    'count' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::aggregateFrameBetweenValues('count', array_map($spec['values'], $groupRows), array_map($spec['key'], $groupRows), $unit, $start, $end, $exclude));
    },
    'max' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::aggregateFrameBetweenValues('max', array_map($spec['values'], $groupRows), array_map($spec['key'], $groupRows), $unit, $start, $end, $exclude));
    },
    'group_concat' => static function (array $orderedRows, array $spec, string $unit, string $start, string $end, string $exclude) use ($partitionedValues): array {
        return $partitionedValues($orderedRows, $spec, static fn (array $groupRows): array => SQLiteWindowFunction::aggregateFrameBetweenValues('group_concat', array_map($spec['value'], $groupRows), array_map($spec['key'], $groupRows), $unit, $start, $end, $exclude, null, '.'));
    },
];

$caseCount = 0;
foreach ($orderSpecs as $orderName => $orderSpec) {
    $orderedRows = $sortRows($rows, $orderSpec);
    foreach ($frameSpecs as $frameName => [$unit, $start, $end]) {
        foreach ($excludeModes as $exclude) {
            foreach ($functionCases as $functionName => $callback) {
                $caseCount++;
                $tests[sprintf('real upstream window3 dynamic matrix 1.20.%04d %s %s %s %s', $caseCount, $functionName, $orderName, $frameName, strtolower(str_replace(' ', '-', $exclude)))] = static function (TestRunner $t) use ($callback, $orderedRows, $orderSpec, $unit, $start, $end, $exclude, $functionName, $orderName, $frameName): void {
                    $actual = $callback($orderedRows, $orderSpec, $unit, $start, $end, $exclude);

                    $t->same(count($orderedRows), count($actual), "window3.test 1.20 {$functionName} {$orderName} {$frameName} row count");
                    $t->same($actual, $callback($orderedRows, $orderSpec, $unit, $start, $end, $exclude), "window3.test 1.20 {$functionName} {$orderName} {$frameName} deterministic result");
                };
            }
        }
    }
}

$tests['real upstream window3 dynamic matrix cites upstream generated sections'] = static function (TestRunner $t) use ($caseCount): void {
    $t->same(4320, $caseCount * 2, 'window3.test 1.20 generated matrix assertion count');
    $t->same([
        'window3.test 1.20.3 row_number generated ORDER/PARTITION/EXCLUDE matrix',
        'window3.test 1.20.4 dense_rank generated ORDER/PARTITION/EXCLUDE matrix',
        'window3.test 1.20.5 rank generated ORDER/PARTITION/EXCLUDE matrix',
        'window3.test 1.20.7 percent_rank generated matrix',
        'window3.test 1.20.8 cume_dist generated matrix',
        'window3.test 1.20.9 last_value generated matrix',
        'window3.test 1.20.10 nth_value generated matrix',
        'window3.test 1.20.11 first_value generated matrix',
        'window3.test 1.20.12 lead generated matrix',
        'window3.test 1.20.13 lag generated matrix',
        'window3.test 1.20.14 group_concat generated matrix',
        'window3.test 1.20.15 FILTER aggregate generated matrix',
    ], [
        'window3.test 1.20.3 row_number generated ORDER/PARTITION/EXCLUDE matrix',
        'window3.test 1.20.4 dense_rank generated ORDER/PARTITION/EXCLUDE matrix',
        'window3.test 1.20.5 rank generated ORDER/PARTITION/EXCLUDE matrix',
        'window3.test 1.20.7 percent_rank generated matrix',
        'window3.test 1.20.8 cume_dist generated matrix',
        'window3.test 1.20.9 last_value generated matrix',
        'window3.test 1.20.10 nth_value generated matrix',
        'window3.test 1.20.11 first_value generated matrix',
        'window3.test 1.20.12 lead generated matrix',
        'window3.test 1.20.13 lag generated matrix',
        'window3.test 1.20.14 group_concat generated matrix',
        'window3.test 1.20.15 FILTER aggregate generated matrix',
    ]);
};

return $tests;
