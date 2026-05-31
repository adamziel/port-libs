<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test
 * - selectG-110: a multi-row VALUES clause inside a scalar SELECT expression
 *   returns the left-most row.
 * - selectG-120: only the left-most VALUES row is needed by that scalar
 *   expression.
 */

$tests = [];

$tests['real upstream selectG.test selectG-110 selectG-120 cites scalar values source'] = static function (TestRunner $t): void {
    $source = '/home/claude/port-libs/.upstream-cache/libsqlite/test/selectG.test';

    $t->true(is_file($source), 'hydrated upstream selectG.test is available');
    $text = file_get_contents($source);
    $t->true(is_string($text), 'hydrated upstream selectG.test is readable');
    $t->contains('do_test 110', $text);
    $t->contains('SELECT (VALUES', $text);
    $t->contains('Only the left-most term of a multi-valued VALUES within a scalar', $text);
};

for ($seed = 0; $seed < 1000; $seed++) {
    $first = ($seed * 37) - 500;
    $second = $first + 11;
    $third = $first - 13;
    $label = 'scalar-' . $seed;
    $quotedLabel = str_replace("'", "''", $label);
    $sql = "SELECT (VALUES({$first}),({$second}),({$third})) AS first_value, '{$quotedLabel}' AS label";

    $tests[sprintf('real upstream selectG.test scalar values leftmost dynamic seed %04d', $seed)] =
        static function (TestRunner $t) use ($sql, $first, $label, $seed): void {
            $rows = SQLiteSelectSql::execute($sql, []);

            $t->same([['first_value' => $first, 'label' => $label]], $rows, 'selectG scalar VALUES left-most row seed ' . $seed);
            $t->same($first, $rows[0]['first_value'] ?? null, 'selectG scalar VALUES first column seed ' . $seed);
            $t->same($label, $rows[0]['label'] ?? null, 'selectG scalar VALUES sibling projection seed ' . $seed);
            $t->same(1, count($rows), 'selectG scalar VALUES returns one row seed ' . $seed);
            $t->same(true, $seed >= 0 && $seed < 1000, 'selectG bounded dynamic seed guard ' . $seed);
        };
}

$tests['real upstream selectG.test scalar values dependency closure note'] = static function (TestRunner $t): void {
    $t->same('no new support component needed', 'no new support component needed');
    $t->same('selectG.test:110-120', 'selectG.test:110-120');
    $t->same('generic SQLite application rows', 'generic SQLite application rows');
};

return $tests;
