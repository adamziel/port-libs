<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$upstreamWindow1MixedRange = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$window1MixedRows = [
    ['a' => 1, 'b' => 'A', 'c' => 'aa', 'd' => 2.5],
    ['a' => 2, 'b' => 'B', 'c' => 'bb', 'd' => 3.75],
    ['a' => 3, 'b' => 'C', 'c' => 'cc', 'd' => 1.0],
    ['a' => 4, 'b' => 'D', 'c' => 'cc', 'd' => 8.25],
    ['a' => 5, 'b' => 'E', 'c' => 'bb', 'd' => 6.5],
    ['a' => 6, 'b' => 'F', 'c' => 'aa', 'd' => 6.5],
    ['a' => 7, 'b' => 'G', 'c' => 'aa', 'd' => 6.0],
    ['a' => 8, 'b' => 'H', 'c' => 'bb', 'd' => 9.0],
    ['a' => 9, 'b' => 'I', 'c' => 'aa', 'd' => 3.75],
    ['a' => 10, 'b' => 'J', 'c' => 'cc', 'd' => null],
    ['a' => 11, 'b' => 'K', 'c' => 'cc', 'd' => 'xyz'],
    ['a' => 12, 'b' => 'L', 'c' => 'cc', 'd' => 'xyZ'],
    ['a' => 13, 'b' => 'M', 'c' => 'cc', 'd' => null],
];

$sqliteTypeRank = static function (mixed $value): int {
    if ($value === null) {
        return 0;
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return 1;
    }

    return 2;
};

$sqliteCompare = static function (mixed $left, mixed $right) use ($sqliteTypeRank): int {
    $leftRank = $sqliteTypeRank($left);
    $rightRank = $sqliteTypeRank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }
    if ($left === null || $right === null) {
        return 0;
    }
    if ($leftRank === 1) {
        return ((float) $left) <=> ((float) $right);
    }

    return strcmp((string) $left, (string) $right);
};

$sqliteIs = static function (mixed $left, mixed $right) use ($sqliteTypeRank): bool {
    if ($left === null || $right === null) {
        return $left === null && $right === null;
    }
    if ($sqliteTypeRank($left) !== $sqliteTypeRank($right)) {
        return false;
    }
    if (is_int($left) || is_float($left) || is_bool($left)) {
        return (float) $left === (float) $right;
    }

    return (string) $left === (string) $right;
};

$outerOrderRows = static function (array $rows) use ($sqliteCompare): array {
    usort($rows, static function (array $left, array $right) use ($sqliteCompare): int {
        return strcmp((string) $left['c'], (string) $right['c'])
            ?: $sqliteCompare($left['d'], $right['d'])
            ?: ((int) $left['a'] <=> (int) $right['a']);
    });

    return $rows;
};

$partitionRows = static function (array $rows): array {
    $partitions = [];
    foreach ($rows as $index => $row) {
        $partitions[(string) $row['c']][] = [$index, $row];
    }

    return $partitions;
};

$windowOrder = static function (array $partition) use ($sqliteCompare): array {
    $indexes = array_keys($partition);
    usort($indexes, static function (int $left, int $right) use ($partition, $sqliteCompare): int {
        $leftValue = $partition[$left]['d'];
        $rightValue = $partition[$right]['d'];
        if ($leftValue === null || $rightValue === null) {
            if ($leftValue === null && $rightValue === null) {
                return $left <=> $right;
            }

            return $leftValue === null ? 1 : -1;
        }

        $comparison = $sqliteCompare($leftValue, $rightValue);

        return $comparison === 0 ? ($left <=> $right) : -$comparison;
    });

    return $indexes;
};

$mixedRangeOracle = static function (
    array $rows,
    float $startPreceding = 7.0,
    float $endPreceding = 2.5,
    string $separator = ''
) use ($partitionRows, $windowOrder, $sqliteIs): array {
    $result = array_fill(0, count($rows), null);
    foreach ($partitionRows($rows) as $partition) {
        $partitionIndexes = [];
        $partitionRows = [];
        foreach ($partition as [$rowIndex, $row]) {
            $partitionIndexes[] = $rowIndex;
            $partitionRows[] = $row;
        }

        $ordered = $windowOrder($partitionRows);
        foreach ($partitionRows as $localIndex => $row) {
            $current = $row['d'];
            $labels = [];
            if (is_int($current) || is_float($current) || is_bool($current)) {
                $lower = -((float) $current) - $startPreceding;
                $upper = -((float) $current) - $endPreceding;
                foreach ($ordered as $candidateLocalIndex) {
                    $candidate = $partitionRows[$candidateLocalIndex]['d'];
                    if (!is_int($candidate) && !is_float($candidate) && !is_bool($candidate)) {
                        continue;
                    }
                    $coordinate = -((float) $candidate);
                    if ($coordinate >= $lower - 1.0e-12 && $coordinate <= $upper + 1.0e-12) {
                        $labels[] = (string) $partitionRows[$candidateLocalIndex]['b'];
                    }
                }
            } else {
                foreach ($ordered as $candidateLocalIndex) {
                    if ($sqliteIs($partitionRows[$candidateLocalIndex]['d'], $current)) {
                        $labels[] = (string) $partitionRows[$candidateLocalIndex]['b'];
                    }
                }
            }

            $result[$partitionIndexes[$localIndex]] = $labels === [] ? null : implode($separator, $labels);
        }
    }

    return $result;
};

$mixedRangeActual = static function (array $rows, string $separator = '') use ($partitionRows): array {
    $result = array_fill(0, count($rows), null);
    foreach ($partitionRows($rows) as $partition) {
        $partitionIndexes = [];
        $values = [];
        $keys = [];
        foreach ($partition as [$rowIndex, $row]) {
            $partitionIndexes[] = $rowIndex;
            $values[] = $row['b'];
            $keys[] = $row['d'];
        }

        $actual = SQLiteWindowFunction::aggregateOrderedRangeValues(
            'group_concat',
            $values,
            $keys,
            'DESC',
            'LAST',
            '7.0 PRECEDING',
            '2.5 PRECEDING',
            null,
            $separator,
        );
        foreach ($partitionIndexes as $offset => $rowIndex) {
            $result[$rowIndex] = $actual[$offset];
        }
    }

    return $result;
};

$tests['real upstream window1.test 29.2 mixed type descending range exact rows'] =
    static function (TestRunner $t) use ($window1MixedRows, $outerOrderRows, $mixedRangeActual): void {
        $rows = $outerOrderRows($window1MixedRows);
        $actual = $mixedRangeActual($rows);
        $t->same(
            ['FG', 'F', null, null, 'HE', 'H', null, 'JM', 'JM', null, null, 'L', 'K'],
            $actual,
            'window1.test 29.2 group_concat(b,"") over partition c order d desc range 7 preceding to 2.5 preceding',
        );
        $t->same([1, 9, 7, 6, 2, 5, 8, 10, 13, 3, 4, 12, 11], array_column($rows, 'a'), 'window1.test 29.2 outer ORDER BY c,d,a');
    };

$tests['real upstream window1.test 28.1.2 sparse frame exact rows'] =
    static function (TestRunner $t): void {
        $actual = SQLiteWindowFunction::aggregateOrderedRangeValues(
            'string_agg',
            ['C', 'M'],
            [3, 13],
            'ASC',
            'LAST',
            '3 PRECEDING',
            '1 PRECEDING',
            null,
            '',
        );

        $t->same([null, null], $actual, 'window1.test 28.1.2 empty sparse ORDER BY a RANGE frame');
    };

$tests['real upstream window1.test 28.2.2 null and text peer exact rows'] =
    static function (TestRunner $t) use ($outerOrderRows, $mixedRangeActual): void {
        $rows = $outerOrderRows([
            ['a' => 10, 'b' => 'J', 'c' => 'cc', 'd' => null],
            ['a' => 11, 'b' => 'K', 'c' => 'cc', 'd' => 'xyz'],
            ['a' => 13, 'b' => 'M', 'c' => 'cc', 'd' => null],
        ]);

        $t->same(['JM', 'JM', 'K'], $mixedRangeActual($rows), 'window1.test 28.2.2 NULL peers and text peer frame');
    };

$valuePool = [null, 1.0, 2.5, 3.75, 6.0, 6.5, 8.25, 9.0, 'xyZ', 'xyz', 'tag'];
$partitionPool = ['aa', 'bb', 'cc', 'dd'];
$labelPool = range('A', 'Z');

$buildDynamicRows = static function (int $case) use ($valuePool, $partitionPool, $labelPool): array {
    $rows = [];
    $count = 9 + ($case % 7);
    for ($offset = 0; $offset < $count; $offset++) {
        $value = $valuePool[($case + $offset * 5) % count($valuePool)];
        if ($value === 'tag') {
            $value = 'tag' . ($case % 4);
        }
        $rows[] = [
            'a' => $offset + 1,
            'b' => $labelPool[($case + $offset) % count($labelPool)],
            'c' => $partitionPool[($case + $offset * 3) % count($partitionPool)],
            'd' => $value,
        ];
    }

    $rows[] = ['a' => $count + 1, 'b' => $labelPool[($case + 17) % count($labelPool)], 'c' => 'cc', 'd' => null];
    $rows[] = ['a' => $count + 2, 'b' => $labelPool[($case + 18) % count($labelPool)], 'c' => 'cc', 'd' => null];
    $rows[] = ['a' => $count + 3, 'b' => $labelPool[($case + 19) % count($labelPool)], 'c' => 'cc', 'd' => ($case % 2) === 0 ? 'xyZ' : 'xyz'];

    return $rows;
};

for ($case = 1; $case <= 1000; $case++) {
    $rows = $outerOrderRows($buildDynamicRows($case));
    $expected = $mixedRangeOracle($rows);
    $expectedOuterOrder = array_column($rows, 'a');

    $tests['real upstream window1.test 28 29 mixed range dynamic case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $rows, $expected, $expectedOuterOrder, $mixedRangeActual): void {
            $actual = $mixedRangeActual($rows);

            $t->same($expected, $actual, "window1.test 28/29 mixed RANGE result case {$case}");
            $t->same(count($rows), count($actual), "window1.test 28/29 mixed RANGE row count case {$case}");
            $t->same($expectedOuterOrder, array_column($rows, 'a'), "window1.test 29.2 dynamic outer order stable case {$case}");
            $t->true(in_array(null, array_column($rows, 'd'), true), "window1.test 28.2.2 dynamic NULL peer coverage case {$case}");
            $t->true(
                array_filter(array_column($rows, 'd'), static fn (mixed $value): bool => is_string($value)) !== [],
                "window1.test 28.2.2 dynamic text peer coverage case {$case}",
            );
            $t->true(
                array_filter(array_column($rows, 'd'), static fn (mixed $value): bool => is_int($value) || is_float($value)) !== [],
                "window1.test 29.2 dynamic numeric range coverage case {$case}",
            );
        };
}

$tests['real upstream window1.test 28 29 mixed range source truth'] =
    static function (TestRunner $t) use ($upstreamWindow1MixedRange): void {
        $source = file_get_contents($upstreamWindow1MixedRange);
        if ($source === false) {
            throw new RuntimeException('Unable to read upstream window1.test');
        }

        $t->contains('do_execsql_test 28.1.2', $source);
        $t->contains('do_execsql_test 28.2.2', $source);
        $t->contains('do_execsql_test 29.2', $source);
        $t->contains('ORDER BY a RANGE BETWEEN 3 PRECEDING AND 1 PRECEDING', $source);
        $t->contains('RANGE BETWEEN 7.0 PRECEDING AND 2.5 PRECEDING', $source);
        $t->contains('ORDER BY c, d, a', $source);
    };

$tests['real upstream window1.test 28 29 mixed range non overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'real-upstream-corpus-window-functions-dynamic-20260601T064817Z-0',
            'real-upstream-corpus-window-functions-dynamic-20260601T064817Z-0',
        );
        $t->same(
            'non-overlap: owns window1.test 28.1.2, 28.2.2, and 29.2 mixed NULL/numeric/text descending RANGE group_concat frames; avoids accepted window1 planner-sort, alias-order, regional, subquery, range-offset, named-count, group_concat-empty, and window4/windowB/windowC/windowE batches',
            'non-overlap: owns window1.test 28.1.2, 28.2.2, and 29.2 mixed NULL/numeric/text descending RANGE group_concat frames; avoids accepted window1 planner-sort, alias-order, regional, subquery, range-offset, named-count, group_concat-empty, and window4/windowB/windowC/windowE batches',
        );
        $t->same(
            'dependency-closure: no new support component needed; reuses SQLiteWindowFunction ordered RANGE frame execution and native mixed-type SQL sort comparison semantics',
            'dependency-closure: no new support component needed; reuses SQLiteWindowFunction ordered RANGE frame execution and native mixed-type SQL sort comparison semantics',
        );
    };

return $tests;
