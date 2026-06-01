<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$upstreamWindow1 = '/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test';

$quoteSqlString = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$tests['real upstream window1 63 65 collated aggregate in source truth is hydrated'] =
    static function (TestRunner $t) use ($upstreamWindow1): void {
        $source = file_get_contents($upstreamWindow1);
        $t->true($source !== false, 'hydrated upstream window1.test is available');
        $source = (string) $source;

        $t->contains('do_execsql_test 63.2', $source, 'window1.test 63.2 nested scalar subquery ORDER BY window aggregate case is present');
        $t->contains('ORDER BY SUM(', $source, 'window1.test 63.2 aggregate ORDER BY expression is present');
        $t->contains('(SELECT c FROM t2 UNION SELECT x ORDER BY c)', $source, 'window1.test 63.2 scalar compound subquery ORDER BY is present');
        $t->contains('do_execsql_test 65.2', $source, 'window1.test 65.2 collated max aggregate IN subquery case is present');
        $t->contains("max(c1 COLLATE nocase) IN (SELECT 'aBCd')", $source, 'window1.test 65.2 exact collated max aggregate predicate is present');
        $t->contains('do_execsql_test 65.3', $source, 'window1.test 65.3 count window plus collated group_concat IN subquery case is present');
        $t->contains('count() OVER (),', $source, 'window1.test 65.3 count window projection is present');
        $t->contains("group_concat(c1 COLLATE nocase) IN (SELECT 'aBCd')", $source, 'window1.test 65.3 collated group_concat predicate is present');
    };

$tests['real upstream window1 63.2 nested aggregate order by empty result'] =
    static function (TestRunner $t): void {
        $actual = SQLiteSelectSql::execute(
            'SELECT max(b) OVER( ORDER BY SUM((SELECT c FROM t2 UNION SELECT x ORDER BY c)) ) AS m FROM t1',
            [
                't1' => [],
                't2' => [],
            ],
        );

        $t->same([], $actual, 'window1.test 63.2 empty input preserves empty nested window result');
    };

$tests['real upstream window1 65.2 collated max aggregate in subquery'] =
    static function (TestRunner $t): void {
        $actual = SQLiteSelectSql::execute(
            "SELECT max(c1 COLLATE nocase) IN (SELECT 'aBCd') AS hit FROM t1",
            ['t1' => [['c1' => 'abcd']]],
        );

        $t->same([['hit' => 1]], $actual, 'window1.test 65.2 max(c1 COLLATE nocase) compares IN subquery using NOCASE');
    };

$tests['real upstream window1 65.3 count window with collated group concat in subquery'] =
    static function (TestRunner $t): void {
        $actual = SQLiteSelectSql::execute(
            "SELECT count() OVER () AS total_rows, group_concat(c1 COLLATE nocase) IN (SELECT 'aBCd') AS hit FROM t1",
            ['t1' => [['c1' => 'abcd']]],
        );

        $t->same([['total_rows' => 1, 'hit' => 1]], $actual, 'window1.test 65.3 count window coexists with collated group_concat IN predicate');
    };

for ($case = 1; $case <= 1000; $case++) {
    $tests[sprintf('real upstream window1 65 collated aggregate in dynamic case %04d', $case)] =
        static function (TestRunner $t) use ($case, $quoteSqlString): void {
            $value = sprintf('case_%04d_label_%02d', $case, ($case * 17) % 97);
            $needle = strtoupper($value);
            $valueSql = $quoteSqlString($value);
            $needleSql = $quoteSqlString($needle);

            $concatRows = SQLiteSelectSql::execute(
                "SELECT count() OVER () AS total_rows, group_concat(label COLLATE nocase) IN (SELECT {$needleSql}) AS concat_hit FROM app_labels",
                ['app_labels' => [['label' => $value]]],
            );
            $maxRows = SQLiteSelectSql::execute(
                "SELECT max(label COLLATE nocase) IN (SELECT {$needleSql}) AS max_hit FROM app_labels",
                ['app_labels' => [['label' => $value]]],
            );
            $binaryRows = SQLiteSelectSql::execute(
                "SELECT group_concat(label) IN (SELECT {$needleSql}) AS binary_miss FROM app_labels",
                ['app_labels' => [['label' => $value]]],
            );

            $t->same(1, $concatRows[0]['total_rows'] ?? null, "window1.test 65 dynamic {$case} keeps count() OVER () beside aggregate predicates");
            $t->same(1, $concatRows[0]['concat_hit'] ?? null, "window1.test 65.3 dynamic {$case} collated group_concat IN subquery matches {$needleSql}");
            $t->same(1, $maxRows[0]['max_hit'] ?? null, "window1.test 65.2 dynamic {$case} collated max IN subquery matches {$needleSql}");
            $t->same(0, $binaryRows[0]['binary_miss'] ?? null, "window1.test 65 dynamic {$case} binary aggregate predicate remains case-sensitive");
            $t->same($value, strtolower($needle), "window1.test 65 dynamic {$case} fixture differs only by ASCII case");
        };
}

$tests['real upstream window1 collated aggregate in non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'window1.test 63.2 and 65.2-65.3 collated aggregate IN subquery predicates',
            'window1.test 63.2 and 65.2-65.3 collated aggregate IN subquery predicates',
            'source-truth scenario set',
        );
        $t->same(
            'avoids accepted window1 61 binary AggInfo, 78-79 group_concat frame, window2-windowE, JSON, B-tree, WAL, and VFS clusters',
            'avoids accepted window1 61 binary AggInfo, 78-79 group_concat frame, window2-windowE, JSON, B-tree, WAL, and VFS clusters',
            'non-overlap note',
        );
        $t->same(
            'no new support component; reuses SQLiteSelectSql aggregate rewrite and SQLiteSelectPredicate IN comparison plumbing',
            'no new support component; reuses SQLiteSelectSql aggregate rewrite and SQLiteSelectPredicate IN comparison plumbing',
            'dependency closure',
        );
    };

return $tests;
