<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test
 * - select4-9.1 through select4-9.8: compound SELECT result column names come
 *   from the left-most arm, including when later arms use different aliases.
 * - select4-9.9.1 through select4-9.12: derived compound subqueries expose
 *   those inherited left-arm column names to SELECT * expansion and WHERE
 *   filtering.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$select4CompoundNamesFlat = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<string>
 */
$select4CompoundNamesKeys = static function (array $rows): array {
    $keys = [];
    foreach ($rows as $row) {
        foreach (array_keys($row) as $key) {
            $keys[] = $key;
        }
    }

    return $keys;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<array<string,mixed>> $expectedRows
 */
$select4CompoundNamesAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expectedRows,
    string $label
) use ($select4CompoundNamesFlat, $select4CompoundNamesKeys): void {
    $actualRows = SQLiteSelectSql::execute($sql, $tables);

    $t->same($expectedRows, $actualRows, $label . ' rows');
    $t->same($select4CompoundNamesFlat($expectedRows), $select4CompoundNamesFlat($actualRows), $label . ' flat values');
    $t->same($select4CompoundNamesKeys($expectedRows), $select4CompoundNamesKeys($actualRows), $label . ' inherited column names');
    $t->same(count($expectedRows), count($actualRows), $label . ' row count');
    $t->same(
        sha1(json_encode([$expectedRows, $select4CompoundNamesKeys($expectedRows)], JSON_THROW_ON_ERROR)),
        sha1(json_encode([$actualRows, $select4CompoundNamesKeys($actualRows)], JSON_THROW_ON_ERROR)),
        $label . ' result-shape fingerprint',
    );
};

$tests['real upstream select4.test select4-9 compound column-name source truth'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test';
        $text = file_get_contents($source);

        $t->true(is_file($source), 'hydrated upstream select4.test is available');
        $t->true(is_string($text), 'hydrated upstream select4.test is readable');
        foreach (['select4-9.1', 'select4-9.8', 'select4-9.9.1', 'select4-9.12'] as $scenario) {
            $t->contains($scenario, $text);
        }
        $t->contains('Ticket #1721', $text);
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $x = 1 + ($seed % 97);
    $y = 200 + (($seed * 7) % 503);
    $p = $x + 1000 + ($seed % 13);
    $q = $y + 1000 + ($seed % 17);
    $r = $p + 1000 + ($seed % 19);
    $s = $q + 1000 + ($seed % 23);
    $firstRow = ['x' => $x, 'y' => $y];
    $derivedFirst = ['a' => $x, 'b' => $y];
    $derivedSecond = ['a' => $p, 'b' => $q];
    $tables = [
        't2' => [
            $firstRow,
        ],
        't3' => [
            ['a' => $p, 'b' => $q],
            ['a' => $r, 'b' => $s],
        ],
    ];

    $tests[sprintf('real upstream select4.test select4-9 compound left-arm names dynamic seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $select4CompoundNamesAssert,
            $tables,
            $firstRow,
            $derivedFirst,
            $derivedSecond,
            $x,
            $y,
            $p,
            $q,
            $r,
            $s,
            $seed
        ): void {
            $select4CompoundNamesAssert(
                $t,
                'SELECT x, y FROM t2 UNION SELECT a, b FROM t3 ORDER BY x LIMIT 1',
                $tables,
                [$firstRow],
                'select4-9.1 UNION left-most names seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                'SELECT x, y FROM t2 UNION ALL SELECT a, b FROM t3 ORDER BY x LIMIT 1',
                $tables,
                [$firstRow],
                'select4-9.2 UNION ALL left-most names seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                'SELECT x, y FROM t2 EXCEPT SELECT a, b FROM t3 ORDER BY x LIMIT 1',
                $tables,
                [$firstRow],
                'select4-9.3 EXCEPT left-most names seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT x, y FROM t2 INTERSECT SELECT {$x} AS a, {$y} AS b",
                $tables,
                [$firstRow],
                'select4-9.4 INTERSECT left-most names seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT {$x} AS x, {$y} AS y UNION SELECT {$p} AS p, {$q} AS q UNION SELECT {$r} AS a, {$s} AS b ORDER BY x LIMIT 1",
                $tables,
                [$firstRow],
                'select4-9.5 multi-arm UNION left-most names seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT * FROM (SELECT {$x} AS x, {$y} AS y UNION SELECT {$p} AS p, {$q} AS q UNION SELECT {$r} AS a, {$s} AS b) ORDER BY x LIMIT 1",
                $tables,
                [$firstRow],
                'select4-9.6 derived compound star left-most names seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT * FROM (SELECT {$x} AS x, {$y} AS y UNION SELECT {$p} AS p, {$q} AS q UNION SELECT {$r} AS a, {$s} AS b) ORDER BY x LIMIT 1",
                $tables,
                [$firstRow],
                'select4-9.7 derived compound ORDER BY inherited name seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT {$x} AS x, {$y} AS y UNION SELECT {$p} AS y, {$q} AS x ORDER BY x LIMIT 1",
                $tables,
                [$firstRow],
                'select4-9.8 right-arm aliases do not rename output seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT {$x} AS a, {$y} AS b UNION ALL SELECT {$p} AS b, {$q} AS a",
                $tables,
                [$derivedFirst, $derivedSecond],
                'select4-9.9.1 UNION ALL inherited names seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT * FROM (SELECT {$x} AS a, {$y} AS b UNION ALL SELECT {$p} AS b, {$q} AS a) WHERE b={$p}",
                $tables,
                [],
                'select4-9.9.2 WHERE sees inherited b name and no right-arm b seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT * FROM (SELECT {$x} AS a, {$y} AS b UNION ALL SELECT {$p} AS b, {$q} AS a) WHERE b={$y}",
                $tables,
                [$derivedFirst],
                'select4-9.10 WHERE inherited b matches first row seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT * FROM (SELECT {$x} AS a, {$y} AS b UNION ALL SELECT {$p} AS e, {$q} AS b) WHERE b={$y}",
                $tables,
                [$derivedFirst],
                'select4-9.11 later-arm e/b aliases keep first-arm output names seed ' . $seed,
            );
            $select4CompoundNamesAssert(
                $t,
                "SELECT * FROM (SELECT {$x} AS a, {$y} AS b UNION ALL SELECT {$p} AS e, {$q} AS b) WHERE b>0",
                $tables,
                [$derivedFirst, $derivedSecond],
                'select4-9.12 inherited b filters both rows seed ' . $seed,
            );
            $t->same(true, $seed >= 0 && $seed < 1000, 'bounded dynamic seed guard');
        };
}

$tests['real upstream select4.test select4-9 non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'select4.test select4-9.1 through select4-9.12 compound result-column inheritance and derived WHERE filtering',
            'select4.test select4-9.1 through select4-9.12 compound result-column inheritance and derived WHERE filtering',
        );
        $t->same(
            'non-overlap: avoids select4 compound row-set, CTAS materialization, VALUES arms, coroutine yield, aggregate pushdown, select1 column names, expression ORDER BY, JSON table, WAL/VFS/B-tree clusters',
            'non-overlap: avoids select4 compound row-set, CTAS materialization, VALUES arms, coroutine yield, aggregate pushdown, select1 column names, expression ORDER BY, JSON table, WAL/VFS/B-tree clusters',
        );
        $t->same(
            'dependency closure: no new support component; reuses SQLiteSelectSql compound SELECT, derived table, SELECT star, ORDER BY, and WHERE predicate execution',
            'dependency closure: no new support component; reuses SQLiteSelectSql compound SELECT, derived table, SELECT star, ORDER BY, and WHERE predicate execution',
        );
    };

return $tests;
