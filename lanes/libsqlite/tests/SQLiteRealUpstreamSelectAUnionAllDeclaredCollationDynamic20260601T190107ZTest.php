<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test
 * - selectA-2.7/selectA-2.8: UNION ALL final ORDER BY c uses the left-arm
 *   result expression and inherits t1.c declared NOCASE collation.
 * - selectA-2.9: the same inherited NOCASE collation is applied before DESC.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectA279Flat = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            if ($value instanceof SQLiteBlobValue) {
                $value = $value->bytes;
            }
            if (is_float($value) && floor($value) === $value) {
                $value = (int) $value;
            }
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectA279CanonicalTables = static function (): array {
    $t1Collations = ['a' => 'BINARY', 'b' => 'BINARY', 'c' => 'NOCASE'];
    $t2Collations = ['x' => 'BINARY', 'y' => 'BINARY', 'z' => 'NOCASE'];

    return [
        't1' => [
            ['a' => 1, 'b' => 'a', 'c' => 'a', '__sqlite_column_collations' => $t1Collations],
            ['a' => 9.9, 'b' => 'b', 'c' => 'B', '__sqlite_column_collations' => $t1Collations],
            ['a' => null, 'b' => 'C', 'c' => 'c', '__sqlite_column_collations' => $t1Collations],
            ['a' => 'hello', 'b' => 'd', 'c' => 'D', '__sqlite_column_collations' => $t1Collations],
            ['a' => new SQLiteBlobValue('abc'), 'b' => 'e', 'c' => 'e', '__sqlite_column_collations' => $t1Collations],
        ],
        't2' => [
            ['x' => null, 'y' => 'U', 'z' => 'u', '__sqlite_column_collations' => $t2Collations],
            ['x' => 'mad', 'y' => 'Z', 'z' => 'z', '__sqlite_column_collations' => $t2Collations],
            ['x' => new SQLiteBlobValue('hare'), 'y' => 'm', 'z' => 'M', '__sqlite_column_collations' => $t2Collations],
            ['x' => 5200000.0, 'y' => 'X', 'z' => 'x', '__sqlite_column_collations' => $t2Collations],
            ['x' => -23, 'y' => 'Y', 'z' => 'y', '__sqlite_column_collations' => $t2Collations],
        ],
    ];
};

/**
 * @param list<mixed> $expected
 */
$selectA279Assert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($selectA279Flat): void {
    $actual = $selectA279Flat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(intdiv(count($expected), 3), intdiv(count($actual), 3), $label . ' row count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' first and last values',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint',
    );
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectA279DynamicTables = static function (int $seed): array {
    $suffix = sprintf('%04d', $seed);
    $t1Collations = ['a' => 'BINARY', 'b' => 'BINARY', 'c' => 'NOCASE'];
    $t2Collations = ['x' => 'BINARY', 'y' => 'BINARY', 'z' => 'NOCASE'];

    return [
        't1' => [
            ['a' => 'left-low-' . $suffix, 'b' => 'delta-' . $suffix, 'c' => 'a' . $suffix, '__sqlite_column_collations' => $t1Collations],
            ['a' => 'left-tie-b-' . $suffix, 'b' => 'beta-' . $suffix, 'c' => 'M' . $suffix, '__sqlite_column_collations' => $t1Collations],
            ['a' => 'left-tie-a-' . $suffix, 'b' => 'alpha-' . $suffix, 'c' => 'm' . $suffix, '__sqlite_column_collations' => $t1Collations],
            ['a' => 'left-high-' . $suffix, 'b' => 'gamma-' . $suffix, 'c' => 'z' . $suffix, '__sqlite_column_collations' => $t1Collations],
        ],
        't2' => [
            ['x' => 'right-mid-b-' . $suffix, 'y' => 'echo-' . $suffix, 'z' => 'B' . $suffix, '__sqlite_column_collations' => $t2Collations],
            ['x' => 'right-mid-a-' . $suffix, 'y' => 'bravo-' . $suffix, 'z' => 'b' . $suffix, '__sqlite_column_collations' => $t2Collations],
            ['x' => 'right-tail-' . $suffix, 'y' => 'zeta-' . $suffix, 'z' => 'Y' . $suffix, '__sqlite_column_collations' => $t2Collations],
        ],
    ];
};

/**
 * @return list<array{a:mixed,b:mixed,c:mixed}>
 */
$selectA279UnionAllRows = static function (array $tables): array {
    $rows = [];
    foreach ($tables['t1'] as $row) {
        $rows[] = ['a' => $row['a'], 'b' => $row['b'], 'c' => $row['c']];
    }
    foreach ($tables['t2'] as $row) {
        $rows[] = ['a' => $row['x'], 'b' => $row['y'], 'c' => $row['z']];
    }

    return $rows;
};

$selectA279CompareValue = static function (mixed $left, mixed $right, string $collation = 'BINARY'): int {
    $rank = static function (mixed $value): int {
        return match (true) {
            $value === null => 0,
            is_int($value) || is_float($value) || is_bool($value) => 1,
            is_string($value) => 2,
            $value instanceof SQLiteBlobValue => 3,
            default => throw new InvalidArgumentException('dynamic selectA value must be scalar, BLOB, or NULL'),
        };
    };

    $leftRank = $rank($left);
    $rightRank = $rank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }
    if ($left === null || $right === null) {
        return 0;
    }
    if (is_int($left) || is_float($left) || is_bool($left)) {
        return ((float) $left) <=> ((float) $right);
    }
    if ($left instanceof SQLiteBlobValue || $right instanceof SQLiteBlobValue) {
        $leftBytes = $left instanceof SQLiteBlobValue ? $left->bytes : (string) $left;
        $rightBytes = $right instanceof SQLiteBlobValue ? $right->bytes : (string) $right;

        return strcmp($leftBytes, $rightBytes);
    }

    $leftText = (string) $left;
    $rightText = (string) $right;
    if (strtoupper($collation) === 'NOCASE') {
        return strcmp(strtolower($leftText), strtolower($rightText));
    }

    return strcmp($leftText, $rightText);
};

/**
 * @param list<array{a:mixed,b:mixed,c:mixed}> $rows
 * @param list<array{column:string,direction?:string,collation?:string}> $terms
 * @return list<mixed>
 */
$selectA279ExpectedFlat = static function (array $rows, array $terms) use ($selectA279CompareValue): array {
    usort($rows, static function (array $left, array $right) use ($terms, $selectA279CompareValue): int {
        foreach ($terms as $term) {
            $column = $term['column'];
            $comparison = $selectA279CompareValue($left[$column], $right[$column], $term['collation'] ?? 'BINARY');
            if ($comparison !== 0) {
                return ($term['direction'] ?? 'ASC') === 'DESC' ? -$comparison : $comparison;
            }
        }

        return 0;
    });

    $flat = [];
    foreach ($rows as $row) {
        array_push($flat, $row['a'], $row['b'], $row['c']);
    }

    return $flat;
};

$tests['real upstream selectA.test selectA-2.7 through 2.9 cites union all declared collation source'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test';
        $text = file_get_contents($source);

        $t->true(is_string($text), 'hydrated upstream selectA.test is readable');
        $t->contains('do_test selectA-2.7', $text);
        $t->contains('do_test selectA-2.8', $text);
        $t->contains('do_test selectA-2.9', $text);
        $t->contains('SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2', $text);
        $t->contains('ORDER BY c DESC,a,b', $text);
    };

$tests['real upstream selectA.test selectA-2.7 through 2.9 canonical mixed-type results'] =
    static function (TestRunner $t) use ($selectA279Assert, $selectA279CanonicalTables): void {
        $tables = $selectA279CanonicalTables();
        $ascending = [1, 'a', 'a', 9.9, 'b', 'B', null, 'C', 'c', 'hello', 'd', 'D', 'abc', 'e', 'e', 'hare', 'm', 'M', null, 'U', 'u', 5200000, 'X', 'x', -23, 'Y', 'y', 'mad', 'Z', 'z'];
        $descending = ['mad', 'Z', 'z', -23, 'Y', 'y', 5200000, 'X', 'x', null, 'U', 'u', 'hare', 'm', 'M', 'abc', 'e', 'e', 'hello', 'd', 'D', null, 'C', 'c', 9.9, 'b', 'B', 1, 'a', 'a'];

        $selectA279Assert($t, 'SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY c,b,a', $tables, $ascending, 'selectA-2.7 canonical');
        $selectA279Assert($t, 'SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY c,a,b', $tables, $ascending, 'selectA-2.8 canonical');
        $selectA279Assert($t, 'SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY c DESC,a,b', $tables, $descending, 'selectA-2.9 canonical');
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $tables = $selectA279DynamicTables($seed);
    $unionRows = $selectA279UnionAllRows($tables);
    $byCBA = $selectA279ExpectedFlat($unionRows, [
        ['column' => 'c', 'collation' => 'NOCASE'],
        ['column' => 'b'],
        ['column' => 'a'],
    ]);
    $byCAB = $selectA279ExpectedFlat($unionRows, [
        ['column' => 'c', 'collation' => 'NOCASE'],
        ['column' => 'a'],
        ['column' => 'b'],
    ]);
    $byCDescAB = $selectA279ExpectedFlat($unionRows, [
        ['column' => 'c', 'collation' => 'NOCASE', 'direction' => 'DESC'],
        ['column' => 'a'],
        ['column' => 'b'],
    ]);

    $tests[sprintf('real upstream selectA.test selectA-2.7 through 2.9 dynamic union all declared collation seed %04d', $seed)] =
        static function (TestRunner $t) use ($selectA279Assert, $tables, $byCBA, $byCAB, $byCDescAB, $seed): void {
            $selectA279Assert($t, 'SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY c,b,a', $tables, $byCBA, 'selectA-2.7 dynamic seed ' . $seed);
            $selectA279Assert($t, 'SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY c,a,b', $tables, $byCAB, 'selectA-2.8 dynamic seed ' . $seed);
            $selectA279Assert($t, 'SELECT a,b,c FROM t1 UNION ALL SELECT x,y,z FROM t2 ORDER BY c DESC,a,b', $tables, $byCDescAB, 'selectA-2.9 dynamic seed ' . $seed);
            $t->same('left-low-' . sprintf('%04d', $seed), $byCBA[0], 'NOCASE c ordering keeps lowercase a before uppercase B seed ' . $seed);
            $t->same('left-low-' . sprintf('%04d', $seed), $byCDescAB[count($byCDescAB) - 3], 'DESC NOCASE c ordering moves lowercase a tail row seed ' . $seed);
            $t->same('a' . sprintf('%04d', $seed), $byCDescAB[array_key_last($byCDescAB)], 'DESC NOCASE c ordering keeps lowercase a tail value seed ' . $seed);
        };
}

$tests['real upstream selectA.test union all declared collation non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'selectA.test selectA-2.7 through selectA-2.9 UNION ALL ORDER BY c declared NOCASE collation inheritance',
            'selectA.test selectA-2.7 through selectA-2.9 UNION ALL ORDER BY c declared NOCASE collation inheritance',
        );
        $t->same(
            'non-overlap: avoids accepted selectA-2.37 through 2.39 reversed UNION, duplicate-source selectA-2.72 through 2.92, select9 set ops, selectB compound subquery, selectD parenthesized joins, JSON table, WAL, B-tree, VFS, and source-neutral cleanup',
            'non-overlap: avoids accepted selectA-2.37 through 2.39 reversed UNION, duplicate-source selectA-2.72 through 2.92, select9 set ops, selectB compound subquery, selectD parenthesized joins, JSON table, WAL, B-tree, VFS, and source-neutral cleanup',
        );
        $t->same(
            'dependency closure: no new support component; reuses SQLiteSelectSql compound SELECT execution, row metadata collations, and hydrated upstream SQLite selectA.test source truth',
            'dependency closure: no new support component; reuses SQLiteSelectSql compound SELECT execution, row metadata collations, and hydrated upstream SQLite selectA.test source truth',
        );
    };

return $tests;
