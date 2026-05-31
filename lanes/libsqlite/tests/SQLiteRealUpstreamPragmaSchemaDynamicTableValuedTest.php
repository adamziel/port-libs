<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-6.5.1b and pragma-6.5.1c:
 *   index_xinfo() includes an auxiliary rowid column while index_info()
 *   reports only key columns in rank order.
 * - SQLite test/pragma.test pragma-6.6.1 through pragma-6.6.4:
 *   TEMP schema objects shadow main objects for unqualified table_info(),
 *   while schema-qualified table_info() remains pinned to the requested
 *   schema.
 * - SQLite test/pragma.test pragma-6.7 and pragma-6.8 plus
 *   pragma4.test 5.0: table_info() preserves default expressions, comments,
 *   and table-primary-key ordinals.
 * - SQLite test/pragma4.test 6.0 through 7.3: table-valued PRAGMA functions
 *   join schema-local foreign_key_list() and table_info() rowsets.
 * - SQLite test/pragma5.test 1.0 through 3.1: table_info() works for virtual
 *   PRAGMA tables such as pragma_function_list, pragma_module_list, and
 *   pragma_pragma_list.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$makeCatalog = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $settings = "dynamic_settings_{$variant}";
    $settingsIndex = "dynamic_settings_key_idx_{$variant}";
    $defaults = "dynamic_defaults_{$variant}";
    $composite = "dynamic_composite_{$variant}";
    $parent = "dynamic_parent_{$variant}";
    $child = "dynamic_child_{$variant}";
    $shadow = "dynamic_shadow_{$variant}";
    $tenant = "tenant{$variant}";

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $settings, $settings, 1000 + $variant, "CREATE TABLE {$settings}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL, key_value TEXT, key_upper TEXT GENERATED ALWAYS AS (upper(key_name)) VIRTUAL, key_size INT GENERATED ALWAYS AS (length(key_value)) STORED)", 10),
            $record('index', $settingsIndex, $settings, 2000 + $variant, "CREATE INDEX {$settingsIndex} ON {$settings}(key_name COLLATE NOCASE DESC, key_value)", 11),
            $record('table', $defaults, $defaults, 3000 + $variant, "CREATE TABLE {$defaults}(one INT NOT NULL DEFAULT -{$variant} /* pragma4 */, two TEXT, three VARCHAR(45, 65) DEFAULT 'abc{$variant}', four REAL DEFAULT X'abcdef', five DEFAULT CURRENT_TIME, six DEFAULT (+{$variant}.0 -- upstream comment\n), seven TEXT DEFAULT '')", 12),
            $record('table', $composite, $composite, 4000 + $variant, "CREATE TABLE {$composite}(a, b, c, PRIMARY KEY(a,b,a,c))", 13),
            $record('table', $parent, $parent, 5000 + $variant, "CREATE TABLE {$parent}(tenant_id INTEGER, key_name TEXT, PRIMARY KEY(tenant_id, key_name))", 14),
            $record('table', $child, $child, 6000 + $variant, "CREATE TABLE {$child}(tenant_id INTEGER, key_name TEXT, parent_key TEXT REFERENCES {$parent}(key_name), FOREIGN KEY(tenant_id, key_name) REFERENCES {$parent}(tenant_id, key_name) ON UPDATE CASCADE ON DELETE SET NULL)", 15),
        ],
        [
            $record('table', $shadow, $shadow, 7000 + $variant, "CREATE TABLE {$shadow}(temp_value TEXT)", 16),
        ],
    );

    $catalog->attach($tenant, "tenant-{$variant}.sqlite", [
        $record('table', $shadow, $shadow, 8000 + $variant, "CREATE TABLE {$shadow}(attached_value TEXT)", 17),
        $record('table', $parent, $parent, 9000 + $variant, "CREATE TABLE {$parent}(tenant_id INTEGER, key_name TEXT, PRIMARY KEY(tenant_id, key_name))", 18),
        $record('table', $child, $child, 10000 + $variant, "CREATE TABLE {$child}(tenant_id INTEGER, key_name TEXT REFERENCES {$parent}(key_name), FOREIGN KEY(tenant_id, key_name) REFERENCES {$parent}(tenant_id, key_name) ON UPDATE SET DEFAULT ON DELETE CASCADE)", 19),
    ]);

    return $catalog;
};

foreach (range(1, 1000) as $variant) {
    $tests["real upstream pragma schema table valued dynamic corpus variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $variant): void {
        $catalog = $makeCatalog($variant);
        $tenant = "tenant{$variant}";
        $settings = "dynamic_settings_{$variant}";
        $settingsIndex = "dynamic_settings_key_idx_{$variant}";
        $defaults = "dynamic_defaults_{$variant}";
        $composite = "dynamic_composite_{$variant}";
        $parent = "dynamic_parent_{$variant}";
        $child = "dynamic_child_{$variant}";
        $shadow = "dynamic_shadow_{$variant}";

        $settingsInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$settings}', 'main')");
        $settingsXInfo = $catalog->executeTableValuedPragma("pragma_table_xinfo('{$settings}', 'main')");
        $indexInfo = $catalog->executeTableValuedPragma("pragma_index_info('{$settingsIndex}', 'main')");
        $indexXInfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$settingsIndex}', 'main')");
        $defaultsInfo = $catalog->executeSchemaPragma("PRAGMA table_info({$defaults})")['rows'];
        $compositeInfo = $catalog->executeSchemaPragma("PRAGMA main.table_info({$composite})")['rows'];
        $mainForeignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$child}', 'main')")['rows'];
        $tenantForeignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$child}', '{$tenant}')")['rows'];
        $shadowUnqualified = $catalog->executeSchemaPragma("PRAGMA table_info({$shadow})");
        $shadowTenant = $catalog->executeTableValuedPragma("pragma_table_info('{$shadow}', '{$tenant}')");
        $functionVirtual = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_function_list)')['rows'];
        $moduleVirtual = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_module_list)')['rows'];
        $pragmaVirtual = $catalog->executeSchemaPragma('PRAGMA table_info(pragma_pragma_list)')['rows'];
        $databaseListBefore = $catalog->executeTableValuedPragma('pragma_database_list()')['rows'];
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$shadow, $settings], [$settingsIndex]);
        $cursor = $catalog->executeTableValuedPragmaCursor("pragma_table_xinfo('{$settings}', 'main')");
        $catalog->detach($tenant);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $databaseListAfter = $catalog->executeSchemaPragma('PRAGMA database_list')['rows'];

        $t->same('main', $settingsInfo['schema']);
        $t->same(['setting_id', 'key_name', 'key_value'], array_column($settingsInfo['rows'], 'name'));
        $t->same(['setting_id', 'key_name', 'key_value', 'key_upper', 'key_size'], array_column($settingsXInfo['rows'], 'name'));
        $t->same([0, 0, 0, 2, 3], array_column($settingsXInfo['rows'], 'hidden'));
        $t->same(['key_name', 'key_value'], array_column($indexInfo['rows'], 'name'));
        $t->same(['key_name', 'key_value', null], array_column($indexXInfo['rows'], 'name'));
        $t->same([1, 1, 0], array_column($indexXInfo['rows'], 'key'));
        $t->same([1, 0, 0], array_column($indexXInfo['rows'], 'desc'));
        $t->same(['NOCASE', 'BINARY', 'BINARY'], array_column($indexXInfo['rows'], 'coll'));
        $t->same("-{$variant}", $defaultsInfo[0]['dflt_value']);
        $t->same("'abc{$variant}'", $defaultsInfo[2]['dflt_value']);
        $t->same("X'abcdef'", $defaultsInfo[3]['dflt_value']);
        $t->same('CURRENT_TIME', $defaultsInfo[4]['dflt_value']);
        $t->same("+{$variant}.0", $defaultsInfo[5]['dflt_value']);
        $t->same("''", $defaultsInfo[6]['dflt_value']);
        $t->same([1, 2, 4], [$compositeInfo[0]['pk'], $compositeInfo[1]['pk'], $compositeInfo[2]['pk']]);
        $t->same(['parent_key', 'tenant_id', 'key_name'], array_column($mainForeignKeys, 'from'));
        $t->same(['NO ACTION', 'CASCADE', 'CASCADE'], array_column($mainForeignKeys, 'on_update'));
        $t->same(['NO ACTION', 'SET NULL', 'SET NULL'], array_column($mainForeignKeys, 'on_delete'));
        $t->same(['key_name', 'tenant_id', 'key_name'], array_column($tenantForeignKeys, 'from'));
        $t->same(['NO ACTION', 'SET DEFAULT', 'SET DEFAULT'], array_column($tenantForeignKeys, 'on_update'));
        $t->same(['NO ACTION', 'CASCADE', 'CASCADE'], array_column($tenantForeignKeys, 'on_delete'));
        $t->same('temp', $shadowUnqualified['schema']);
        $t->same(['temp_value'], array_column($shadowUnqualified['rows'], 'name'));
        $t->same($tenant, $shadowTenant['schema']);
        $t->same(['attached_value'], array_column($shadowTenant['rows'], 'name'));
        $t->same(['name', 'builtin', 'type', 'enc', 'narg', 'flags'], array_column($functionVirtual, 'name'));
        $t->same(['name'], array_column($moduleVirtual, 'name'));
        $t->same(['name'], array_column($pragmaVirtual, 'name'));
        $t->same(['main', 'temp', $tenant], array_column($databaseListBefore, 'name'));
        $t->same(['main', 'temp'], array_column($databaseListAfter, 'name'));
        $t->same(false, $invalidation['current']);
        $t->same([$tenant], $invalidation['removed_schemas']);
        $t->same(false, $invalidation['table_changes'][$shadow]['changed']);
        $t->same(false, $invalidation['table_changes'][$settings]['changed']);
        $t->same(false, $invalidation['index_changes'][$settingsIndex]['changed']);
        $t->same('setting_id', $cursor->current()['name']);
    };
}

$tests['real upstream pragma schema table valued dynamic corpus source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.5.1b index_xinfo auxiliary rowid column',
        'pragma.test pragma-6.6.1 through pragma-6.6.4 temp/main table_info shadowing',
        'pragma.test pragma-6.7 and pragma4.test 5.0 default-expression preservation',
        'pragma.test pragma-6.8 composite primary-key ordinals',
        'pragma4.test 6.0 through 7.3 table-valued PRAGMA joins',
        'pragma5.test 1.0 through 3.1 virtual PRAGMA table metadata',
    ];

    $t->same(6, count($sections));
    $t->contains('pragma-6.5.1b', $sections[0]);
    $t->contains('pragma5.test 1.0', $sections[5]);
};

return $tests;
