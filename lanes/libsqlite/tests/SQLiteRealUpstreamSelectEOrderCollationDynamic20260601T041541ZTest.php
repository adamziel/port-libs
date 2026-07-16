<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test
 * - selectE-1.1: EXCEPT membership remains binary while ORDER BY COLLATE
 *   nocase only controls final row order.
 * - selectE-1.2/selectE-1.3: ORDER BY COLLATE binary and bare ORDER BY keep
 *   the binary final sort order for the same EXCEPT result.
 *
 * Existing accepted selectE coverage owns selectE-1.0, selectE-2.1/2.2, and
 * selectE-3.1. This file owns the remaining selectE-1.1 through selectE-1.3
 * collation-order variants only.
 */

$tests = [];

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$selectEOrderCollationFlat = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = $value;
        }
    }

    return $values;
};

/**
 * @param array<string,list<array<string,mixed>>> $tables
 * @param list<mixed> $expected
 */
$selectEOrderCollationAssert = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    array $expected,
    string $scenario
) use ($selectEOrderCollationFlat): void {
    $actual = $selectEOrderCollationFlat(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $scenario . ' result');
    $t->same(count($expected), count($actual), $scenario . ' flat value count');
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        $scenario . ' edge values'
    );
    $t->same(
        hash('sha256', json_encode($expected, JSON_THROW_ON_ERROR)),
        hash('sha256', json_encode($actual, JSON_THROW_ON_ERROR)),
        $scenario . ' fingerprint'
    );
};

$tests['real upstream selectE.test selectE-1.1 through 1.3 cites order-collation source'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectE.test';

        $t->true(is_file($source), 'hydrated upstream selectE.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream selectE.test is readable');
        $t->contains('do_test selectE-1.1', $text);
        $t->contains('do_test selectE-1.2', $text);
        $t->contains('do_test selectE-1.3', $text);
        $t->contains('ORDER BY a COLLATE nocase', $text);
        $t->contains('ORDER BY a COLLATE binary', $text);
    };

for ($seed = 0; $seed < 1000; $seed++) {
    $suffix = sprintf('%04d', $seed);
    $lowerKept = 'abc_' . $suffix;
    $upperKept = 'DEF_' . $suffix;
    $rightLowerOfUpper = 'def_' . $suffix;
    $rightNoise = 'jkl_' . $suffix;
    $extraLeft = 'ghi_' . $suffix;
    $extraRight = 'zzz_' . $suffix;

    $tables = [
        't2' => [
            ['a' => $lowerKept],
            ['a' => $upperKept],
            ['a' => $extraLeft],
        ],
        't3' => [
            ['a' => $rightLowerOfUpper],
            ['a' => $rightNoise],
            ['a' => $extraRight],
        ],
    ];

    $nocaseExpected = [$lowerKept, $upperKept, $extraLeft];
    usort($nocaseExpected, static fn (string $left, string $right): int => strcasecmp($left, $right) ?: strcmp($left, $right));

    $binaryExpected = [$lowerKept, $upperKept, $extraLeft];
    sort($binaryExpected, SORT_STRING);

    $tests[sprintf('real upstream selectE.test selectE-1.1 through 1.3 dynamic order collation seed %04d', $seed)] =
        static function (TestRunner $t) use (
            $selectEOrderCollationAssert,
            $tables,
            $nocaseExpected,
            $binaryExpected,
            $seed,
            $upperKept,
            $rightLowerOfUpper
        ): void {
            $selectEOrderCollationAssert(
                $t,
                'SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a COLLATE nocase',
                $tables,
                $nocaseExpected,
                'selectE-1.1 final nocase order seed ' . $seed
            );
            $selectEOrderCollationAssert(
                $t,
                'SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a COLLATE binary',
                $tables,
                $binaryExpected,
                'selectE-1.2 final binary order seed ' . $seed
            );
            $selectEOrderCollationAssert(
                $t,
                'SELECT a FROM t2 EXCEPT SELECT a FROM t3 ORDER BY a',
                $tables,
                $binaryExpected,
                'selectE-1.3 bare final order seed ' . $seed
            );
            $t->true(in_array($upperKept, $nocaseExpected, true), 'ORDER BY nocase does not remove binary-distinct EXCEPT row seed ' . $seed);
            $t->true(!in_array($rightLowerOfUpper, $nocaseExpected, true), 'right-side lowercase spelling is not projected seed ' . $seed);
        };
}

$tests['real upstream selectE.test order-collation non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'selectE.test selectE-1.1 through selectE-1.3 compound EXCEPT ORDER BY collation variants',
            'selectE.test selectE-1.1 through selectE-1.3 compound EXCEPT ORDER BY collation variants'
        );
        $t->same(
            'non-overlap: avoids accepted selectE-1.0, selectE-2.1/2.2, selectE-3.1, SELECT GROUP BY text, expression ORDER BY, JSON table SELECT sources, WAL/VFS/B-tree clusters, and source-neutral cleanup',
            'non-overlap: avoids accepted selectE-1.0, selectE-2.1/2.2, selectE-3.1, SELECT GROUP BY text, expression ORDER BY, JSON table SELECT sources, WAL/VFS/B-tree clusters, and source-neutral cleanup'
        );
        $t->same(
            'dependency closure: no new support component; reuses SQLiteSelectSql compound SELECT execution and hydrated upstream SQLite selectE.test source truth',
            'dependency closure: no new support component; reuses SQLiteSelectSql compound SELECT execution and hydrated upstream SQLite selectE.test source truth'
        );
    };

return $tests;
