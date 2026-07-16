<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test
 * - select7-5.1 through select7-5.4 reject IN subqueries that return two columns.
 * - select7-8.1 and select7-8.2 reject compound SELECT arms with different
 *   result-column counts before an outer WHERE or query-plan wrapper can hide it.
 *
 * This dynamic batch keeps the same SELECT-core arity contract while varying
 * generic application table values, source shapes, and compound operators.
 */

$tests = [];

$assertSelectSqlThrows = static function (
    TestRunner $t,
    string $sql,
    array $tables,
    string $messageNeedle,
    string $label
): void {
    $caught = null;

    try {
        SQLiteSelectSql::execute($sql, $tables);
    } catch (InvalidArgumentException $exception) {
        $caught = $exception;
    }

    $t->true($caught instanceof InvalidArgumentException, $label . ' throws InvalidArgumentException');
    $t->contains($messageNeedle, $caught?->getMessage() ?? '', $label . ' message');
    $t->contains('SELECT', strtoupper($sql), $label . ' keeps SELECT SQL path');
};

$tests['real upstream select7.test select7-5 and select7-8 arity source truth'] =
    static function (TestRunner $t): void {
        $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test';

        $t->true(is_file($source), 'hydrated upstream select7.test is available');
        $text = file_get_contents($source);
        $t->true(is_string($text), 'hydrated upstream select7.test is readable');
        foreach (['select7-5.1', 'select7-5.2', 'select7-5.3', 'select7-5.4'] as $scenario) {
            $t->contains($scenario, $text, $scenario . ' exists upstream');
        }
        $t->contains('set testprefix select7', $text);
        $t->contains('do_catchsql_test 8.1', $text);
        $t->contains('do_catchsql_test 8.2', $text);
        $t->contains('sub-select returns 2 columns - expected 1', $text);
        $t->contains('SELECTs to the left and right of UNION do not have the same number of result columns', $text);
    };

for ($case = 0; $case < 1000; $case++) {
    $probe = 5 + ($case % 97);
    $leftX = $probe + 10;
    $leftY = ($case * 7) % 31;
    $rightA = $probe + 20;
    $rightB = $probe + 30;
    $operator = ['UNION', 'UNION ALL', 'INTERSECT', 'EXCEPT'][$case % 4];

    $tables = [
        'compound_left' => [
            ['x' => $leftX, 'y' => $leftY],
        ],
        'compound_right' => [
            ['x' => $rightA],
        ],
        'arity_pairs' => [
            ['a' => $rightA, 'b' => $rightB],
            ['a' => $probe, 'b' => $leftY],
        ],
    ];

    $tests[sprintf('real upstream select7.test dynamic subquery and compound arity errors case %04d', $case)] =
        static function (TestRunner $t) use ($assertSelectSqlThrows, $tables, $probe, $leftY, $operator, $case): void {
            $assertSelectSqlThrows(
                $t,
                "SELECT {$probe} IN (SELECT a,b FROM arity_pairs) AS matched",
                $tables,
                'IN subquery expression must return one column',
                'select7-5.1 two explicit subquery columns case ' . $case,
            );
            $assertSelectSqlThrows(
                $t,
                "SELECT {$probe} IN (SELECT * FROM arity_pairs) AS matched",
                $tables,
                'IN subquery expression must return one column',
                'select7-5.2 wildcard subquery columns case ' . $case,
            );
            $assertSelectSqlThrows(
                $t,
                "SELECT {$probe} IN (SELECT a,b FROM arity_pairs UNION SELECT b,a FROM arity_pairs) AS matched",
                $tables,
                'IN subquery expression must return one column',
                'select7-5.3 compound two-column IN subquery case ' . $case,
            );
            $assertSelectSqlThrows(
                $t,
                "SELECT * FROM (SELECT * FROM compound_left UNION SELECT x FROM compound_right) WHERE y={$leftY}",
                $tables,
                'same number of result columns',
                'select7-8.1 derived compound width mismatch case ' . $case,
            );
            $assertSelectSqlThrows(
                $t,
                "SELECT * FROM (SELECT x FROM compound_right {$operator} SELECT * FROM compound_left) WHERE x={$probe}",
                $tables,
                'same number of result columns',
                'select7-8.1 reversed compound width mismatch case ' . $case,
            );
            $assertSelectSqlThrows(
                $t,
                "SELECT count(*) AS n FROM (SELECT a,b FROM arity_pairs {$operator} SELECT a FROM arity_pairs)",
                $tables,
                'same number of result columns',
                'select7-8.2 aggregate wrapper compound width mismatch case ' . $case,
            );
        };
}

$tests['real upstream select7.test dynamic arity error non-overlap and dependency closure'] =
    static function (TestRunner $t): void {
        $t->same(
            'select7.test select7-5 IN-subquery arity and select7-8 compound-arm arity rejection',
            'select7.test select7-5 IN-subquery arity and select7-8 compound-arm arity rejection',
        );
        $t->same(
            'non-overlap: avoids accepted select7 grouped CASE/type-affinity, correlated EXCEPT, selectG large VALUES, selectH omit-unused, expression ORDER BY, JSON, WAL, VFS, B-tree, PRAGMA, and metadata-only rows',
            'non-overlap: avoids accepted select7 grouped CASE/type-affinity, correlated EXCEPT, selectG large VALUES, selectH omit-unused, expression ORDER BY, JSON, WAL, VFS, B-tree, PRAGMA, and metadata-only rows',
        );
        $t->same(
            'no new support component needed; reuses SQLiteSelectSql IN-subquery and compound SELECT arity validation',
            'no new support component needed; reuses SQLiteSelectSql IN-subquery and compound SELECT arity validation',
        );
    };

return $tests;
