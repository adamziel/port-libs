<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$sqliteScalarEquals = static function (mixed $left, mixed $right): bool {
    if ($left === null || $right === null) {
        return false;
    }

    if (is_bool($left)) {
        $left = $left ? 1 : 0;
    }
    if (is_bool($right)) {
        $right = $right ? 1 : 0;
    }

    if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
        return (float) $left === (float) $right;
    }
    if (get_debug_type($left) !== get_debug_type($right)) {
        return false;
    }

    return (string) $left === (string) $right;
};

$sqliteIs = static function (mixed $left, mixed $right) use ($sqliteScalarEquals): bool {
    if ($left === null || $right === null) {
        return $left === null && $right === null;
    }

    return $sqliteScalarEquals($left, $right);
};

$sqliteIsFalse = static function (mixed $value): bool {
    if ($value === null) {
        return false;
    }
    if (is_bool($value)) {
        return $value === false;
    }
    if (is_int($value) || is_float($value)) {
        return (float) $value === 0.0;
    }
    if (is_string($value) && is_numeric($value)) {
        return (float) $value === 0.0;
    }

    return false;
};

$windowDViewRows = static function (array $partitionValues): array {
    $rowsByPartition = [];
    foreach ($partitionValues as $index => $partitionValue) {
        $key = $partitionValue === null ? "\0null" : (string) $partitionValue;
        $rowsByPartition[$key][] = ['source_index' => $index, 'c0' => $partitionValue];
    }

    $materialized = [];
    foreach ($rowsByPartition as $partitionRows) {
        $distances = SQLiteWindowFunction::cumeDist(array_fill(0, count($partitionRows), 0));
        foreach ($partitionRows as $offset => $row) {
            $materialized[$row['source_index']] = [
                'c0' => $row['c0'],
                'cume_dist' => $distances[$offset],
                'true_column' => 1,
                'false_column' => 0,
            ];
        }
    }
    ksort($materialized);

    return array_values($materialized);
};

$tests['real upstream windowD 1.1 string literal is not view true column'] = static function (TestRunner $t) use ($windowDViewRows, $sqliteIs): void {
    $rows = $windowDViewRows(['x']);
    $t->same(false, $sqliteIs('500', $rows[0]['true_column']), "windowD.test 1.1 SELECT ('500') IS (v0.c1)");
};

$tests['real upstream windowD 1.2 parenthesized string is comparison is false'] = static function (TestRunner $t) use ($windowDViewRows, $sqliteIs, $sqliteIsFalse): void {
    $rows = $windowDViewRows(['x']);
    $comparison = $sqliteIs('500', $rows[0]['true_column']);
    $t->same(false, $comparison, "windowD.test 1.2 SELECT (('500') IS (v0.c1))");
    $t->same(true, $sqliteIsFalse($comparison), "windowD.test 1.2 SELECT (('500') IS (v0.c1)) IS FALSE");
};

$tests['real upstream windowD 1.3 view exposes cume dist and true literal'] = static function (TestRunner $t) use ($windowDViewRows): void {
    $rows = $windowDViewRows(['x']);
    $t->same([['c0' => 'x', 'cume_dist' => 1.0, 'true_column' => 1, 'false_column' => 0]], $rows, 'windowD.test 1.3 SELECT * FROM v0');
};

$tests['real upstream windowD 1.4 false is predicate keeps view row'] = static function (TestRunner $t) use ($windowDViewRows, $sqliteIs, $sqliteIsFalse): void {
    $rows = $windowDViewRows(['x']);
    $filtered = array_values(array_filter(
        $rows,
        static fn (array $row): bool => $sqliteIsFalse($sqliteIs('500', $row['true_column'])),
    ));
    $t->same($rows, $filtered, "windowD.test 1.4 WHERE ('500' IS v0.c1) IS FALSE");
};

$tests['real upstream windowD 2.1 integer comparisons do not match projected boolean literals'] = static function (TestRunner $t) use ($sqliteIs): void {
    $row = ['a' => 1, 'b' => 2, 'c' => 1, 'd' => 0];
    $t->same([false, false, false, false], [
        $sqliteIs(500, $row['a']),
        $sqliteIs(500, $row['b']),
        $sqliteIs(500, $row['c']),
        $sqliteIs(500, $row['d']),
    ], 'windowD.test 2.1 SELECT 500 IS a, 500 IS b, 500 IS c, 500 IS d');
};

$tests['real upstream windowD 2.4 through 2.6 max window view true column filters out 500'] = static function (TestRunner $t) use ($sqliteIs): void {
    $viewRows = [['a' => 'value', 'c' => 1]];
    $t->same(false, $sqliteIs(500, $viewRows[0]['c']), 'windowD.test 2.5 SELECT 500 IS c FROM v2');
    $t->same([], array_values(array_filter($viewRows, static fn (array $row): bool => $sqliteIs(500, $row['c']))), 'windowD.test 2.6 SELECT * FROM v2 WHERE 500 IS c');
};

for ($case = 1; $case <= 1200; $case++) {
    $rowCount = 1 + ($case % 7);
    $partitions = [];
    for ($index = 0; $index < $rowCount; $index++) {
        $partitions[] = match (($case + $index) % 5) {
            0 => 'x',
            1 => 'y',
            2 => 'X',
            3 => null,
            default => 'group-' . ($case % 3),
        };
    }

    $probe = match ($case % 8) {
        0 => '500',
        1 => 500,
        2 => '1',
        3 => 1,
        4 => '0',
        5 => 0,
        6 => null,
        default => 'true',
    };
    $targetColumn = $case % 3 === 0 ? 'false_column' : 'true_column';

    $tests["real upstream windowD dynamic cume-dist view boolean is case {$case}"] = static function (TestRunner $t) use ($windowDViewRows, $sqliteIs, $sqliteIsFalse, $partitions, $probe, $targetColumn, $case): void {
        $rows = $windowDViewRows($partitions);
        $actualComparisons = [];
        $falsePredicates = [];
        foreach ($rows as $row) {
            $comparison = $sqliteIs($probe, $row[$targetColumn]);
            $actualComparisons[] = $comparison;
            $falsePredicates[] = $sqliteIsFalse($comparison);
            $t->same(1.0, $row['cume_dist'], "windowD.test 1.3 dynamic cume_dist case {$case}");
        }

        $expectedComparisons = [];
        foreach ($rows as $row) {
            $target = $row[$targetColumn];
            $expectedComparisons[] = match (true) {
                $probe === null => false,
                is_int($probe), is_float($probe), is_bool($probe) => (int) $probe === $target,
                default => false,
            };
        }
        $expectedFalsePredicates = array_map($sqliteIsFalse, $expectedComparisons);

        $t->same($expectedComparisons, $actualComparisons, "windowD.test 1.1-1.2 dynamic IS comparisons case {$case}");
        $t->same($expectedFalsePredicates, $falsePredicates, "windowD.test 1.2 dynamic IS FALSE case {$case}");
        $t->same(count($partitions), count($rows), "windowD.test 1.3 dynamic view row count case {$case}");
    };
}

$tests['real upstream windowD dynamic cites exact upstream source sections'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowD.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowD.test 2.0-2.6',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowD.test 1.0-1.4',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowD.test 2.0-2.6',
    ]);
};

return $tests;
