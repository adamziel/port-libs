<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma4.test 4.* through 7.*: schema-qualified
 *   table-valued PRAGMA functions resolve against the requested schema while
 *   unqualified PRAGMAs follow SQLite temp/main/attached name resolution.
 * - SQLite test/pragma5.test 1.0 through 3.1: pragma_table_list() reports
 *   schema, type, ncol, WITHOUT ROWID, and STRICT flags for tables/views.
 * - SQLite test/schema.test schema-4.*, schema-9.*, and schema-10.*:
 *   attached-schema changes invalidate cached object resolution while existing
 *   PRAGMA cursors keep their current rowset stable.
 * - SQLite test/pragma.test 6.2.*, 6.5.*, and pragma4.test 5.0 through 7.3:
 *   table_info preserves default expressions/comments and composite primary-key
 *   ordinals, index_xinfo includes auxiliary rowid columns, schema-qualified
 *   foreign_key_list targets the requested attached schema, table_list tolerates
 *   invalid view SQL, and PRAGMA virtual-table metadata remains queryable.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $shared = sprintf('shadow_settings_%03d', $variant);
    $mainOnly = sprintf('main_settings_%03d', $variant);
    $archiveOnly = sprintf('archive_settings_%03d', $variant);
    $view = sprintf('shadow_view_%03d', $variant);
    $mainIndex = sprintf('shadow_main_idx_%03d', $variant);
    $tempIndex = sprintf('shadow_temp_idx_%03d', $variant);
    $archiveIndex = sprintf('shadow_archive_idx_%03d', $variant);
    $strict = sprintf('strict_settings_%03d', $variant);
    $withoutRowid = sprintf('tenant_settings_%03d', $variant);
    $defaults = sprintf('default_settings_%03d', $variant);
    $composite = sprintf('composite_settings_%03d', $variant);
    $parent = sprintf('parent_settings_%03d', $variant);
    $child = sprintf('child_settings_%03d', $variant);
    $childIndex = sprintf('child_settings_idx_%03d', $variant);
    $archiveParent = sprintf('archive_parent_%03d', $variant);
    $archiveChild = sprintf('archive_child_%03d', $variant);
    $brokenView = sprintf('broken_view_%03d', $variant);

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $shared, $shared, 1000 + $variant, "CREATE TABLE {$shared}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL, key_value TEXT DEFAULT 'main-{$variant}', load_policy TEXT DEFAULT 'eager')", 1),
            $record('index', $mainIndex, $shared, 2000 + $variant, "CREATE INDEX {$mainIndex} ON {$shared}(key_name COLLATE NOCASE DESC, load_policy)", 2),
            $record('table', $mainOnly, $mainOnly, 3000 + $variant, "CREATE TABLE {$mainOnly}(tenant_id INTEGER, key_name TEXT, key_value TEXT, PRIMARY KEY(tenant_id, key_name)) WITHOUT ROWID", 3),
            $record('table', $strict, $strict, 4000 + $variant, "CREATE TABLE {$strict}(key_name TEXT PRIMARY KEY, key_value TEXT NOT NULL) STRICT", 4),
            $record('view', $view, $view, null, "CREATE VIEW {$view} AS SELECT key_name, key_value FROM {$shared}", 5),
            $record('table', $defaults, $defaults, 11000 + $variant, "CREATE TABLE {$defaults}(one INT NOT NULL DEFAULT -{$variant} /* upstream pragma-6.7 */, two TEXT, three VARCHAR(45, 65) DEFAULT 'abc{$variant}', four REAL DEFAULT X'abcdef', five DEFAULT CURRENT_TIME, six DEFAULT (5+{$variant}), seven TEXT DEFAULT '' )", 12),
            $record('table', $composite, $composite, 12000 + $variant, "CREATE TABLE {$composite}(a, b INTEGER PRIMARY KEY, c, PRIMARY KEY(a,b,a,c))", 13),
            $record('table', $parent, $parent, 13000 + $variant, "CREATE TABLE {$parent}(tenant_id INTEGER, key_name TEXT, PRIMARY KEY(tenant_id, key_name))", 14),
            $record('table', $child, $child, 14000 + $variant, "CREATE TABLE {$child}(tenant_id INTEGER, key_name TEXT, parent_key TEXT REFERENCES {$parent}(key_name), FOREIGN KEY(tenant_id, key_name) REFERENCES {$parent}(tenant_id, key_name) ON UPDATE CASCADE ON DELETE SET NULL MATCH SIMPLE)", 15),
            $record('index', $childIndex, $child, 15000 + $variant, "CREATE INDEX {$childIndex} ON {$child}(key_name COLLATE NOCASE DESC, tenant_id)", 16),
            $record('view', $brokenView, $brokenView, null, "CREATE VIEW {$brokenView} AS SELECT nosuchfunc(key_name) FROM {$child}", 17),
        ],
        [
            $record('table', $shared, $shared, 5000 + $variant, "CREATE TABLE {$shared}(temp_id INTEGER PRIMARY KEY, key_name TEXT, key_value BLOB DEFAULT X'{$variant}')", 6),
            $record('index', $tempIndex, $shared, 6000 + $variant, "CREATE INDEX {$tempIndex} ON {$shared}(key_value)", 7),
        ],
    );

    $catalog->attach('archive', "/tmp/pragma-shadow-{$variant}.sqlite", [
        $record('table', $shared, $shared, 7000 + $variant, "CREATE TABLE {$shared}(archive_id INTEGER PRIMARY KEY, key_name TEXT, archived_value TEXT DEFAULT 'archive-{$variant}')", 8),
        $record('index', $archiveIndex, $shared, 8000 + $variant, "CREATE INDEX {$archiveIndex} ON {$shared}(archived_value COLLATE RTRIM)", 9),
        $record('table', $archiveOnly, $archiveOnly, 9000 + $variant, "CREATE TABLE {$archiveOnly}(tenant_id INTEGER, key_name TEXT, key_value TEXT)", 10),
        $record('table', $withoutRowid, $withoutRowid, 10000 + $variant, "CREATE TABLE {$withoutRowid}(tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL, key_value TEXT, PRIMARY KEY(tenant_id, key_name)) WITHOUT ROWID", 11),
        $record('table', $archiveParent, $archiveParent, 16000 + $variant, "CREATE TABLE {$archiveParent}(tenant_id INTEGER, archive_key TEXT, PRIMARY KEY(tenant_id, archive_key))", 18),
        $record('table', $archiveChild, $archiveChild, 17000 + $variant, "CREATE TABLE {$archiveChild}(tenant_id INTEGER, archive_key TEXT REFERENCES {$archiveParent}(archive_key), FOREIGN KEY(tenant_id, archive_key) REFERENCES {$archiveParent}(tenant_id, archive_key) ON UPDATE SET DEFAULT ON DELETE CASCADE)", 19),
    ]);

    return $catalog;
};

foreach (range(1, 400) as $variant) {
    $shared = sprintf('shadow_settings_%03d', $variant);
    $mainOnly = sprintf('main_settings_%03d', $variant);
    $archiveOnly = sprintf('archive_settings_%03d', $variant);
    $view = sprintf('shadow_view_%03d', $variant);
    $mainIndex = sprintf('shadow_main_idx_%03d', $variant);
    $tempIndex = sprintf('shadow_temp_idx_%03d', $variant);
    $archiveIndex = sprintf('shadow_archive_idx_%03d', $variant);
    $strict = sprintf('strict_settings_%03d', $variant);
    $withoutRowid = sprintf('tenant_settings_%03d', $variant);
    $defaults = sprintf('default_settings_%03d', $variant);
    $composite = sprintf('composite_settings_%03d', $variant);
    $child = sprintf('child_settings_%03d', $variant);
    $childIndex = sprintf('child_settings_idx_%03d', $variant);
    $archiveChild = sprintf('archive_child_%03d', $variant);
    $brokenView = sprintf('broken_view_%03d', $variant);

    $tests[sprintf('real upstream pragma schema dynamic shadowing unqualified table info resolves temp variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $shared): void {
        $catalog = $catalogFor($variant);
        $unqualified = $catalog->executeSchemaPragma("PRAGMA table_info({$shared})");
        $main = $catalog->executeTableValuedPragma("pragma_table_info('{$shared}', 'main')");
        $archive = $catalog->executeTableValuedPragma("pragma_table_info('{$shared}', 'archive')");

        $t->same('temp', $unqualified['schema']);
        $t->same(['temp_id', 'key_name', 'key_value'], array_column($unqualified['rows'], 'name'));
        $t->same("X'{$variant}'", $unqualified['rows'][2]['dflt_value']);
        $t->same('main', $main['schema']);
        $t->same(['setting_id', 'key_name', 'key_value', 'load_policy'], array_column($main['rows'], 'name'));
        $t->same('archive', $archive['schema']);
        $t->same(['archive_id', 'key_name', 'archived_value'], array_column($archive['rows'], 'name'));
    };

    $tests[sprintf('real upstream pragma schema dynamic shadowing qualified index resolution variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $shared, $mainIndex, $tempIndex, $archiveIndex): void {
        $catalog = $catalogFor($variant);
        $tempRows = $catalog->executeSchemaPragma("PRAGMA index_info({$tempIndex})");
        $mainRows = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$mainIndex}', 'main')");
        $archiveRows = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$archiveIndex}', 'archive')");
        $tempList = $catalog->executeSchemaPragma("PRAGMA index_list({$shared})");

        $t->same('temp', $tempRows['schema']);
        $t->same('key_value', $tempRows['rows'][0]['name']);
        $t->same([$tempIndex], array_column($tempList['rows'], 'name'));
        $t->same('main', $mainRows['schema']);
        $t->same('key_name', $mainRows['rows'][0]['name']);
        $t->same('NOCASE', $mainRows['rows'][0]['coll']);
        $t->same(1, $mainRows['rows'][0]['desc']);
        $t->same('archive', $archiveRows['schema']);
        $t->same('RTRIM', $archiveRows['rows'][0]['coll']);
    };

    $tests[sprintf('real upstream pragma schema dynamic shadowing table list flags variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $shared, $mainOnly, $archiveOnly, $view, $strict, $withoutRowid): void {
        $catalog = $catalogFor($variant);
        $all = $catalog->executeTableValuedPragma('pragma_table_list()')['rows'];
        $sharedRows = $catalog->executeSchemaPragma("PRAGMA table_list({$shared})")['rows'];
        $mainOnlyRow = $catalog->executeTableValuedPragma("pragma_table_list('{$mainOnly}', 'main')")['rows'][0];
        $archiveOnlyRow = $catalog->executeTableValuedPragma("pragma_table_list('{$archiveOnly}', 'archive')")['rows'][0];
        $strictRow = $catalog->executeTableValuedPragma("pragma_table_list('{$strict}', 'main')")['rows'][0];
        $withoutRowidRow = $catalog->executeTableValuedPragma("pragma_table_list('{$withoutRowid}', 'archive')")['rows'][0];

        $t->same(['temp', 'main', 'archive'], array_values(array_unique(array_column($all, 'schema'))));
        $t->same(['temp', 'main', 'archive'], array_column($sharedRows, 'schema'));
        $t->same([3, 4, 3], array_column($sharedRows, 'ncol'));
        $t->same('view', $catalog->executeSchemaPragma("PRAGMA table_list({$view})")['rows'][0]['type']);
        $t->same(1, $mainOnlyRow['wr']);
        $t->same(0, $mainOnlyRow['strict']);
        $t->same('archive', $archiveOnlyRow['schema']);
        $t->same(1, $strictRow['strict']);
        $t->same(1, $withoutRowidRow['wr']);
    };

    $tests[sprintf('real upstream pragma schema dynamic shadowing detach invalidates archive only variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $shared, $mainOnly, $archiveOnly, $mainIndex, $tempIndex, $archiveIndex): void {
        $catalog = $catalogFor($variant);
        $cursor = $catalog->executeTableValuedPragmaCursor("pragma_index_info('{$archiveIndex}', 'archive')");
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$shared, $mainOnly, $archiveOnly], [$mainIndex, $tempIndex, $archiveIndex]);
        $catalog->detach('archive');
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);

        $t->same(false, $invalidation['current']);
        $t->same(['archive'], $invalidation['removed_schemas']);
        $t->same(true, $invalidation['table_changes'][$archiveOnly]['changed']);
        $t->same(false, $invalidation['table_changes'][$mainOnly]['changed']);
        $t->same(true, $invalidation['index_changes'][$archiveIndex]['changed']);
        $t->same(false, $invalidation['index_changes'][$mainIndex]['changed']);
        $t->same(false, $invalidation['index_changes'][$tempIndex]['changed']);
        $t->same('archived_value', $cursor->current()['name']);
        $t->same(['main', 'temp'], array_column($catalog->executeTableValuedPragma('pragma_database_list()')['rows'], 'name'));
    };

    $tests[sprintf('real upstream pragma schema dynamic extended table info defaults variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $defaults, $composite): void {
        $catalog = $catalogFor($variant);
        $defaultRows = $catalog->executeSchemaPragma("PRAGMA table_info({$defaults})")['rows'];
        $compositeRows = $catalog->executeSchemaPragma("PRAGMA main.table_info({$composite})")['rows'];

        $t->same(['one', 'two', 'three', 'four', 'five', 'six', 'seven'], array_column($defaultRows, 'name'));
        $t->same(['INT', 'TEXT', 'VARCHAR(45, 65)', 'REAL', '', '', 'TEXT'], array_column($defaultRows, 'type'));
        $t->same(1, $defaultRows[0]['notnull']);
        $t->same("-{$variant}", $defaultRows[0]['dflt_value']);
        $t->same("'abc{$variant}'", $defaultRows[2]['dflt_value']);
        $t->same("X'abcdef'", $defaultRows[3]['dflt_value']);
        $t->same('CURRENT_TIME', $defaultRows[4]['dflt_value']);
        $t->same('5+' . $variant, $defaultRows[5]['dflt_value']);
        $t->same("''", $defaultRows[6]['dflt_value']);
        $t->same([1, 2, 4], [$compositeRows[0]['pk'], $compositeRows[1]['pk'], $compositeRows[2]['pk']]);
    };

    $tests[sprintf('real upstream pragma schema dynamic extended index xinfo auxiliary variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $child, $childIndex): void {
        $catalog = $catalogFor($variant);
        $indexList = $catalog->executeSchemaPragma("PRAGMA index_list({$child})")['rows'];
        $indexInfo = $catalog->executeSchemaPragma("PRAGMA index_info({$childIndex})")['rows'];
        $indexXInfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$childIndex}', 'main')")['rows'];

        $t->same([$childIndex], array_column($indexList, 'name'));
        $t->same([0, 1], array_column($indexInfo, 'seqno'));
        $t->same(['key_name', 'tenant_id'], array_column($indexInfo, 'name'));
        $t->same(['key_name', 'tenant_id', null], array_column($indexXInfo, 'name'));
        $t->same([1, 1, 0], array_column($indexXInfo, 'key'));
        $t->same([1, 0, 0], array_column($indexXInfo, 'desc'));
        $t->same(['NOCASE', 'BINARY', 'BINARY'], array_column($indexXInfo, 'coll'));
        $t->same(-1, $indexXInfo[2]['cid']);
    };

    $tests[sprintf('real upstream pragma schema dynamic extended foreign key list schema variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $child, $archiveChild): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$child}', 'main')")['rows'];
        $archiveRows = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$archiveChild}', 'archive')")['rows'];

        $t->same(3, count($mainRows));
        $t->same([0, 1, 1], array_column($mainRows, 'id'));
        $t->same(['parent_key', 'tenant_id', 'key_name'], array_column($mainRows, 'from'));
        $t->same(['key_name', 'tenant_id', 'key_name'], array_column($mainRows, 'to'));
        $t->same(['NO ACTION', 'CASCADE', 'CASCADE'], array_column($mainRows, 'on_update'));
        $t->same(['NO ACTION', 'SET NULL', 'SET NULL'], array_column($mainRows, 'on_delete'));
        $t->same(3, count($archiveRows));
        $t->same(['archive_key', 'tenant_id', 'archive_key'], array_column($archiveRows, 'from'));
        $t->same(['NO ACTION', 'SET DEFAULT', 'SET DEFAULT'], array_column($archiveRows, 'on_update'));
        $t->same(['NO ACTION', 'CASCADE', 'CASCADE'], array_column($archiveRows, 'on_delete'));
    };

    $tests[sprintf('real upstream pragma schema dynamic extended virtual metadata and corrupt view variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $brokenView): void {
        $catalog = $catalogFor($variant);
        $functionColumns = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_function_list)')['rows'];
        $moduleColumns = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_module_list)')['rows'];
        $pragmaColumns = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_pragma_list)')['rows'];
        $functions = $catalog->executeSchemaPragma('PRAGMA function_list')['rows'];
        $modules = $catalog->executeTableValuedPragma('pragma_module_list()')['rows'];
        $pragmas = $catalog->executeTableValuedPragma('pragma_pragma_list()')['rows'];
        $broken = $catalog->executeSchemaPragma("PRAGMA table_list({$brokenView})")['rows'][0];

        $t->same(['name', 'builtin', 'type', 'enc', 'narg', 'flags'], array_column($functionColumns, 'name'));
        $t->same(['name'], array_column($moduleColumns, 'name'));
        $t->same(['name'], array_column($pragmaColumns, 'name'));
        $t->same([1], array_values(array_unique(array_column(array_values(array_filter($functions, static fn (array $row): bool => $row['name'] === 'upper')), 'builtin'))));
        $t->same(true, in_array('fts5', array_column($modules, 'name'), true));
        $t->same(true, in_array('pragma_list', array_column($pragmas, 'name'), true));
        $t->same('view', $broken['type']);
        $t->same(0, $broken['ncol']);
        $t->same(0, $broken['strict']);
    };
}

$tests['real upstream pragma schema dynamic shadowing cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma4.test 4.* through 7.* table-valued schema PRAGMA functions resolve unqualified and schema-qualified targets differently',
        'pragma5.test 1.0 through 3.1 pragma_table_list reports schemas, views, WITHOUT ROWID tables, STRICT tables, and column counts',
        'schema.test schema-4.*, schema-9.*, and schema-10.* require attached-schema cache invalidation while existing PRAGMA cursors remain stable',
        'pragma.test 6.2.*, 6.5.* plus pragma4.test 5.0 through 7.3 cover table_info defaults, composite primary-key ordinals, index_xinfo auxiliary columns, schema-qualified foreign_key_list, virtual PRAGMA tables, and corrupt-view table_list tolerance',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma4.test', $sections[0]);
    $t->contains('pragma5.test', $sections[1]);
    $t->contains('schema.test', $sections[2]);
    $t->contains('pragma.test', $sections[3]);
};

return $tests;
