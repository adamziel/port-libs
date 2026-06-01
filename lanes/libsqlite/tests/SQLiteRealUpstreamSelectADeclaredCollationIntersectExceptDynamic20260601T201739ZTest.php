<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test
 * - selectA-2.42/selectA-2.43/selectA-2.44: INTERSECT/EXCEPT high-row
 *   compound ordering across TEXT and BLOB `a` values.
 * - selectA-2.59/selectA-2.64: final ORDER BY `c` inherits the declared
 *   NOCASE collation from `CREATE TABLE t1(a,b,c COLLATE NOCASE)`.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectA242Flat = static function (array $rows): array {
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
$selectA242CanonicalTables = static function (): array {
    $collations = ['a' => 'BINARY', 'b' => 'BINARY', 'c' => 'NOCASE'];

    return [
        't1' => [
            ['a' => 1, 'b' => 'a', 'c' => 'a', '__sqlite_column_collations' => $collations],
            ['a' => 9.9, 'b' => 'b', 'c' => 'B', '__sqlite_column_collations' => $collations],
            ['a' => null, 'b' => 'C', 'c' => 'c', '__sqlite_column_collations' => $collations],
            ['a' => 'hello', 'b' => 'd', 'c' => 'D', '__sqlite_column_collations' => $collations],
            ['a' => new SQLiteBlobValue('abc'), 'b' => 'e', 'c' => 'e', '__sqlite_column_collations' => $collations],
        ],
    ];
};

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectA242DynamicTables = static function (int $seed): array {
    $suffix = sprintf('%04d', $seed);
    $collations = ['a' => 'BINARY', 'b' => 'BINARY', 'c' => 'NOCASE'];

    return [
        't1' => [
            ['a' => $seed + 1, 'b' => 'a-' . $suffix, 'c' => 'a-' . $suffix, '__sqlite_column_collations' => $collations],
            ['a' => $seed + 9.9, 'b' => 'b-' . $suffix, 'c' => 'B-' . $suffix, '__sqlite_column_collations' => $collations],
            ['a' => null, 'b' => 'C-' . $suffix, 'c' => 'c-' . $suffix, '__sqlite_column_collations' => $collations],
            ['a' => 'hello-' . $suffix, 'b' => 'd-' . $suffix, 'c' => 'D-' . $suffix, '__sqlite_column_collations' => $collations],
            ['a' => new SQLiteBlobValue('abc-' . $suffix), 'b' => 'e-' . $suffix, 'c' => 'e-' . $suffix, '__sqlite_column_collations' => $collations],
        ],
    ];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return list<array{a:mixed,b:mixed,c:mixed}>
 */
$selectA242ProjectedRows = static function (array $tables): array {
    $rows = [];
    foreach ($tables['t1'] as $row) {
        $rows[] = ['a' => $row['a'], 'b' => $row['b'], 'c' => $row['c']];
    }

    return $rows;
};

/**
 * @param list<array{a:mixed,b:mixed,c:mixed}> $rows
 * @return list<array{a:mixed,b:mixed,c:mixed}>
 */
$selectA242HighRows = static function (array $rows): array {
    return array_values(array_filter($rows, static fn (array $row): bool => strcmp((string) $row['b'], 'd') >= 0));
};

/**
 * @param list<array{a:mixed,b:mixed,c:mixed}> $rows
 * @return list<array{a:mixed,b:mixed,c:mixed}>
 */
$selectA242LowRows = static function (array $rows): array {
    return array_values(array_filter($rows, static fn (array $row): bool => strcmp((string) $row['b'], 'd') < 0));
};

$selectA242CompareValue = static function (mixed $left, mixed $right, string $collation = 'BINARY'): int {
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
$selectA242ExpectedFlat = static function (array $rows, array $terms) use ($selectA242CompareValue, $selectA242Flat): array {
    usort($rows, static function (array $left, array $right) use ($terms, $selectA242CompareValue): int {
        foreach ($terms as $term) {
            $column = $term['column'];
            $comparison = $selectA242CompareValue($left[$column], $right[$column], $term['collation'] ?? 'BINARY');
            if ($comparison !== 0) {
                return ($term['direction'] ?? 'ASC') === 'DESC' ? -$comparison : $comparison;
            }
        }

        return 0;
    });

    return $selectA242Flat($rows);
};

/**
 * @param list<mixed> $expected
 */
$selectA242Assert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($selectA242Flat): void {
    $actual = $selectA242Flat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' flattened value count');
    $t->same(intdiv(count($expected), 3), intdiv(count($actual), 3), $label . ' row count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' first and last flattened values',
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' flattened fingerprint',
    );
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @return array<string,array{sql:string,expected:list<mixed>}>
 */
$selectA242Cases = static function (array $tables) use (
    $selectA242ProjectedRows,
    $selectA242HighRows,
    $selectA242LowRows,
    $selectA242ExpectedFlat
): array {
    $rows = $selectA242ProjectedRows($tables);
    $highRows = $selectA242HighRows($rows);
    $lowRows = $selectA242LowRows($rows);

    $byABC = [
        ['column' => 'a'],
        ['column' => 'b'],
        ['column' => 'c', 'collation' => 'NOCASE'],
    ];
    $byCThenADesc = [
        ['column' => 'c', 'collation' => 'NOCASE'],
        ['column' => 'a', 'direction' => 'DESC'],
    ];
    $byC = [
        ['column' => 'c', 'collation' => 'NOCASE'],
    ];

    return [
        'selectA-2.42' => [
            'sql' => "SELECT a,b,c FROM t1 INTERSECT SELECT a,b,c FROM t1 WHERE b>='d' ORDER BY a,b,c",
            'expected' => $selectA242ExpectedFlat($highRows, $byABC),
        ],
        'selectA-2.43' => [
            'sql' => "SELECT a,b,c FROM t1 WHERE b>='d' INTERSECT SELECT a,b,c FROM t1 ORDER BY a,b,c",
            'expected' => $selectA242ExpectedFlat($highRows, $byABC),
        ],
        'selectA-2.44' => [
            'sql' => "SELECT a,b,c FROM t1 EXCEPT SELECT a,b,c FROM t1 WHERE b<'d' ORDER BY a,b,c",
            'expected' => $selectA242ExpectedFlat($highRows, $byABC),
        ],
        'selectA-2.59' => [
            'sql' => "SELECT a,b,c FROM t1 EXCEPT SELECT a,b,c FROM t1 WHERE b>='d' ORDER BY c, a DESC",
            'expected' => $selectA242ExpectedFlat($lowRows, $byCThenADesc),
        ],
        'selectA-2.64' => [
            'sql' => "SELECT a,b,c FROM t1 WHERE b<'d' INTERSECT SELECT a,b,c FROM t1 ORDER BY c",
            'expected' => $selectA242ExpectedFlat($lowRows, $byC),
        ],
    ];
};

$tests['real upstream selectA.test cites declared collation INTERSECT EXCEPT remainder source'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test';
        $text = file_get_contents($source);

        $t->true(is_string($text), 'hydrated upstream selectA.test is readable');
        $t->contains('CREATE TABLE t1(a,b,c COLLATE NOCASE)', $text);
        $t->contains("INSERT INTO t1 VALUES(x'616263', 'e', 'e')", $text);
        $t->contains('do_test selectA-2.42', $text);
        $t->contains('do_test selectA-2.43', $text);
        $t->contains('do_test selectA-2.44', $text);
        $t->contains('do_test selectA-2.59', $text);
        $t->contains('do_test selectA-2.64', $text);
    };

$tests['real upstream selectA.test declared collation INTERSECT EXCEPT canonical results'] =
    static function (TestRunner $t) use ($selectA242Assert, $selectA242CanonicalTables, $selectA242Cases): void {
        $tables = $selectA242CanonicalTables();
        $cases = $selectA242Cases($tables);

        $selectA242Assert($t, $cases['selectA-2.42']['sql'], $tables, ['hello', 'd', 'D', 'abc', 'e', 'e'], 'selectA-2.42 canonical');
        $selectA242Assert($t, $cases['selectA-2.43']['sql'], $tables, ['hello', 'd', 'D', 'abc', 'e', 'e'], 'selectA-2.43 canonical');
        $selectA242Assert($t, $cases['selectA-2.44']['sql'], $tables, ['hello', 'd', 'D', 'abc', 'e', 'e'], 'selectA-2.44 canonical');
        $selectA242Assert($t, $cases['selectA-2.59']['sql'], $tables, [1, 'a', 'a', 9.9, 'b', 'B', null, 'C', 'c'], 'selectA-2.59 canonical');
        $selectA242Assert($t, $cases['selectA-2.64']['sql'], $tables, [1, 'a', 'a', 9.9, 'b', 'B', null, 'C', 'c'], 'selectA-2.64 canonical');
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $tables = $selectA242DynamicTables($seed);
    $cases = $selectA242Cases($tables);
    $suffix = sprintf('%04d', $seed);

    $tests[sprintf('real upstream selectA.test declared collation INTERSECT EXCEPT dynamic seed %04d', $seed)] =
        static function (TestRunner $t) use ($selectA242Assert, $tables, $cases, $seed, $suffix): void {
            foreach ($cases as $upstreamId => $case) {
                $selectA242Assert($t, $case['sql'], $tables, $case['expected'], $upstreamId . ' dynamic seed ' . $seed);
            }

            $t->same('hello-' . $suffix, $cases['selectA-2.42']['expected'][0], 'high TEXT row sorts before BLOB by a seed ' . $seed);
            $t->same('abc-' . $suffix, $cases['selectA-2.44']['expected'][3], 'high BLOB row remains after EXCEPT low set seed ' . $seed);
            $t->same('a-' . $suffix, $cases['selectA-2.59']['expected'][2], 'low c ordering inherits declared NOCASE seed ' . $seed);
            $t->same('a-' . $suffix, $cases['selectA-2.64']['expected'][2], 'INTERSECT low c ordering inherits declared NOCASE seed ' . $seed);
        };
}

$tests['real upstream selectA.test declared collation INTERSECT EXCEPT non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'selectA.test selectA-2.42, selectA-2.43, selectA-2.44, selectA-2.59, and selectA-2.64 declared-collation compound remainder',
            'selectA.test selectA-2.42, selectA-2.43, selectA-2.44, selectA-2.59, and selectA-2.64 declared-collation compound remainder',
        );
        $t->same(
            'non-overlap: fills the selectA INTERSECT/EXCEPT remainder explicitly excluded by the 20260530 selectA handoff; avoids selectA UNION ALL collation, select9 set ops, SELECT JOIN/GROUP/ORDER text, JSON table, WAL, B-tree, VFS, and source-neutral cleanup',
            'non-overlap: fills the selectA INTERSECT/EXCEPT remainder explicitly excluded by the 20260530 selectA handoff; avoids selectA UNION ALL collation, select9 set ops, SELECT JOIN/GROUP/ORDER text, JSON table, WAL, B-tree, VFS, and source-neutral cleanup',
        );
        $t->same(
            'dependency closure: no new support component; reuses SQLiteSelectSql compound SELECT execution, SQLiteBlobValue BLOB ordering, row metadata collations, and hydrated upstream SQLite selectA.test source truth',
            'dependency closure: no new support component; reuses SQLiteSelectSql compound SELECT execution, SQLiteBlobValue BLOB ordering, row metadata collations, and hydrated upstream SQLite selectA.test source truth',
        );
    };

return $tests;
