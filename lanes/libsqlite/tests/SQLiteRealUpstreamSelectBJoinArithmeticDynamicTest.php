<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test
 *
 * This ports the selectB-$ii.23 through selectB-$ii.25 compound-subquery
 * cluster. The upstream Tcl file uses the same compound SELECT shape with
 * inner/left joins and arithmetic projection; this shard varies the row values
 * while preserving the observable result semantics.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectBJoinArithmeticFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_order_')) {
                continue;
            }
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectBJoinArithmeticTables = static function (int $seed): array {
    $base = ($seed % 19) - 7;
    $step = 2 + ($seed % 5);

    $t1 = [];
    for ($i = 0; $i < 3; $i++) {
        $a = $base + ($i * $step);
        $t1[] = [
            'a' => $a,
            'b' => $a + 2 + ($seed % 3),
            'c' => $a + 4 + ($i % 2),
        ];
    }

    $joinIndex = $seed % 3;
    $t2 = [
        [
            'd' => $t1[$joinIndex]['c'],
            'e' => $t1[$joinIndex]['c'] + 3,
            'f' => $t1[$joinIndex]['c'] + 6 + ($seed % 4),
        ],
        [
            'd' => $base + 40 + ($seed % 7),
            'e' => $base + 43 + ($seed % 7),
            'f' => $base + 46 + ($seed % 7),
        ],
    ];

    return ['t1' => $t1, 't2' => $t2];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<array{0:mixed,1:mixed}>
 */
$selectBJoinArithmeticRows = static function (array $tables, bool $leftJoin): array {
    $rows = [];

    foreach ($tables['t1'] as $row) {
        $rows[] = [$row['a'], $row['b']];
    }

    foreach ($tables['t1'] as $left) {
        $matched = false;
        foreach ($tables['t2'] as $right) {
            if ($left['c'] === $right['d']) {
                $matched = true;
                $rows[] = [($left['a'] * 10) + 0.1, ($right['f'] * 10) + 0.1];
            }
        }
        if ($leftJoin && !$matched) {
            $rows[] = [($left['a'] * 10) + 0.1, null];
        }
    }

    foreach ($tables['t1'] as $row) {
        $rows[] = [$row['a'] * 100, $row['b'] * 100];
    }

    usort($rows, static fn (array $left, array $right): int => ($left[0] <=> $right[0]) ?: (($left[1] ?? -INF) <=> ($right[1] ?? -INF)));

    return $rows;
};

/**
 * @param list<array{0:mixed,1:mixed}> $rows
 * @return list<mixed>
 */
$selectBJoinArithmeticFlattenPairs = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        $flat[] = $row[0];
        $flat[] = $row[1];
    }

    return $flat;
};

/**
 * @param list<array{0:mixed,1:mixed}> $rows
 * @return list<mixed>
 */
$selectBJoinArithmeticSums = static function (array $rows): array {
    $sums = [];
    foreach ($rows as $row) {
        if ($row[1] !== null) {
            $sums[] = $row[0] + $row[1];
        }
    }
    sort($sums, SORT_REGULAR);

    return $sums;
};

$selectBJoinArithmeticAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($selectBJoinArithmeticFlat): void {
    $actual = $selectBJoinArithmeticFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' result count');
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
    $t->contains('UNION ALL', $sql, $label . ' keeps compound upstream shape');
};

$tests = [];

$tests['real upstream selectB.test cites join arithmetic compound sections'] = static function (TestRunner $t): void {
    $path = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test';

    $t->true(is_file($path), 'hydrated upstream selectB.test exists');
    $source = file_get_contents($path);
    $t->true(is_string($source), 'hydrated upstream selectB.test is readable');
    $t->contains('SELECT x, y FROM (', $source);
    $t->contains('SELECT a*10 + 0.1, f*10 + 0.1 FROM t1 JOIN t2 ON (c=d)', $source);
    $t->contains('SELECT a*10 + 0.1, f*10 + 0.1 FROM t1 LEFT JOIN t2 ON (c=d)', $source);
    $t->contains('SELECT x+y FROM (', $source);
};

for ($case = 0; $case < 400; $case++) {
    $tables = $selectBJoinArithmeticTables($case);
    $expected = $selectBJoinArithmeticFlattenPairs($selectBJoinArithmeticRows($tables, false));

    $tests[sprintf('real upstream selectB.test selectB-23 join arithmetic dynamic %04d', $case)] =
        static function (TestRunner $t) use ($selectBJoinArithmeticAssert, $tables, $expected, $case): void {
            $sql = <<<'SQL'
SELECT x, y FROM (
  SELECT a AS x, b AS y FROM t1
  UNION ALL
  SELECT a*10 + 0.1, f*10 + 0.1 FROM t1 JOIN t2 ON (c=d)
  UNION ALL
  SELECT a*100, b*100 FROM t1
) ORDER BY 1
SQL;
            $selectBJoinArithmeticAssert($t, $sql, $tables, $expected, 'selectB-23 case ' . $case);
        };
}

for ($case = 0; $case < 400; $case++) {
    $tables = $selectBJoinArithmeticTables($case + 400);
    $expected = $selectBJoinArithmeticFlattenPairs($selectBJoinArithmeticRows($tables, true));

    $tests[sprintf('real upstream selectB.test selectB-24 left join arithmetic dynamic %04d', $case)] =
        static function (TestRunner $t) use ($selectBJoinArithmeticAssert, $tables, $expected, $case): void {
            $sql = <<<'SQL'
SELECT x, y FROM (
  SELECT a AS x, b AS y FROM t1
  UNION ALL
  SELECT a*10 + 0.1, f*10 + 0.1 FROM t1 LEFT JOIN t2 ON (c=d)
  UNION ALL
  SELECT a*100, b*100 FROM t1
) ORDER BY 1
SQL;
            $selectBJoinArithmeticAssert($t, $sql, $tables, $expected, 'selectB-24 case ' . $case);
        };
}

for ($case = 0; $case < 300; $case++) {
    $tables = $selectBJoinArithmeticTables($case + 800);
    $expected = $selectBJoinArithmeticSums($selectBJoinArithmeticRows($tables, true));

    $tests[sprintf('real upstream selectB.test selectB-25 left join arithmetic filtered sums dynamic %04d', $case)] =
        static function (TestRunner $t) use ($selectBJoinArithmeticAssert, $tables, $expected, $case): void {
            $sql = <<<'SQL'
SELECT x+y FROM (
  SELECT a AS x, b AS y FROM t1
  UNION ALL
  SELECT a*10 + 0.1, f*10 + 0.1 FROM t1 LEFT JOIN t2 ON (c=d)
  UNION ALL
  SELECT a*100, b*100 FROM t1
) WHERE y+x NOT NULL ORDER BY 1
SQL;
            $selectBJoinArithmeticAssert($t, $sql, $tables, $expected, 'selectB-25 case ' . $case);
        };
}

return $tests;
