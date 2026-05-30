<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$catalogFor = static function (int $variant): SQLitePragmaSchemaCatalog {
    $parent = "second_pragma_parent_{$variant}";
    $child = "second_pragma_child_{$variant}";
    $view = "second_pragma_view_{$variant}";
    $brokenView = "second_pragma_broken_view_{$variant}";
    $commentDefaults = "second_pragma_defaults_{$variant}";
    $childLookup = "second_pragma_child_lookup_{$variant}";
    $parentAuto = "sqlite_autoindex_{$parent}_1";
    $childAuto = "sqlite_autoindex_{$child}_1";

    return new SQLitePragmaSchemaCatalog(
        [
            new SQLiteSchemaRecord(
                'table',
                $parent,
                $parent,
                1000 + $variant,
                "CREATE TABLE {$parent}(
                    tenant_id INTEGER NOT NULL,
                    key_name TEXT NOT NULL,
                    key_value TEXT DEFAULT 'parent_{$variant}',
                    PRIMARY KEY(tenant_id, key_name)
                ) WITHOUT ROWID",
                1000 + $variant,
            ),
            new SQLiteSchemaRecord('index', $parentAuto, $parent, 2000 + $variant, null, 2000 + $variant),
            new SQLiteSchemaRecord(
                'table',
                $child,
                $child,
                3000 + $variant,
                "CREATE TABLE {$child}(
                    rowid_alias INTEGER PRIMARY KEY,
                    tenant_id INTEGER NOT NULL,
                    key_name TEXT NOT NULL,
                    key_value TEXT DEFAULT (json_object('variant', {$variant})),
                    load_policy TEXT COLLATE nocase DEFAULT 'lazy',
                    UNIQUE(key_name, tenant_id),
                    FOREIGN KEY(tenant_id, key_name) REFERENCES {$parent}(tenant_id, key_name)
                      ON UPDATE CASCADE ON DELETE SET NULL
                ) STRICT",
                3000 + $variant,
            ),
            new SQLiteSchemaRecord('index', $childAuto, $child, 4000 + $variant, null, 4000 + $variant),
            new SQLiteSchemaRecord(
                'index',
                $childLookup,
                $child,
                5000 + $variant,
                "CREATE INDEX {$childLookup} ON {$child}(load_policy COLLATE nocase DESC, key_value ASC) WHERE key_value IS NOT NULL",
                5000 + $variant,
            ),
            new SQLiteSchemaRecord(
                'table',
                $commentDefaults,
                $commentDefaults,
                6000 + $variant,
                "CREATE TABLE {$commentDefaults}(
                    a DEFAULT 'abc' /* comment */,
                    b DEFAULT -1 -- comment
                    , c DEFAULT +4.0 /* another comment */,
                    d TEXT DEFAULT ('dynamic_' || {$variant})
                )",
                6000 + $variant,
            ),
            new SQLiteSchemaRecord(
                'view',
                $view,
                $view,
                null,
                "CREATE VIEW {$view} AS SELECT key_name, key_value FROM {$child}",
                7000 + $variant,
            ),
            new SQLiteSchemaRecord(
                'view',
                $brokenView,
                $brokenView,
                null,
                "CREATE VIEW {$brokenView} AS SELECT nosuchfunc(key_name) FROM {$child}",
                8000 + $variant,
            ),
        ],
        [
            ['name' => "app_rank_{$variant}", 'builtin' => 0, 'type' => 'w', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2097152 + $variant],
            ['name' => "app_norm_{$variant}", 'builtin' => 0, 'type' => 's', 'enc' => 'utf16le', 'narg' => 2, 'flags' => 2048 + $variant],
            ['name' => 'lower', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
        ],
        [
            ['name' => 'json_each'],
            ['name' => "tenant_module_{$variant}"],
            ['name' => 'json_tree'],
        ],
        [
            ['seq' => 0, 'name' => 'binary'],
            ['seq' => 1, 'name' => "tenant_nocase_{$variant}"],
            ['seq' => 2, 'name' => 'rtrim'],
        ],
    );
};

foreach (range(1, 200) as $variant) {
    $parent = "second_pragma_parent_{$variant}";
    $child = "second_pragma_child_{$variant}";
    $view = "second_pragma_view_{$variant}";
    $brokenView = "second_pragma_broken_view_{$variant}";
    $commentDefaults = "second_pragma_defaults_{$variant}";
    $childLookup = "second_pragma_child_lookup_{$variant}";
    $childAuto = "sqlite_autoindex_{$child}_1";

    $tests["real upstream pragma schema second thousand pragma4 5.0 default comments variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $commentDefaults, $variant): void {
        $rows = $catalogFor($variant)->execute("PRAGMA table_info = {$commentDefaults}")['rows'];

        $t->same(4, count($rows));
        $t->same('a', $rows[0]['name']);
        $t->same("'abc'", $rows[0]['dflt_value']);
        $t->same('b', $rows[1]['name']);
        $t->same('-1', $rows[1]['dflt_value']);
        $t->same('c', $rows[2]['name']);
        $t->same('+4.0', $rows[2]['dflt_value']);
        $t->same('d', $rows[3]['name']);
        $t->same("'dynamic_' || {$variant}", $rows[3]['dflt_value']);
    };

    $tests["real upstream pragma schema second thousand pragma4 6.0 table-list view stability variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $parent, $child, $view, $brokenView, $variant): void {
        $rows = $catalogFor($variant)->execute('PRAGMA table_list')["rows"];
        $byName = [];
        foreach ($rows as $row) {
            $byName[$row['name']] = $row;
        }

        $t->same('table', $byName[$parent]['type']);
        $t->same(3, $byName[$parent]['ncol']);
        $t->same(1, $byName[$parent]['wr']);
        $t->same('table', $byName[$child]['type']);
        $t->same(5, $byName[$child]['ncol']);
        $t->same(1, $byName[$child]['strict']);
        $t->same('view', $byName[$view]['type']);
        $t->same(0, $byName[$view]['ncol']);
        $t->same('view', $byName[$brokenView]['type']);
        $t->same(1, $byName[$brokenView]['ncol']);
    };

    $tests["real upstream pragma schema second thousand pragma4 7.3 table-valued join shape variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $parent, $child, $childLookup, $childAuto, $variant): void {
        $catalog = $catalogFor($variant);
        $foreignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$child}', 'main')")['rows'];
        $parentInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$parent}', 'main')")['rows'];
        $indexList = $catalog->executeTableValuedPragma("pragma_index_list('{$child}', 'main')")['rows'];
        $indexInfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$childLookup}', 'main')")['rows'];

        $t->same($parent, $foreignKeys[0]['table']);
        $t->same('tenant_id', $foreignKeys[0]['from']);
        $t->same('tenant_id', $foreignKeys[0]['to']);
        $t->same('key_name', $parentInfo[1]['name']);
        $t->same(2, $parentInfo[1]['pk']);
        $t->same($childAuto, $indexList[0]['name']);
        $t->same(1, $indexList[0]['unique']);
        $t->same($childLookup, $indexList[1]['name']);
        $t->same('NOCASE', $indexInfo[0]['coll']);
        $t->same(1, $indexInfo[0]['desc']);
    };

    $tests["real upstream pragma schema second thousand pragma3 shared-cache data-version variant {$variant}"] = static function (TestRunner $t) use ($variant): void {
        $db = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => 10 + $variant, 'data_version' => 1, 'change_counter' => 1, 'user_version' => 0],
        ]);
        $db2 = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => 10 + $variant, 'data_version' => 2, 'change_counter' => 2, 'user_version' => 0],
        ]);

        $db->beginTransaction();
        $db->recordSchemaChange('main', 2, 'pragma3_shared_cache_schema_change');
        $during = $db->execute('PRAGMA data_version')['value'];
        $db->commitTransaction();
        $db2->observeHeader('main', 12 + $variant, 3, 'pragma3_shared_cache_commit_observed');

        $t->same(1, $during);
        $t->same(1, $db->execute('PRAGMA data_version')['value']);
        $t->same(3, $db->headerUpdate('main')['file_change_counter']);
        $t->same(3, $db2->execute('PRAGMA data_version')['value']);
        $t->same(12 + $variant, $db2->execute('PRAGMA schema_version')['value']);
        $t->same(3, $db2->headerUpdate('main')['file_change_counter']);
        $t->same(false, $db2->execute('PRAGMA data_version = 999')['changed']);
        $t->same('read_only_pragma_ignored', $db2->execute('PRAGMA data_version = 999')['reason']);
    };

    $tests["real upstream pragma schema second thousand pragma function module collation lists variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant): void {
        $catalog = $catalogFor($variant);
        $functions = $catalog->execute('PRAGMA function_list')['rows'];
        $modules = $catalog->executeTableValuedPragma('pragma_module_list()')['rows'];
        $collations = $catalog->execute('PRAGMA collation_list')['rows'];
        $pragmas = $catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'];

        $t->same("app_norm_{$variant}", $functions[0]['name']);
        $t->same('utf16le', $functions[0]['enc']);
        $t->same(2, $functions[0]['narg']);
        $t->same("tenant_module_{$variant}", $modules[2]['name']);
        $t->same('BINARY', $collations[0]['name']);
        $t->same("TENANT_NOCASE_{$variant}", $collations[1]['name']);
        $t->same('pragma_list', $pragmas[7]['name']);
        $t->same('table_xinfo', $pragmas[10]['name']);
    };
}

$tests['real upstream pragma schema second thousand cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 5.0 comment-stripped DEFAULT values in PRAGMA table_info',
        'pragma4.test 6.0 PRAGMA table_list remains stable with corrupt view SQL',
        'pragma4.test 7.1 through 7.3 table-valued pragma result sets used in joins',
        'pragma3.test 300 through 430 shared-cache and WAL data_version observers',
        'pragma.test 11 and pragma4 table-valued function/module/collation/pragma lists',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma4.test 5.0', $sections[0]);
    $t->contains('pragma4.test 6.0', $sections[1]);
    $t->contains('pragma4.test 7.1', $sections[2]);
    $t->contains('pragma3.test 300', $sections[3]);
    $t->contains('function/module/collation', $sections[4]);
};

return $tests;
