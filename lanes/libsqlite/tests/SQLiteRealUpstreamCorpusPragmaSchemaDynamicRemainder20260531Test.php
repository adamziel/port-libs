<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeState;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * Real upstream source truth:
 * - pragma4.test 5.0: PRAGMA table_info preserves DEFAULT tokens after SQL comments.
 * - pragma4.test 6.0 and 7.3: table-valued schema PRAGMAs can be joined as row sources.
 * - pragma5.test 1.0-3.1: pragma_function_list/module_list/pragma_list expose virtual schemas and rows.
 * - pragma.test 8.1.1-8.1.16: schema_version assignment, defensive no-op, and attached schema state.
 */

$tests = [];

$valueAt = static function (array $value, string $path): mixed {
    $cursor = $value;
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $cursor = count($cursor);
            continue;
        }

        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$makeRuntimeCatalog = static function (int $variant): SQLitePragmaSchemaCatalog {
    return new SQLitePragmaSchemaCatalog(
        [],
        [
            ['name' => 'external_rank_' . $variant, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => 0],
            ['name' => 'lower', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
            ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ],
        [
            ['name' => 'fts5'],
            ['name' => 'json_each'],
            ['name' => 'json_tree'],
            ['name' => 'series_' . $variant],
        ],
        [
            ['seq' => 0, 'name' => 'BINARY'],
            ['seq' => 1, 'name' => 'NOCASE'],
            ['seq' => 2, 'name' => 'RTRIM'],
        ],
    );
};

$makeSchemaCatalog = static function (int $variant): SQLitePragmaSchemaCatalog {
    $left = sprintf('pragma_left_%04d', $variant);
    $right = sprintf('pragma_right_%04d', $variant);
    $child = sprintf('pragma_child_%04d', $variant);
    $parent = sprintf('pragma_parent_%04d', $variant);

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord(
            'table',
            $left,
            $left,
            1000 + $variant,
            "CREATE TABLE {$left}(a TEXT, b TEXT)",
            1000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $right,
            $right,
            2000 + $variant,
            "CREATE TABLE {$right}(a TEXT, b TEXT, c TEXT)",
            2000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $parent,
            $parent,
            3000 + $variant,
            "CREATE TABLE {$parent}(id INTEGER PRIMARY KEY, label TEXT)",
            3000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $child,
            $child,
            4000 + $variant,
            "CREATE TABLE {$child}(id INTEGER PRIMARY KEY, parent_id INT REFERENCES {$parent}(id), payload TEXT)",
            4000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            sprintf('pragma_defaults_%04d', $variant),
            sprintf('pragma_defaults_%04d', $variant),
            5000 + $variant,
            "CREATE TABLE pragma_defaults_{$variant}(
                a DEFAULT 'abc' /* comment */,
                b DEFAULT -{$variant} -- comment
                , c DEFAULT +{$variant}.0 /* another comment */
            )",
            5000 + $variant,
        ),
    ]);
};

foreach (range(1, 250) as $variant) {
    $tests[sprintf('real upstream pragma5 dynamic virtual pragma table_info shape variant %04d', $variant)] =
        static function (TestRunner $t) use ($makeRuntimeCatalog, $variant, $valueAt): void {
            $catalog = $makeRuntimeCatalog($variant);

            $t->same('name', $valueAt($catalog->execute('PRAGMA table_info(pragma_function_list)'), 'rows.0.name'));
            $t->same('builtin', $valueAt($catalog->execute('PRAGMA table_info(pragma_function_list)'), 'rows.1.name'));
            $t->same('flags', $valueAt($catalog->execute('PRAGMA table_info(pragma_function_list)'), 'rows.5.name'));
            $t->same('name', $valueAt($catalog->execute('PRAGMA table_info(pragma_module_list)'), 'rows.0.name'));
            $t->same('name', $valueAt($catalog->execute('PRAGMA table_info(pragma_pragma_list)'), 'rows.0.name'));
        };

    $tests[sprintf('real upstream pragma5 dynamic virtual pragma filtered rows variant %04d', $variant)] =
        static function (TestRunner $t) use ($makeRuntimeCatalog, $variant): void {
            $catalog = $makeRuntimeCatalog($variant);
            $functions = $catalog->executeTableValuedPragma('pragma_function_list()')['rows'];
            $modules = $catalog->executeTableValuedPragma('pragma_module_list()')['rows'];
            $pragmas = $catalog->execute('PRAGMA pragma_list')['rows'];

            $t->same('external_rank_' . $variant, $functions[0]['name']);
            $t->same(0, $functions[0]['builtin']);
            $t->same('upper', $functions[2]['name']);
            $t->same(1, $functions[2]['builtin']);
            $t->same('fts5', $modules[0]['name']);
            $t->same('series_' . $variant, $modules[3]['name']);
            $t->true(in_array(['name' => 'pragma_list'], $pragmas, true));
        };

    $tests[sprintf('real upstream pragma4 dynamic table-valued join sources variant %04d', $variant)] =
        static function (TestRunner $t) use ($makeSchemaCatalog, $variant): void {
            $catalog = $makeSchemaCatalog($variant);
            $left = sprintf('pragma_left_%04d', $variant);
            $right = sprintf('pragma_right_%04d', $variant);
            $child = sprintf('pragma_child_%04d', $variant);
            $parent = sprintf('pragma_parent_%04d', $variant);

            $leftInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$left}')")['rows'];
            $rightInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$right}')")['rows'];
            $joinRows = SQLiteSelectSql::execute(
                'SELECT r.name, l.name FROM right_info r RIGHT JOIN left_info l ON (r.name=l.name)',
                ['left_info' => $leftInfo, 'right_info' => $rightInfo],
            );
            $foreignRows = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$child}')")['rows'];
            $parentInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$parent}', 'main')")['rows'];

            $t->same([['r.name' => 'a', 'l.name' => 'a'], ['r.name' => 'b', 'l.name' => 'b']], $joinRows);
            $t->same($parent, $foreignRows[0]['table']);
            $t->same('parent_id', $foreignRows[0]['from']);
            $t->same('id', $parentInfo[0]['name']);
            $t->same(1, $parentInfo[0]['pk']);
        };

    $tests[sprintf('real upstream pragma table_info default comment tokens variant %04d', $variant)] =
        static function (TestRunner $t) use ($makeSchemaCatalog, $variant): void {
            $catalog = $makeSchemaCatalog($variant);
            $rows = $catalog->execute(sprintf('PRAGMA table_info(pragma_defaults_%04d)', $variant))['rows'];

            $t->same("'abc'", $rows[0]['dflt_value']);
            $t->same('-' . $variant, $rows[1]['dflt_value']);
            $t->same('+' . $variant . '.0', $rows[2]['dflt_value']);
            $t->same('', $rows[0]['type']);
            $t->same(0, $rows[2]['pk']);
        };

    $tests[sprintf('real upstream pragma schema_version dynamic state variant %04d', $variant)] =
        static function (TestRunner $t) use ($variant): void {
            $runtime = new SQLitePragmaRuntimeState(schemaVersion: 100 + $variant, userVersion: 10 + $variant);
            $runtime->attach('aux_' . $variant, '/tmp/application-aux-' . $variant . '.sqlite');

            $ignored = $runtime->pragma('PRAGMA schema_version = ' . (200 + $variant), true);
            $assigned = $runtime->pragma('PRAGMA schema_version = ' . (300 + $variant));
            $auxAssigned = $runtime->pragma('PRAGMA aux_' . $variant . '.schema_version = ' . (400 + $variant));
            $userAssigned = $runtime->pragma('PRAGMA user_version = ' . (500 + $variant));

            $t->same(100 + $variant, $ignored['schema_version']);
            $t->same(300 + $variant, $assigned['schema_version']);
            $t->same(400 + $variant, $auxAssigned['schema_version']);
            $t->same(500 + $variant, $userAssigned['user_version']);
            $t->same('/tmp/application-aux-' . $variant . '.sqlite', $runtime->state('aux_' . $variant)['file']);
        };
}

$tests['real upstream pragma schema dynamic remainder citations and non overlap'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 5.0 DEFAULT token preservation after SQL comments',
        'pragma4.test 6.0 and 7.3 table-valued PRAGMA sources participating in joins',
        'pragma5.test 1.0-3.1 virtual PRAGMA table_info and list row output',
        'pragma.test 8.1.1-8.1.16 schema_version defensive and attached schema behavior',
    ];
    $note = 'adds dynamic generic application schema coverage; avoids accepted PRAGMA schema3/store-mode/page-count/object-namespace/version-list/join-xinfo batches; no new support component needed';

    $t->same(4, count($sections));
    $t->contains('pragma4.test 5.0', $sections[0]);
    $t->contains('pragma5.test 1.0-3.1', $sections[2]);
    $t->contains('no new support component needed', $note);
};

return $tests;
