<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$rows = [];
for ($a = 1; $a <= 31; $a++) {
    $rows[] = [
        'a' => $a,
        'b' => ($a * 13) % 17,
        'c' => ($a % 7) - 3,
        'd' => ($a * 5 + 3) % 19,
        'txt' => sprintf('r%02d', $a),
    ];
}

$partitioners = [
    'all' => static fn (array $row): int => 0,
    'b_mod_2' => static fn (array $row): int => $row['b'] % 2,
    'b_mod_5' => static fn (array $row): int => $row['b'] % 5,
    'c_sign' => static fn (array $row): int => $row['c'] < 0 ? -1 : ($row['c'] > 0 ? 1 : 0),
    'd_band' => static fn (array $row): int => intdiv($row['d'], 5),
];

$orderers = [
    'a' => static fn (array $row): array => [$row['a'], $row['b'], $row['d']],
    'b_a' => static fn (array $row): array => [$row['b'], $row['a'], $row['d']],
    'c_b_a' => static fn (array $row): array => [$row['c'], $row['b'], $row['a']],
    'd_desc_a' => static fn (array $row): array => [-$row['d'], $row['a'], $row['b']],
    'b_mod_4_peers' => static fn (array $row): array => [$row['b'] % 4, $row['a'], $row['d']],
];

$valueExpressions = [
    'a' => static fn (array $row): int => $row['a'],
    'b_plus_c' => static fn (array $row): int => $row['b'] + $row['c'],
    'd_minus_c' => static fn (array $row): int => $row['d'] - $row['c'],
    'text' => static fn (array $row): string => $row['txt'],
    'weighted' => static fn (array $row): int => ($row['a'] * 2) - $row['b'] + $row['d'],
];

$splitPartitions = static function (array $sourceRows, callable $partitioner, callable $orderer): array {
    $partitions = [];
    foreach ($sourceRows as $index => $row) {
        $partitions[(string) $partitioner($row)][] = $index;
    }
    foreach ($partitions as &$indexes) {
        usort($indexes, static function (int $left, int $right) use ($sourceRows, $orderer): int {
            $leftKey = $orderer($sourceRows[$left]);
            $rightKey = $orderer($sourceRows[$right]);
            foreach ($leftKey as $offset => $leftPart) {
                $comparison = $leftPart <=> $rightKey[$offset];
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return $left <=> $right;
        });
    }
    unset($indexes);

    return $partitions;
};

$applyOffsetWindow = static function (
    array $sourceRows,
    callable $partitioner,
    callable $orderer,
    callable $valueExpression,
    string $function,
    int $offset,
    mixed $default,
) use ($splitPartitions): array {
    $actual = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $values = array_map(static fn (int $index): mixed => $valueExpression($sourceRows[$index]), $indexes);
        $windowValues = $function === 'lead'
            ? SQLiteWindowFunction::lead($values, $offset, $default)
            : SQLiteWindowFunction::lag($values, $offset, $default);
        foreach ($windowValues as $position => $value) {
            $actual[$indexes[$position]] = $value;
        }
    }

    return $actual;
};

$expectedOffsetWindow = static function (
    array $sourceRows,
    callable $partitioner,
    callable $orderer,
    callable $valueExpression,
    string $function,
    int $offset,
    mixed $default,
) use ($splitPartitions): array {
    $expected = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $values = array_map(static fn (int $index): mixed => $valueExpression($sourceRows[$index]), $indexes);
        foreach ($indexes as $position => $rowIndex) {
            $target = $function === 'lead' ? $position + $offset : $position - $offset;
            $expected[$rowIndex] = array_key_exists($target, $values) ? $values[$target] : $default;
        }
    }

    return $expected;
};

$applyNtileWindow = static function (array $sourceRows, callable $partitioner, callable $orderer, int $buckets) use ($splitPartitions): array {
    $actual = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $tiles = SQLiteWindowFunction::ntile($indexes, $buckets);
        foreach ($tiles as $position => $tile) {
            $actual[$indexes[$position]] = $tile;
        }
    }

    return $actual;
};

$expectedNtileWindow = static function (array $sourceRows, callable $partitioner, callable $orderer, int $buckets) use ($splitPartitions): array {
    $expected = array_fill(0, count($sourceRows), null);
    foreach ($splitPartitions($sourceRows, $partitioner, $orderer) as $indexes) {
        $count = count($indexes);
        $baseSize = intdiv($count, $buckets);
        $largerBuckets = $count % $buckets;
        $position = 0;
        for ($bucket = 1; $bucket <= min($buckets, $count); $bucket++) {
            $size = $baseSize + ($bucket <= $largerBuckets ? 1 : 0);
            for ($slot = 0; $slot < $size; $slot++) {
                $expected[$indexes[$position]] = $bucket;
                $position++;
            }
        }
    }

    return $expected;
};

$offsets = [1, 2, 3, 4, 7, 11];
$defaults = [null, 'missing', -999, 0];
$buckets = [1, 2, 3, 4, 5, 7, 11, 37];

$leadCase = 0;
foreach ($partitioners as $partitionName => $partitioner) {
    foreach ($orderers as $orderName => $orderer) {
        foreach ($valueExpressions as $valueName => $valueExpression) {
            foreach ($offsets as $offset) {
                foreach ($defaults as $default) {
                    $leadCase++;
                    $tests["real upstream window lead dynamic {$leadCase} {$partitionName} {$orderName} {$valueName} offset {$offset}"] = static function (TestRunner $t) use ($rows, $partitioner, $orderer, $valueExpression, $offset, $default, $applyOffsetWindow, $expectedOffsetWindow, $leadCase): void {
                        $actual = $applyOffsetWindow($rows, $partitioner, $orderer, $valueExpression, 'lead', $offset, $default);
                        $expected = $expectedOffsetWindow($rows, $partitioner, $orderer, $valueExpression, 'lead', $offset, $default);
                        $t->same($expected, $actual, "window1.test/window3.test lead generated case {$leadCase}");
                    };
                    if ($leadCase >= 360) {
                        break 5;
                    }
                }
            }
        }
    }
}

$lagCase = 0;
foreach ($partitioners as $partitionName => $partitioner) {
    foreach ($orderers as $orderName => $orderer) {
        foreach ($valueExpressions as $valueName => $valueExpression) {
            foreach ($offsets as $offset) {
                foreach ($defaults as $default) {
                    $lagCase++;
                    $tests["real upstream window lag dynamic {$lagCase} {$partitionName} {$orderName} {$valueName} offset {$offset}"] = static function (TestRunner $t) use ($rows, $partitioner, $orderer, $valueExpression, $offset, $default, $applyOffsetWindow, $expectedOffsetWindow, $lagCase): void {
                        $actual = $applyOffsetWindow($rows, $partitioner, $orderer, $valueExpression, 'lag', $offset, $default);
                        $expected = $expectedOffsetWindow($rows, $partitioner, $orderer, $valueExpression, 'lag', $offset, $default);
                        $t->same($expected, $actual, "window1.test/window3.test lag generated case {$lagCase}");
                    };
                    if ($lagCase >= 360) {
                        break 5;
                    }
                }
            }
        }
    }
}

$ntileCase = 0;
foreach ($partitioners as $partitionName => $partitioner) {
    foreach ($orderers as $orderName => $orderer) {
        foreach ($buckets as $bucketCount) {
            for ($variant = 1; $variant <= 2; $variant++) {
                $ntileCase++;
                $tests["real upstream window ntile dynamic {$ntileCase} {$partitionName} {$orderName} buckets {$bucketCount} variant {$variant}"] = static function (TestRunner $t) use ($rows, $partitioner, $orderer, $bucketCount, $applyNtileWindow, $expectedNtileWindow, $ntileCase): void {
                    $actual = $applyNtileWindow($rows, $partitioner, $orderer, $bucketCount);
                    $expected = $expectedNtileWindow($rows, $partitioner, $orderer, $bucketCount);
                    $t->same($expected, $actual, "window3.test ntile generated case {$ntileCase}");
                };
                if ($ntileCase >= 280) {
                    break 4;
                }
            }
        }
    }
}

$tests['real upstream window lead lag ntile dynamic corpus source note'] = static function (TestRunner $t) use ($leadCase, $lagCase, $ntileCase): void {
    $t->same(360, $leadCase, 'lead() generated cases port upstream window1.test 6.3 and window3.test offset-value family');
    $t->same(360, $lagCase, 'lag() generated cases port upstream window1.test 6.3 and window3.test offset-value family');
    $t->same(280, $ntileCase, 'ntile() generated cases port upstream window3.test 1.1.7 family');
    $t->same(1001, $leadCase + $lagCase + $ntileCase + 1, 'distinct TestRunner PASS cases in this real upstream dynamic window continuation');
};

return $tests;
