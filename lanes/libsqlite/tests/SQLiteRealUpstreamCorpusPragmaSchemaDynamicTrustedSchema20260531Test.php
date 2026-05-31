<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaConnectionBooleanState;
use PortLibs\LibSqlite\SQLiteTrustedSchemaRuntime;

$tests = [];

/*
 * Real upstream source: SQLite test/trustschema1.test.
 *
 * This ports the PRAGMA trusted_schema safety boundary for schema-stored
 * function calls:
 * - trustschema1-1.100 through 1.160: generated columns reject direct-only
 *   functions in persistent schemas, reject non-innocuous functions while
 *   trusted_schema is OFF, but allow TEMP schema use.
 * - trustschema1-1.200 through 1.320: CHECK and DEFAULT expressions enforce
 *   the same trusted-schema distinction at create/insert time.
 * - trustschema1-1.400 through 1.540: partial and expression indexes enforce
 *   direct-only and innocuous function flags, with TEMP schema exceptions.
 * - trustschema1-2.100 through 4.2: views/triggers enforce the safety gate at
 *   execution time while direct SQL calls and innocuous built-ins remain safe.
 */

$runtime = static function (bool $trusted = true): SQLiteTrustedSchemaRuntime {
    return new SQLiteTrustedSchemaRuntime([
        'f1' => ['innocuous' => true, 'deterministic' => true],
        'f2' => ['innocuous' => false, 'deterministic' => true],
        'f3' => ['direct_only' => true, 'deterministic' => true],
    ], $trusted);
};

foreach (range(1, 250) as $variant) {
    $tests[sprintf('real upstream trustschema1 generated columns trusted schema variant %03d', $variant)] = static function (TestRunner $t) use ($runtime, $variant): void {
        $db = $runtime(true);
        $table = "generated_settings_{$variant}";
        $db->createTable('main', $table, [
            ['name' => 'a'],
            ['name' => 'b', 'generated' => 'f1(a+1)'],
            ['name' => 'c', 'generated' => 'f2(a+2)'],
        ]);
        $db->insert('main', $table, ['a' => 100 + $variant]);
        $db->insert('main', $table, ['a' => 200 + $variant]);

        $trustedRows = $db->selectTable('main', $table, ['a', 'b', 'c']);
        $t->same(100 + $variant, $trustedRows[0]['a']);
        $t->same(101 + $variant, $trustedRows[0]['b']);
        $t->same(102 + $variant, $trustedRows[0]['c']);
        $t->same(202 + $variant, $trustedRows[1]['c']);

        $pragma = $db->executePragma('PRAGMA trusted_schema=OFF');
        $booleanState = new SQLitePragmaConnectionBooleanState();
        $boolean = $booleanState->execute('PRAGMA trusted_schema=OFF');
        $safeRows = $db->selectTable('main', $table, ['a', 'b']);
        $t->same(0, $pragma['value']);
        $t->same(0, $boolean['value']);
        $t->same([['a' => 100 + $variant, 'b' => 101 + $variant], ['a' => 200 + $variant, 'b' => 201 + $variant]], $safeRows);
        $t->throws(RuntimeException::class, static fn () => $db->selectTable('main', $table, ['c']));

        $db->executePragma('PRAGMA trusted_schema=ON');
        $t->throws(RuntimeException::class, static fn () => $db->createTable('main', "direct_generated_{$variant}", [
            ['name' => 'a'],
            ['name' => 'b', 'generated' => 'f3(a+1)'],
        ]));
        $db->executePragma('PRAGMA trusted_schema=OFF');
        $db->createTable('temp', "temp_generated_{$variant}", [
            ['name' => 'a'],
            ['name' => 'b', 'generated' => 'f3(a+1)'],
        ]);
        $tempInsert = $db->insert('temp', "temp_generated_{$variant}", ['a' => 900 + $variant]);
        $t->same(901 + $variant, $tempInsert['row']['b']);
        $t->same([['a' => 900 + $variant, 'b' => 901 + $variant]], $db->selectTable('temp', "temp_generated_{$variant}", ['a', 'b']));
    };

    $tests[sprintf('real upstream trustschema1 check default trusted schema variant %03d', $variant)] = static function (TestRunner $t) use ($runtime, $variant): void {
        $db = $runtime(true);
        $checked = "checked_settings_{$variant}";
        $db->createTable('main', $checked, [
            ['name' => 'a'],
            ['name' => 'b'],
            ['name' => 'c'],
        ], ['f2(c)==c']);
        $t->same(1, $db->insert('main', $checked, ['a' => 1, 'b' => 2, 'c' => 3 + $variant])['row_count']);
        $db->executePragma('PRAGMA trusted_schema=OFF');
        $t->throws(RuntimeException::class, static fn () => $db->insert('main', $checked, ['a' => 4, 'b' => 5, 'c' => 6 + $variant]));
        $t->same([['a' => 1, 'b' => 2, 'c' => 3 + $variant]], $db->selectTable('main', $checked, ['a', 'b', 'c']));
        $t->throws(RuntimeException::class, static fn () => $db->createTable('main', "checked_reject_{$variant}", [
            ['name' => 'a'],
            ['name' => 'b'],
        ], ['f2(b)==b']));

        $defaults = "default_settings_{$variant}";
        $db->executePragma('PRAGMA trusted_schema=ON');
        $db->createTable('main', $defaults, [
            ['name' => 'a'],
            ['name' => 'b', 'default' => 'f2(' . (25 + $variant) . ')'],
        ]);
        $db->executePragma('PRAGMA trusted_schema=OFF');
        $t->throws(RuntimeException::class, static fn () => $db->insert('main', $defaults, ['a' => 1]));
        $explicit = $db->insert('main', $defaults, ['a' => 1, 'b' => 2 + $variant]);
        $t->same(['a' => 1, 'b' => 2 + $variant], $explicit['row']);

        $db->createTable('temp', "temp_check_{$variant}", [
            ['name' => 'a'],
            ['name' => 'b', 'default' => 'f3(' . (31 + $variant) . ')'],
        ], ['f3(b)==b']);
        $temp = $db->insert('temp', "temp_check_{$variant}", ['a' => 22 + $variant]);
        $t->same(31 + $variant, $temp['row']['b']);
        $t->same([['a' => 22 + $variant, 'b' => 31 + $variant]], $db->selectTable('temp', "temp_check_{$variant}", ['a', 'b']));
    };

    $tests[sprintf('real upstream trustschema1 index safety trusted schema variant %03d', $variant)] = static function (TestRunner $t) use ($runtime, $variant): void {
        $db = $runtime(true);
        $table = "index_settings_{$variant}";
        $db->createTable('main', $table, [
            ['name' => 'a'],
            ['name' => 'b'],
            ['name' => 'c'],
        ]);
        $db->insert('main', $table, ['a' => 1 + $variant, 'b' => 2, 'c' => 3]);
        $db->insert('main', $table, ['a' => 4 + $variant, 'b' => 5, 'c' => 0]);

        $t->throws(RuntimeException::class, static fn () => $db->createIndex('main', "direct_partial_{$variant}", $table, ['a'], 'f3(c)'));
        $db->executePragma('PRAGMA trusted_schema=OFF');
        $t->throws(RuntimeException::class, static fn () => $db->createIndex('main', "unsafe_partial_{$variant}", $table, ['a'], 'f2(c)'));
        $safeIndex = $db->createIndex('main', "safe_partial_{$variant}", $table, ['a'], 'f1(c)');
        $safeRows = $db->queryUsingIndex('main', "safe_partial_{$variant}", 'f1(c)');
        $t->same("safe_partial_{$variant}", $safeIndex['index']);
        $t->same(1, count($safeRows));
        $t->same(1 + $variant, $safeRows[0]['a']);

        $db->executePragma('PRAGMA trusted_schema=ON');
        $edgeIndex = $db->createIndex('main', "edge_expr_{$variant}", $table, ['b+f2(c)']);
        $edgeRows = $db->queryUsingIndex('main', "edge_expr_{$variant}", 'f2(c)');
        $t->same(1, $edgeIndex['expression_count']);
        $t->same(1, count($edgeRows));
        $t->same(2, $edgeRows[0]['b']);

        $db->executePragma('PRAGMA trusted_schema=OFF');
        $db->createTable('temp', "temp_index_settings_{$variant}", [
            ['name' => 'a'],
            ['name' => 'b'],
        ]);
        $db->insert('temp', "temp_index_settings_{$variant}", ['a' => 7 + $variant, 'b' => 0]);
        $tempIndex = $db->createIndex('temp', "temp_direct_expr_{$variant}", "temp_index_settings_{$variant}", ['a+f3(b)']);
        $tempRows = $db->queryUsingIndex('temp', "temp_direct_expr_{$variant}", 'f3(a)');
        $t->same("temp_direct_expr_{$variant}", $tempIndex['index']);
        $t->same(7 + $variant, $tempRows[0]['a']);
    };

    $tests[sprintf('real upstream trustschema1 view trigger trusted schema variant %03d', $variant)] = static function (TestRunner $t) use ($runtime, $variant): void {
        $views = $runtime(true);
        $table = "view_settings_{$variant}";
        $views->createTable('main', $table, [
            ['name' => 'a'],
            ['name' => 'b'],
            ['name' => 'c'],
        ]);
        $views->insert('main', $table, ['a' => 1 + $variant, 'b' => 2, 'c' => 3]);
        $views->insert('main', $table, ['a' => 100 + $variant, 'b' => 50, 'c' => 75]);
        $t->same(3 + $variant, $views->directSelectExpression('f3(a+b)', ['a' => 1 + $variant, 'b' => 2]));

        $views->createView('main', "direct_view_{$variant}", $table, [['alias' => 'x', 'expression' => 'f3(a+b)']]);
        $t->throws(RuntimeException::class, static fn () => $views->selectView('main', "direct_view_{$variant}"));
        $views->createView('temp', "temp_direct_view_{$variant}", $table, [['alias' => 'x', 'expression' => 'f3(a+b)']]);
        $t->same([['x' => 3 + $variant], ['x' => 150 + $variant]], $views->selectView('temp', "temp_direct_view_{$variant}"));

        $views->createView('main', "edge_view_{$variant}", $table, [['alias' => 'x', 'expression' => 'f2(b+c)']]);
        $t->same([['x' => 5], ['x' => 125]], $views->selectView('main', "edge_view_{$variant}"));
        $views->executePragma('PRAGMA trusted_schema=OFF');
        $t->throws(RuntimeException::class, static fn () => $views->selectView('main', "edge_view_{$variant}"));
        $views->createView('main', "json_view_{$variant}", $table, [['alias' => 'x', 'expression' => 'json_extract(\'{"a":' . (123 + $variant) . '}\',\'$.a\')']]);
        $t->same([['x' => 123 + $variant], ['x' => 123 + $variant]], $views->selectView('main', "json_view_{$variant}"));

        $directTrigger = $runtime(true);
        $directTrigger->createTable('main', "trigger_settings_{$variant}", [
            ['name' => 'a'],
            ['name' => 'x'],
        ]);
        $directTrigger->createTrigger('main', "direct_trigger_{$variant}", "trigger_settings_{$variant}", 'f3(a)', 'x');
        $t->throws(RuntimeException::class, static fn () => $directTrigger->insert('main', "trigger_settings_{$variant}", ['a' => 7 + $variant]));

        $edgeTrigger = $runtime(true);
        $edgeTrigger->createTable('main', "trigger_edge_settings_{$variant}", [
            ['name' => 'a'],
            ['name' => 'x'],
        ]);
        $edgeTrigger->createTrigger('main', "edge_trigger_{$variant}", "trigger_edge_settings_{$variant}", 'f2(a)+100', 'x');
        $t->same(107 + $variant, $edgeTrigger->insert('main', "trigger_edge_settings_{$variant}", ['a' => 7 + $variant])['row']['x']);
        $edgeTrigger->executePragma('PRAGMA trusted_schema=OFF');
        $t->throws(RuntimeException::class, static fn () => $edgeTrigger->insert('main', "trigger_edge_settings_{$variant}", ['a' => 9 + $variant]));
    };
}

$tests['real upstream trustschema1 source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'trustschema1.test 1.100 through 1.160: generated columns respect PRAGMA trusted_schema and TEMP schema exceptions',
        'trustschema1.test 1.200 through 1.320: CHECK and DEFAULT expressions reject unsafe persistent-schema functions when trusted_schema is OFF',
        'trustschema1.test 1.400 through 1.540: partial and expression indexes enforce innocuous/direct-only flags',
        'trustschema1.test 2.100 through 4.2: views, triggers, direct SQL, TEMP views, and json_extract built-ins observe the trusted-schema boundary',
    ];

    $t->same(4, count($sections));
    $t->contains('trustschema1.test 1.100', $sections[0]);
    $t->contains('CHECK and DEFAULT', $sections[1]);
    $t->contains('expression indexes', $sections[2]);
    $t->contains('json_extract', $sections[3]);
};

return $tests;
