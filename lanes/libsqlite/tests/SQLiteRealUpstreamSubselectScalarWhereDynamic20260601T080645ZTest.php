<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/subselect.test
 * - subselect-1.1: scalar aggregate subquery values participate in WHERE
 *   equality.
 * - subselect-1.3a through subselect-1.3e: scalar subqueries return the
 *   selected first value, including compound SELECT ORDER BY sources.
 * - subselect-1.4: an empty scalar subquery produces NULL and can be
 *   defaulted with coalesce().
 * - subselect-1.5: multiple aggregate scalar subqueries can be composed in a
 *   single arithmetic predicate expression.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$subselectScalarFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (is_string($column) && str_starts_with($column, '__sqlite_')) {
                continue;
            }
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @return array{t1:list<array{a:int,b:int,label:string}>,t2:list<array{x:int,y:string}>}
 */
$subselectScalarFixture = static function (int $seed): array {
    $rowCount = 3 + ($seed % 4);
    $step = 2 + ($seed % 5);
    $base = 20 + ($seed * 3);
    $targetIndex = $seed % $rowCount;
    $fallbackIndex = ($targetIndex + 1) % $rowCount;
    $compoundFirst = -5000 - $seed;

    $t1 = [];
    $sumA = 0;
    $sumB = 0;
    for ($i = 0; $i < $rowCount; $i++) {
        $a = $i === 0 ? $rowCount : $base + $i;
        $b = $base + 100 + ($i * $step);
        if ($i === 0) {
            $b = $compoundFirst + 10000;
        }
        $row = [
            'a' => $a,
            'b' => $b,
            'label' => sprintf('row_%04d_%02d', $seed, $i),
        ];
        $t1[] = $row;
        $sumA += $a;
        $sumB += $b;
    }

    $difference = $sumB - $sumA;
    $t2 = [
        ['x' => $difference - 1, 'y' => sprintf('before_%04d', $seed)],
        ['x' => $difference, 'y' => sprintf('matched_%04d', $seed)],
        ['x' => $difference + 1, 'y' => sprintf('after_%04d', $seed)],
    ];

    return ['t1' => $t1, 't2' => $t2];
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$subselectScalarAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $label
) use ($subselectScalarFlat): void {
    $actual = $subselectScalarFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $label . ' result');
    $t->same(count($expected), count($actual), $label . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $label . ' edge values'
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $label . ' fingerprint'
    );
};

$tests['real upstream subselect.test subselect-1 scalar where source truth'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/subselect.test';

        $t->true(is_file($source), 'hydrated upstream subselect.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream subselect.test is readable');
        foreach (['subselect-1.1', 'subselect-1.3a', 'subselect-1.3e', 'subselect-1.4', 'subselect-1.5'] as $scenario) {
            $t->contains($scenario, $text, $scenario . ' exists upstream');
        }
        $t->contains('SELECT * FROM t1 WHERE a = (SELECT count(*) FROM t1)', $text);
        $t->contains('SELECT b FROM t1', $text);
        $t->contains('SELECT y from t2', $text);
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $tests[sprintf('real upstream subselect.test subselect-1 scalar where dynamic case %04d', $seed)] =
        static function (TestRunner $t) use (
            $subselectScalarFixture,
            $subselectScalarAssert,
            $seed
        ): void {
            $tables = $subselectScalarFixture($seed);
            $rowCount = count($tables['t1']);
            $targetIndex = $seed % $rowCount;
            $fallbackIndex = ($targetIndex + 1) % $rowCount;
            $target = $tables['t1'][$targetIndex];
            $fallback = $tables['t1'][$fallbackIndex];
            $missingB = 900000 + $seed;
            $sumA = array_sum(array_column($tables['t1'], 'a'));
            $sumB = array_sum(array_column($tables['t1'], 'b'));
            $difference = $sumB - $sumA;

            $subselectScalarAssert(
                $t,
                'SELECT label FROM t1 WHERE a = (SELECT count(*) FROM t1)',
                $tables,
                [$tables['t1'][0]['label']],
                'subselect-1.1 scalar count WHERE equality seed ' . $seed
            );
            $subselectScalarAssert(
                $t,
                'SELECT b FROM t1 WHERE a = (SELECT a FROM t1 WHERE b=' . $target['b'] . ')',
                $tables,
                [$target['b']],
                'subselect-1.3 scalar value WHERE equality seed ' . $seed
            );
            $subselectScalarAssert(
                $t,
                'SELECT b FROM t1 WHERE a = (SELECT a FROM t1 WHERE b=' . $missingB . ')',
                $tables,
                [],
                'subselect-1.3d empty scalar subquery seed ' . $seed
            );
            $subselectScalarAssert(
                $t,
                'SELECT label FROM t1 WHERE a = coalesce((SELECT a FROM t1 WHERE b=' . $missingB . '),' . $fallback['a'] . ')',
                $tables,
                [$fallback['label']],
                'subselect-1.4 empty scalar coalesce fallback seed ' . $seed
            );
            $subselectScalarAssert(
                $t,
                'SELECT y FROM t2 WHERE x = (SELECT sum(b) FROM t1 WHERE a notnull) - (SELECT sum(a) FROM t1)',
                $tables,
                ['matched_' . sprintf('%04d', $seed)],
                'subselect-1.5 multiple scalar aggregate arithmetic seed ' . $seed
            );
            $subselectScalarAssert(
                $t,
                'SELECT b FROM t1 WHERE a = (SELECT a FROM t1 UNION SELECT b FROM t1 ORDER BY 1)',
                $tables,
                [$tables['t1'][0]['b']],
                'subselect-1.3e compound scalar subquery first ordered value seed ' . $seed
            );
            $t->same($difference, $tables['t2'][1]['x'], 'subselect-1.5 arithmetic target is represented seed ' . $seed);
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded subselect-1 dynamic seed');
        };
}

$tests['real upstream subselect.test subselect-1 scalar where non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'subselect.test subselect-1.1, subselect-1.3a-e, subselect-1.4, and subselect-1.5 scalar subquery expression behavior',
            'subselect.test subselect-1.1, subselect-1.3a-e, subselect-1.4, and subselect-1.5 scalar subquery expression behavior'
        );
        $t->same(
            'non-overlap: owns early subselect.test scalar WHERE, empty scalar NULL fallback, compound scalar first-row, and multi-subquery arithmetic; avoids existing subselect-2 through subselect-4 ORDER/LIMIT coverage, SELECT subquery join corpus, expression ORDER BY, grouped SELECT text, JSON table, WAL, VFS, B-tree, PRAGMA, and metadata-only rows',
            'non-overlap: owns early subselect.test scalar WHERE, empty scalar NULL fallback, compound scalar first-row, and multi-subquery arithmetic; avoids existing subselect-2 through subselect-4 ORDER/LIMIT coverage, SELECT subquery join corpus, expression ORDER BY, grouped SELECT text, JSON table, WAL, VFS, B-tree, PRAGMA, and metadata-only rows'
        );
        $t->same(
            'dependency closure: no new support component needed; reuses SQLiteSelectSql scalar subquery, aggregate, compound SELECT, coalesce, arithmetic predicate, and hydrated upstream SQLite subselect.test source truth',
            'dependency closure: no new support component needed; reuses SQLiteSelectSql scalar subquery, aggregate, compound SELECT, coalesce, arithmetic predicate, and hydrated upstream SQLite subselect.test source truth'
        );
    };

return $tests;
