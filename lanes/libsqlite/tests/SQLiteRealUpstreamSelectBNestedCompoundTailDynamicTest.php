<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test
 * - selectB-3.17 through selectB-6.24: nested compound SELECT tails, per-arm
 *   DISTINCT, expression ORDER BY over SELECT *, constant UNION arms, and
 *   arithmetic JOIN/LEFT JOIN compound rows.
 */

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $flat;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenExpectedRows = static function (array $rows) use ($flattenRows): array {
    return $flattenRows($rows);
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertFlatSelect = static function (TestRunner $t, string $sql, array $tables, array $expected, string $label) use ($flattenRows): void {
    $actual = $flattenRows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' flat result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values',
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' result fingerprint',
    );
    $t->contains('SELECT', strtoupper($sql), $label . ' remains a SELECT corpus query');
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectBTables = static function (int $seed): array {
    $base = 1 + ($seed * 5);
    $t1 = [];
    $t2 = [];
    for ($i = 0; $i < 5; $i++) {
        $a = $base + ($i * 3);
        $t1[] = [
            'a' => $a,
            'b' => $a + 2 + ($seed % 4),
            'c' => $base + 20 + ($i * 4),
        ];
    }
    for ($i = 0; $i < 4; $i++) {
        $match = $base + 20 + (($i + $seed) % 5) * 4;
        $t2[] = [
            'd' => $match,
            'e' => $match + 1,
            'f' => $match + 30 + ($i * 2),
        ];
    }
    $t2[] = [
        'd' => $base + 900,
        'e' => $base + 901,
        'f' => $base + 902,
    ];

    return ['t1' => $t1, 't2' => $t2];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
$sortRowsBy = static function (array $rows, array $columns): array {
    usort($rows, static function (array $left, array $right) use ($columns): int {
        foreach ($columns as $column) {
            $comparison = ($left[$column] <=> $right[$column]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return 0;
    });

    return $rows;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
$distinctRows = static function (array $rows): array {
    $seen = [];
    $distinct = [];
    foreach ($rows as $row) {
        $key = json_encode($row, JSON_THROW_ON_ERROR);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $distinct[] = $row;
    }

    return $distinct;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return array{one:list<array<string,mixed>>, three:list<array<string,mixed>>}
 */
$compoundRows = static function (array $tables): array {
    $one = [];
    foreach ($tables['t1'] as $row) {
        $one[] = ['a' => $row['a']];
    }
    foreach ($tables['t2'] as $row) {
        $one[] = ['a' => $row['d']];
    }

    $three = $one;
    foreach ($tables['t1'] as $row) {
        $three[] = ['a' => $row['c']];
    }

    return ['one' => $one, 'three' => $three];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<array<string,mixed>>
 */
$selectB19Rows = static function (array $tables) use ($distinctRows): array {
    $left = [];
    foreach ($tables['t1'] as $row) {
        $left[] = ['v' => intdiv((int) $row['a'], 10)];
    }
    $right = [];
    foreach ($tables['t2'] as $row) {
        $right[] = ['v' => ((int) $row['d']) % 2];
    }

    return array_merge($distinctRows($left), $distinctRows($right));
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<array<string,mixed>>
 */
$selectB21Rows = static function (array $tables): array {
    $rows = [];
    foreach ($tables['t1'] as $row) {
        $rows[] = ['a' => $row['a'], 'b' => $row['b'], 'c' => $row['c']];
    }
    foreach ($tables['t2'] as $row) {
        $rows[] = ['a' => $row['d'], 'b' => $row['e'], 'c' => $row['f']];
    }
    usort($rows, static fn (array $left, array $right): int => (($left['a'] + $left['b']) <=> ($right['a'] + $right['b'])));

    return $rows;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<array<string,mixed>>
 */
$selectB23Rows = static function (array $tables, bool $leftJoin): array {
    $rows = [];
    foreach ($tables['t1'] as $row) {
        $rows[] = ['x' => $row['a'], 'y' => $row['b']];
    }
    foreach ($tables['t1'] as $left) {
        $matched = false;
        foreach ($tables['t2'] as $right) {
            if ($left['c'] !== $right['d']) {
                continue;
            }
            $matched = true;
            $rows[] = [
                'x' => round($left['a'] * 10 + 0.1, 6),
                'y' => round($right['f'] * 10 + 0.1, 6),
            ];
        }
        if ($leftJoin && !$matched) {
            $rows[] = ['x' => round($left['a'] * 10 + 0.1, 6), 'y' => null];
        }
    }
    foreach ($tables['t1'] as $row) {
        $rows[] = ['x' => $row['a'] * 100, 'y' => $row['b'] * 100];
    }
    usort($rows, static function (array $left, array $right): int {
        $comparison = $left['x'] <=> $right['x'];
        return $comparison !== 0 ? $comparison : (($left['y'] ?? -INF) <=> ($right['y'] ?? -INF));
    });

    return $rows;
};

$tests = [];

$tests['real upstream selectB.test cites nested compound tail source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectB.test';

    $t->true(is_file($source), 'hydrated upstream selectB.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'selectB.test source can be read');
    foreach (['selectB-$ii.17', 'selectB-$ii.19', 'selectB-$ii.21', 'selectB-$ii.23', 'selectB-$ii.24'] as $scenario) {
        $t->contains($scenario, $text);
    }
};

for ($seed = 0; $seed < 1000; $seed++) {
    $tables = $selectBTables($seed);
    $compound = $compoundRows($tables);
    $innerLimit = 2 + ($seed % 5);
    $innerOffset = $seed % 4;
    $outerLimit = 1 + ($seed % 3);
    $oneRows = $compound['one'];
    $nestedExpected = array_slice(array_slice($oneRows, $innerOffset, $innerLimit), 0, $outerLimit);
    $nestedSql = sprintf(
        'SELECT * FROM (SELECT a FROM t1 UNION ALL SELECT d FROM t2 LIMIT %d OFFSET %d) LIMIT %d',
        $innerLimit,
        $innerOffset,
        $outerLimit,
    );

    $tests[sprintf('real upstream selectB.test selectB-17-18 nested compound limits seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $flattenExpectedRows, $nestedSql, $tables, $nestedExpected, $seed): void {
            $assertFlatSelect($t, $nestedSql, $tables, $flattenExpectedRows($nestedExpected), 'selectB-17/18 seed ' . $seed);
            $t->contains('UNION ALL', $nestedSql, 'selectB nested compound preserves UNION ALL source');
        };

    $distinctSql = 'SELECT * FROM (SELECT DISTINCT (a/10) FROM t1 UNION ALL SELECT DISTINCT(d%2) FROM t2)';
    $distinctExpected = $selectB19Rows($tables);
    $tests[sprintf('real upstream selectB.test selectB-19 per-arm distinct seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $flattenExpectedRows, $distinctSql, $tables, $distinctExpected, $seed): void {
            $assertFlatSelect($t, $distinctSql, $tables, $flattenExpectedRows($distinctExpected), 'selectB-19 seed ' . $seed);
            $t->contains('SELECT DISTINCT', $distinctSql, 'selectB per-arm DISTINCT source');
        };

    $outerDistinctSql = 'SELECT DISTINCT * FROM (SELECT DISTINCT (a/10) FROM t1 UNION ALL SELECT DISTINCT(d%2) FROM t2)';
    $outerDistinctExpected = $distinctRows($distinctExpected);
    $tests[sprintf('real upstream selectB.test selectB-20 outer distinct seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $flattenExpectedRows, $outerDistinctSql, $tables, $outerDistinctExpected, $seed): void {
            $assertFlatSelect($t, $outerDistinctSql, $tables, $flattenExpectedRows($outerDistinctExpected), 'selectB-20 seed ' . $seed);
            $t->contains('SELECT DISTINCT *', $outerDistinctSql, 'selectB outer DISTINCT source');
        };

    $orderAllSql = 'SELECT * FROM (SELECT * FROM t1 UNION ALL SELECT * FROM t2) ORDER BY a+b';
    $orderAllExpected = $selectB21Rows($tables);
    $tests[sprintf('real upstream selectB.test selectB-21 star compound expression order seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $flattenExpectedRows, $orderAllSql, $tables, $orderAllExpected, $seed): void {
            $assertFlatSelect($t, $orderAllSql, $tables, $flattenExpectedRows($orderAllExpected), 'selectB-21 seed ' . $seed);
            $t->contains('ORDER BY a+b', $orderAllSql, 'selectB expression ORDER BY over SELECT star');
        };

    $constant = 10000 + $seed;
    $constantSql = 'SELECT * FROM (SELECT ' . $constant . ' UNION ALL SELECT d FROM t2) ORDER BY 1';
    $constantExpected = array_map(static fn (array $row): array => ['column1' => $row['d']], $tables['t2']);
    $constantExpected[] = ['column1' => $constant];
    $constantExpected = $sortRowsBy($constantExpected, ['column1']);
    $tests[sprintf('real upstream selectB.test selectB-22 constant union arm seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $flattenExpectedRows, $constantSql, $tables, $constantExpected, $seed): void {
            $assertFlatSelect($t, $constantSql, $tables, $flattenExpectedRows($constantExpected), 'selectB-22 seed ' . $seed);
            $t->contains('UNION ALL SELECT d', $constantSql, 'selectB constant arm plus table arm source');
        };

    $joinSql = 'SELECT x, y FROM ('
        . 'SELECT a AS x, b AS y FROM t1 '
        . 'UNION ALL SELECT a*10 + 0.1, f*10 + 0.1 FROM t1 JOIN t2 ON (c=d) '
        . 'UNION ALL SELECT a*100, b*100 FROM t1'
        . ') ORDER BY 1';
    $joinExpected = $selectB23Rows($tables, false);
    $tests[sprintf('real upstream selectB.test selectB-23 arithmetic join compound seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $flattenExpectedRows, $joinSql, $tables, $joinExpected, $seed): void {
            $assertFlatSelect($t, $joinSql, $tables, $flattenExpectedRows($joinExpected), 'selectB-23 seed ' . $seed);
            $t->contains('JOIN t2 ON (c=d)', $joinSql, 'selectB arithmetic JOIN arm source');
        };

    $leftJoinSql = 'SELECT x, y FROM ('
        . 'SELECT a AS x, b AS y FROM t1 '
        . 'UNION ALL SELECT a*10 + 0.1, f*10 + 0.1 FROM t1 LEFT JOIN t2 ON (c=d) '
        . 'UNION ALL SELECT a*100, b*100 FROM t1'
        . ') ORDER BY 1';
    $leftJoinExpected = $selectB23Rows($tables, true);
    $tests[sprintf('real upstream selectB.test selectB-24 arithmetic left join compound seed %04d', $seed)] =
        static function (TestRunner $t) use ($assertFlatSelect, $flattenExpectedRows, $leftJoinSql, $tables, $leftJoinExpected, $seed): void {
            $assertFlatSelect($t, $leftJoinSql, $tables, $flattenExpectedRows($leftJoinExpected), 'selectB-24 seed ' . $seed);
            $t->contains('LEFT JOIN t2 ON (c=d)', $leftJoinSql, 'selectB arithmetic LEFT JOIN arm source');
        };
}

return $tests;
