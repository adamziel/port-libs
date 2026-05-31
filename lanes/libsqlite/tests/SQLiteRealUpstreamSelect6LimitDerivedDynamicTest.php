<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test
 * - select6-9.1 through select6-9.11.
 *
 * This dynamic shard ports SELECT behavior for derived tables that carry their
 * own LIMIT/OFFSET clauses, including outer LIMIT composition and scalar
 * expressions projected by the derived SELECT.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select6LimitDerivedFlat = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @return list<array{x:int,y:int,payload:string}>
 */
$select6LimitDerivedRows = static function (int $rowCount, int $offset): array {
    $rows = [];
    for ($index = 1; $index <= $rowCount; $index++) {
        $x = $offset + $index;
        $rows[] = [
            'x' => $x,
            'y' => 1 + (($index + $offset) % 5),
            'payload' => 'v' . (($index + $offset) % 11),
        ];
    }

    return $rows;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$select6LimitDerivedAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($select6LimitDerivedFlat): void {
    $actual = $select6LimitDerivedFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat value count for ' . $scenario);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $scenario,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'result fingerprint for ' . $scenario,
    );
};

$tests['real upstream select6.test select6-9 derived limit source citation'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test';

    $t->true(is_file($source), 'hydrated upstream select6.test exists');
    $text = file_get_contents($source);
    $t->contains('Ticket #1634', $text);
    $t->contains('SELECT a.x, b.x FROM t1 AS a, (SELECT x FROM t1 LIMIT 2) AS b', $text);
    $t->contains('SELECT x FROM (SELECT x FROM t1 LIMIT 2 OFFSET 1)', $text);
    $t->contains('SELECT x, y FROM (SELECT x, (SELECT 10+x) y FROM t1 LIMIT -1 OFFSET 1)', $text);
};

for ($seed = 0; $seed < 250; $seed++) {
    $rowCount = 6 + ($seed % 17);
    $baseOffset = $seed * 3;
    $innerLimit = 1 + ($seed % 7);
    $innerOffset = $seed % 5;
    $outerLimit = 1 + (($seed * 2) % 6);
    $outerOffset = $seed % 3;
    $rows = $select6LimitDerivedRows($rowCount, $baseOffset);
    $tables = ['app_values' => $rows];

    $innerRows = array_slice($rows, $innerOffset, $innerLimit);
    $outerRows = array_slice($innerRows, 0, $outerLimit);
    $expectedOuterLimit = array_map(static fn (array $row): int => $row['x'], $outerRows);
    $sqlOuterLimit = "SELECT x FROM (SELECT x FROM app_values LIMIT {$innerLimit} OFFSET {$innerOffset}) LIMIT {$outerLimit}";

    $tests[sprintf('real upstream select6.test select6-9 dynamic derived inner and outer limit seed %04d', $seed)] =
        static function (TestRunner $t) use ($select6LimitDerivedAssert, $tables, $expectedOuterLimit, $sqlOuterLimit): void {
            $select6LimitDerivedAssert($t, $sqlOuterLimit, $tables, $expectedOuterLimit, 'select6-9 inner outer limit');
        };

    $expectedOuterOffset = array_map(static fn (array $row): int => $row['x'], array_slice($rows, $outerOffset, $outerLimit));
    $sqlOuterOffset = "SELECT x FROM (SELECT x FROM app_values) LIMIT {$outerLimit} OFFSET {$outerOffset}";

    $tests[sprintf('real upstream select6.test select6-9 dynamic outer limit offset seed %04d', $seed)] =
        static function (TestRunner $t) use ($select6LimitDerivedAssert, $tables, $expectedOuterOffset, $sqlOuterOffset): void {
            $select6LimitDerivedAssert($t, $sqlOuterOffset, $tables, $expectedOuterOffset, 'select6-9 outer limit offset');
        };

    $constant = 10 + ($seed % 19);
    $scalarRows = array_slice($rows, $innerOffset);
    $expectedScalar = [];
    foreach ($scalarRows as $row) {
        $expectedScalar[] = $row['x'];
        $expectedScalar[] = $constant + $row['x'];
    }
    $sqlScalar = "SELECT x, y FROM (SELECT x, (SELECT {$constant})+x AS y FROM app_values LIMIT -1 OFFSET {$innerOffset})";

    $tests[sprintf('real upstream select6.test select6-9 dynamic scalar projection in limited derived row seed %04d', $seed)] =
        static function (TestRunner $t) use ($select6LimitDerivedAssert, $tables, $expectedScalar, $sqlScalar): void {
            $select6LimitDerivedAssert($t, $sqlScalar, $tables, $expectedScalar, 'select6-9 scalar projection limited derived row');
        };

    $leftMax = $baseOffset + 1 + ($seed % min(8, $rowCount));
    $derivedLimit = 1 + (($seed + 1) % min(6, $rowCount));
    $expectedJoinRows = [];
    $rightRows = array_slice($rows, 0, $derivedLimit);
    foreach ($rows as $left) {
        if ($left['x'] > $leftMax) {
            continue;
        }
        foreach ($rightRows as $right) {
            $expectedJoinRows[] = ['left_x' => $left['x'], 'right_x' => $right['x']];
        }
    }
    usort($expectedJoinRows, static fn (array $a, array $b): int => ($a['left_x'] <=> $b['left_x']) ?: ($a['right_x'] <=> $b['right_x']));
    $expectedJoin = [];
    foreach ($expectedJoinRows as $row) {
        $expectedJoin[] = $row['left_x'];
        $expectedJoin[] = $row['right_x'];
    }
    $sqlJoin = "SELECT a.x, b.x FROM app_values AS a, (SELECT x FROM app_values LIMIT {$derivedLimit}) AS b WHERE a.x<={$leftMax} ORDER BY 1, 2";

    $tests[sprintf('real upstream select6.test select6-9 dynamic join against limited derived table seed %04d', $seed)] =
        static function (TestRunner $t) use ($select6LimitDerivedAssert, $tables, $expectedJoin, $sqlJoin): void {
            $select6LimitDerivedAssert($t, $sqlJoin, $tables, $expectedJoin, 'select6-9 join against limited derived table');
        };
}

return $tests;
