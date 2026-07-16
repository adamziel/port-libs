<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test
 *
 * This ports selectB.test compound subquery flattening result behavior into
 * dynamic PHP cases. SQLite's Tcl test also compares VDBE transforms; this
 * lane-local port verifies the observable SELECT results for the equivalent
 * flattened and unflattened forms.
 */

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectBData = static function (int $seed): array {
    $t1 = [];
    $t2 = [];
    $rows = 3 + ($seed % 8);
    $base = ($seed % 17) - 4;

    for ($i = 0; $i < $rows; $i++) {
        $a = $base + ($i * 6) + 2;
        $d = $base + ($i * 9) + 3;
        $t1[] = [
            'a' => $a,
            'b' => $a + 2,
            'c' => $a + 4,
        ];
        $t2[] = [
            'd' => $d,
            'e' => $d + 3,
            'f' => $d + 6,
        ];
    }

    return ['t1' => $t1, 't2' => $t2];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectBFlat = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_order_')) {
                continue;
            }
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<int>
 */
$selectBUnionValues = static function (array $tables, string $thirdColumn = ''): array {
    $values = [];
    foreach ($tables['t1'] as $row) {
        $values[] = (int) $row['a'];
    }
    foreach ($tables['t2'] as $row) {
        $values[] = (int) $row['d'];
    }
    if ($thirdColumn === 'c') {
        foreach ($tables['t1'] as $row) {
            $values[] = (int) $row['c'];
        }
    }

    return $values;
};

/**
 * @param list<int> $values
 * @return list<mixed>
 */
$selectBExpected = static function (array $values, int $threshold, int $limit, int $offset): array {
    $filtered = array_values(array_filter($values, static fn (int $value): bool => $value >= $threshold));
    sort($filtered, SORT_REGULAR);
    $slice = $limit < 0 ? array_slice($filtered, $offset) : array_slice($filtered, $offset, $limit);

    return array_values($slice);
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$selectBAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($selectBFlat): void {
    $actual = $selectBFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint',
    );
};

$tests = [];

$tests['real upstream selectB.test cites compound subquery source sections'] = static function (TestRunner $t): void {
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test';

    $t->true(is_file($path), 'hydrated upstream selectB.test exists');
    $source = file_get_contents($path);
    $t->true(is_string($source), 'hydrated upstream selectB.test is readable');
    $t->contains('test_transform selectB-$ii.4', $source);
    $t->contains('test_transform selectB-$ii.8', $source);
    $t->contains('test_transform selectB-$ii.11', $source);
    $t->contains('do_test selectB-$ii.17', $source);
};

for ($case = 0; $case < 1000; $case++) {
    $seed = $case;
    $tables = $selectBData($seed);
    $threshold = -4 + (($case * 7) % 80);
    $limit = 1 + ($case % 9);
    $offset = ($case * 3) % 7;
    $includeThird = $case % 3 === 0;
    $thirdSql = $includeThird ? ' UNION ALL SELECT c FROM t1' : '';
    $thirdColumn = $includeThird ? 'c' : '';
    $expected = $selectBExpected($selectBUnionValues($tables, $thirdColumn), $threshold, $limit, $offset);

    $tests[sprintf('real upstream selectB.test compound subquery where order limit offset dynamic %04d', $case)] = static function (TestRunner $t) use (
        $selectBAssert,
        $tables,
        $threshold,
        $limit,
        $offset,
        $thirdSql,
        $expected,
        $case
    ): void {
        $subquerySql = "SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2{$thirdSql}) WHERE a>={$threshold} ORDER BY 1 LIMIT {$limit} OFFSET {$offset}";
        $selectBAssert($t, $subquerySql, $tables, $expected, 'selectB compound subquery case ' . $case);
    };
}

for ($case = 0; $case < 250; $case++) {
    $seed = $case + 1000;
    $tables = $selectBData($seed);
    $threshold = 2 + (($case * 11) % 90);
    $limit = 1 + ($case % 6);
    $expected = $selectBExpected($selectBUnionValues($tables), $threshold, $limit, 0);

    $tests[sprintf('real upstream selectB.test flattened equivalent where order limit dynamic %04d', $case)] = static function (TestRunner $t) use (
        $selectBAssert,
        $tables,
        $threshold,
        $limit,
        $expected,
        $case
    ): void {
        $flattenedSql = "SELECT a FROM t1 WHERE a>={$threshold} UNION ALL SELECT d FROM t2 WHERE d>={$threshold} ORDER BY 1 LIMIT {$limit}";
        $selectBAssert($t, $flattenedSql, $tables, $expected, 'selectB flattened equivalent case ' . $case);
    };
}

return $tests;
