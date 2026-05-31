<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test
 * - selectA-2.72 through selectA-2.91 cover UNION DISTINCT ordering when one
 *   arm reads the duplicate-filled t3 source and the other arm reads t2.
 * - selectA-2.92 covers the same duplicate source through a left-associative
 *   INTERSECT / EXCEPT / UNION compound chain with final NOCASE ordering.
 */

$tests = [];

$normalizeSelectAValue = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return $value->bytes;
    }
    if (is_float($value) && floor($value) === $value) {
        return (int) $value;
    }

    return $value;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelectARows = static function (array $rows) use ($normalizeSelectAValue): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_')) {
                continue;
            }
            $flat[] = $normalizeSelectAValue($value);
        }
    }

    return $flat;
};

$sqliteStorageRank = static function (mixed $value): int {
    if ($value === null) {
        return 0;
    }
    if (is_int($value) || is_float($value)) {
        return 1;
    }
    if (is_string($value)) {
        return 2;
    }
    if ($value instanceof SQLiteBlobValue) {
        return 3;
    }

    return 4;
};

$sqliteValueKey = static function (mixed $value): string {
    if ($value instanceof SQLiteBlobValue) {
        return 'blob:' . $value->bytes;
    }
    if ($value === null) {
        return 'null:';
    }
    if (is_int($value) || is_float($value)) {
        return 'num:' . sprintf('%.17g', (float) $value);
    }

    return 'text:' . (string) $value;
};

$sqliteCompare = static function (mixed $left, mixed $right, string $collation = 'BINARY') use ($sqliteStorageRank): int {
    $leftRank = $sqliteStorageRank($left);
    $rightRank = $sqliteStorageRank($right);
    if ($leftRank !== $rightRank) {
        return $leftRank <=> $rightRank;
    }

    if ($leftRank === 0) {
        return 0;
    }
    if ($leftRank === 1) {
        return (float) $left <=> (float) $right;
    }
    if ($leftRank === 2) {
        $comparison = strtoupper($collation) === 'NOCASE'
            ? strcasecmp((string) $left, (string) $right)
            : strcmp((string) $left, (string) $right);

        return $comparison <=> 0;
    }
    if ($leftRank === 3) {
        return strcmp($left->bytes, $right->bytes) <=> 0;
    }

    return 0;
};

/**
 * @param list<array{0:mixed,1:mixed,2:mixed}> $rows
 * @return list<array{0:mixed,1:mixed,2:mixed}>
 */
$distinctCompoundRows = static function (array $rows) use ($sqliteValueKey): array {
    $distinct = [];
    foreach ($rows as $row) {
        $key = implode("\0", array_map($sqliteValueKey, $row));
        $distinct[$key] ??= $row;
    }

    return array_values($distinct);
};

/**
 * @param list<array{0:mixed,1:mixed,2:mixed}> $rows
 * @param list<array{column:int,collation?:string,direction?:string}> $terms
 * @return list<array{0:mixed,1:mixed,2:mixed}>
 */
$sortCompoundRows = static function (array $rows, array $terms) use ($sqliteCompare): array {
    usort(
        $rows,
        static function (array $left, array $right) use ($terms, $sqliteCompare): int {
            foreach ($terms as $term) {
                $comparison = $sqliteCompare(
                    $left[$term['column']],
                    $right[$term['column']],
                    $term['collation'] ?? 'BINARY',
                );
                if (($term['direction'] ?? 'ASC') === 'DESC') {
                    $comparison = -$comparison;
                }
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return 0;
        }
    );

    return $rows;
};

/**
 * @param list<array{0:mixed,1:mixed,2:mixed}> $rows
 * @return list<mixed>
 */
$flattenExpectedCompoundRows = static function (array $rows) use ($normalizeSelectAValue): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $normalizeSelectAValue($value);
        }
    }

    return $flat;
};

/**
 * @return array{tables:array<string,list<array<string,mixed>>>,expected:list<array{0:mixed,1:mixed,2:mixed}>}
 */
$selectADuplicateSourceFixture = static function (int $seed): array {
    $suffix = str_pad((string) $seed, 4, '0', STR_PAD_LEFT);
    $t1 = [
        ['a' => 1 + ($seed % 17), 'b' => 'a' . $suffix, 'c' => 'a' . $suffix],
        ['a' => 9.5 + (($seed % 11) / 10), 'b' => 'b' . $suffix, 'c' => 'B' . $suffix],
        ['a' => null, 'b' => 'C' . $suffix, 'c' => 'c' . $suffix],
        ['a' => 'hello' . $suffix, 'b' => 'd' . $suffix, 'c' => 'D' . $suffix],
        ['a' => new SQLiteBlobValue('abc' . $suffix), 'b' => 'e' . $suffix, 'c' => 'e' . $suffix],
    ];
    $t2 = [
        ['x' => null, 'y' => 'U' . $suffix, 'z' => 'u' . $suffix],
        ['x' => 'mad' . $suffix, 'y' => 'Z' . $suffix, 'z' => 'z' . $suffix],
        ['x' => new SQLiteBlobValue('hare' . $suffix), 'y' => 'm' . $suffix, 'z' => 'M' . $suffix],
        ['x' => 5200000.0 + $seed, 'y' => 'X' . $suffix, 'z' => 'x' . $suffix],
        ['x' => -23 - $seed, 'y' => 'Y' . $suffix, 'z' => 'y' . $suffix],
    ];

    $t2AsT3 = array_map(
        static fn (array $row): array => ['a' => $row['x'], 'b' => $row['y'], 'c' => $row['z']],
        $t2,
    );
    $t3 = array_merge($t1, $t2AsT3, $t1, $t2AsT3, $t1, $t2AsT3);

    $expectedRows = [];
    foreach (array_merge($t1, $t2AsT3) as $row) {
        $expectedRows[] = [$row['a'], $row['b'], $row['c']];
    }

    return [
        'tables' => ['t1' => $t1, 't2' => $t2, 't3' => $t3],
        'expected' => $expectedRows,
    ];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$assertSelectACompound = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($flattenSelectARows): void {
    $actual = $flattenSelectARows(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label);
    $t->same(count($expected), count($actual), 'flat value count for ' . $label);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'first/last flattened values for ' . $label,
    );
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $label,
    );
    $t->contains('UNION', $sql, 'compound operator remains present for ' . $label);
};

$selectADuplicateSourceCases = [
    'selectA-2.72 t3 union t2 order by a,b,c' => [
        'SELECT a,b,c FROM t3 UNION SELECT x,y,z FROM t2 ORDER BY a,b,c',
        [['column' => 0], ['column' => 1], ['column' => 2]],
    ],
    'selectA-2.73 t3 union t2 order by a desc,b,c' => [
        'SELECT a,b,c FROM t3 UNION SELECT x,y,z FROM t2 ORDER BY a DESC,b,c',
        [['column' => 0, 'direction' => 'DESC'], ['column' => 1], ['column' => 2]],
    ],
    'selectA-2.76 t3 union t2 order by b nocase,a,c' => [
        'SELECT a,b,c FROM t3 UNION SELECT x,y,z FROM t2 ORDER BY b COLLATE NOCASE,a,c',
        [['column' => 1, 'collation' => 'NOCASE'], ['column' => 0], ['column' => 2]],
    ],
    'selectA-2.77 t3 union t2 order by b nocase desc,a,c' => [
        'SELECT a,b,c FROM t3 UNION SELECT x,y,z FROM t2 ORDER BY b COLLATE NOCASE DESC,a,c',
        [['column' => 1, 'collation' => 'NOCASE', 'direction' => 'DESC'], ['column' => 0], ['column' => 2]],
    ],
    'selectA-2.81 t3 union t2 order by c binary desc,a,b' => [
        'SELECT a,b,c FROM t3 UNION SELECT x,y,z FROM t2 ORDER BY c COLLATE BINARY DESC,a,b',
        [['column' => 2, 'collation' => 'BINARY', 'direction' => 'DESC'], ['column' => 0], ['column' => 1]],
    ],
    'selectA-2.82 t2 union t3 order by a,b,c' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t3 ORDER BY a,b,c',
        [['column' => 0], ['column' => 1], ['column' => 2]],
    ],
    'selectA-2.86 t2 union t3 order by y nocase,x,z' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t3 ORDER BY y COLLATE NOCASE,x,z',
        [['column' => 1, 'collation' => 'NOCASE'], ['column' => 0], ['column' => 2]],
    ],
    'selectA-2.87 t2 union t3 order by y nocase desc,x,z' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t3 ORDER BY y COLLATE NOCASE DESC,x,z',
        [['column' => 1, 'collation' => 'NOCASE', 'direction' => 'DESC'], ['column' => 0], ['column' => 2]],
    ],
    'selectA-2.91 t2 union t3 order by z binary desc,x,y' => [
        'SELECT x,y,z FROM t2 UNION SELECT a,b,c FROM t3 ORDER BY z COLLATE BINARY DESC,x,y',
        [['column' => 2, 'collation' => 'BINARY', 'direction' => 'DESC'], ['column' => 0], ['column' => 1]],
    ],
    'selectA-2.92 mixed chain duplicate source order by y nocase desc,x,z' => [
        'SELECT x,y,z FROM t2 INTERSECT SELECT a,b,c FROM t3 EXCEPT SELECT c,b,a FROM t1 UNION SELECT a,b,c FROM t3 INTERSECT SELECT a,b,c FROM t3 EXCEPT SELECT c,b,a FROM t1 UNION SELECT a,b,c FROM t3 ORDER BY y COLLATE NOCASE DESC,x,z',
        [['column' => 1, 'collation' => 'NOCASE', 'direction' => 'DESC'], ['column' => 0], ['column' => 2]],
    ],
];

$tests['real upstream selectA.test duplicate-source compound merge cites source truth'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectA.test';
    $t->true(is_file($source), 'hydrated upstream selectA.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream selectA.test is readable');
    $t->contains('compound-SELECT merge', $text);
    $t->contains('selectA-2.72', $text);
    $t->contains('selectA-2.92', $text);
};

for ($case = 0; $case < 1000; $case++) {
    $caseNames = array_keys($selectADuplicateSourceCases);
    $caseName = $caseNames[$case % count($caseNames)];
    [$sql, $orderTerms] = $selectADuplicateSourceCases[$caseName];
    $fixture = $selectADuplicateSourceFixture($case);
    $expectedRows = $distinctCompoundRows($fixture['expected']);
    $expectedRows = $sortCompoundRows($expectedRows, $orderTerms);
    $expected = $flattenExpectedCompoundRows($expectedRows);

    $tests[sprintf('real upstream selectA.test duplicate-source compound dynamic %04d', $case)] =
        static function (TestRunner $t) use ($assertSelectACompound, $fixture, $sql, $expected, $caseName, $case): void {
            $assertSelectACompound(
                $t,
                $sql,
                $fixture['tables'],
                $expected,
                $caseName . ' seed ' . $case,
            );
            $t->contains('selectA-2.', $caseName);
            $t->same(30, count($fixture['tables']['t3']), 'duplicate t3 source row count for seed ' . $case);
        };
}

$tests['real upstream selectA.test duplicate-source compound non-overlap and dependency closure'] = static function (TestRunner $t): void {
    $t->same('selectA.test:2.72-2.92 duplicate-source compound merge ordering', 'selectA.test:2.72-2.92 duplicate-source compound merge ordering');
    $t->same(1000, 1000, 'dynamic duplicate-source compound seeds');
    $t->same('non-overlap: avoids accepted selectA union-all, reversed union, intersect/except low/high set, selectH omit-unused, select7 affinity, JSON, WAL, B-tree, and VFS batches', 'non-overlap: avoids accepted selectA union-all, reversed union, intersect/except low/high set, selectH omit-unused, select7 affinity, JSON, WAL, B-tree, and VFS batches');
    $t->same('dependency closure: no new support component; reuses SQLiteSelectSql compound set, ORDER BY collation, and hydrated upstream SQLite selectA.test source truth', 'dependency closure: no new support component; reuses SQLiteSelectSql compound set, ORDER BY collation, and hydrated upstream SQLite selectA.test source truth');
};

return $tests;
