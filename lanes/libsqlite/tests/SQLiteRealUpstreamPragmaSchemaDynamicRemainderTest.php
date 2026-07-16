<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

// Source truth:
// - upstream pragma.test: pragma-6.1 database_list, pragma-6.2 table_info,
//   pragma-6.3 foreign_key_list, pragma-6.4 index_list, pragma-6.5 index_info
//   and index_xinfo.
// - upstream pragma4.test: 4.1 through 4.5 attached-schema table-valued
//   pragma lookup and stale-object empty rowset behavior.
foreach (range(1, 1000) as $variant) {
    $mainTable = 'app_schema_settings_' . $variant;
    $mainIndex = 'app_schema_settings_key_' . $variant;
    $tempTable = 'app_schema_temp_settings_' . $variant;
    $auxTable = 'tenant_schema_settings_' . $variant;
    $auxIndex = 'tenant_schema_settings_key_' . $variant;
    $parentTable = 'tenant_schema_parent_' . $variant;
    $auxSchema = 'tenant' . $variant;
    $auxFile = 'tenant-schema-' . $variant . '.db';

    $makeCatalog = static function () use (
        $variant,
        $mainTable,
        $mainIndex,
        $tempTable,
        $auxTable,
        $auxIndex,
        $parentTable,
        $auxSchema,
        $auxFile
    ): SQLiteAttachedSchemaCatalog {
        $mainRecords = [
            new SQLiteSchemaRecord(
                'table',
                $mainTable,
                $mainTable,
                10 + $variant,
                "CREATE TABLE {$mainTable}(
                    setting_id INTEGER PRIMARY KEY,
                    key_name TEXT NOT NULL DEFAULT 'main_{$variant}',
                    key_value TEXT DEFAULT (json_object('variant',{$variant})),
                    load_policy TEXT DEFAULT 'eager',
                    UNIQUE(key_name)
                )",
                100 + $variant,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $mainTable . '_1', $mainTable, 200 + $variant, null, 200 + $variant),
            new SQLiteSchemaRecord('index', $mainIndex, $mainTable, 300 + $variant, "CREATE INDEX {$mainIndex} ON {$mainTable}(load_policy, key_name COLLATE nocase DESC)", 300 + $variant),
        ];

        $tempRecords = [
            new SQLiteSchemaRecord(
                'table',
                $mainTable,
                $mainTable,
                400 + $variant,
                "CREATE TABLE {$mainTable}(
                    setting_id INTEGER PRIMARY KEY,
                    temp_value TEXT DEFAULT 'temp_shadow_{$variant}'
                )",
                400 + $variant,
            ),
            new SQLiteSchemaRecord(
                'table',
                $tempTable,
                $tempTable,
                500 + $variant,
                "CREATE TABLE {$tempTable}(key_name TEXT, temp_value TEXT)",
                500 + $variant,
            ),
        ];

        $auxRecords = [
            new SQLiteSchemaRecord(
                'table',
                $parentTable,
                $parentTable,
                600 + $variant,
                "CREATE TABLE {$parentTable}(tenant_id INTEGER PRIMARY KEY, tenant_name TEXT)",
                600 + $variant,
            ),
            new SQLiteSchemaRecord(
                'table',
                $auxTable,
                $auxTable,
                700 + $variant,
                "CREATE TABLE {$auxTable}(
                    tenant_id INTEGER NOT NULL REFERENCES {$parentTable}(tenant_id) ON UPDATE CASCADE ON DELETE RESTRICT,
                    key_name TEXT NOT NULL DEFAULT 'aux_{$variant}',
                    key_value TEXT DEFAULT NULL,
                    load_policy TEXT DEFAULT 'lazy',
                    PRIMARY KEY(key_name, tenant_id)
                ) WITHOUT ROWID",
                700 + $variant,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $auxTable . '_1', $auxTable, 800 + $variant, null, 800 + $variant),
            new SQLiteSchemaRecord('index', $auxIndex, $auxTable, 900 + $variant, "CREATE INDEX {$auxIndex} ON {$auxTable}(tenant_id, key_name COLLATE nocase DESC)", 900 + $variant),
        ];

        $catalog = new SQLiteAttachedSchemaCatalog($mainRecords, $tempRecords);
        $catalog->attach($auxSchema, $auxFile, $auxRecords);

        return $catalog;
    };

    $tests["real upstream pragma schema dynamic remainder attached table valued variant {$variant}"] = static function (TestRunner $t) use (
        $makeCatalog,
        $variant,
        $mainTable,
        $mainIndex,
        $auxTable,
        $auxIndex,
        $parentTable,
        $auxSchema,
        $auxFile
    ): void {
        $catalog = $makeCatalog();

        $databaseList = $catalog->executeTableValuedPragma('pragma_database_list()')['rows'];
        $unqualified = $catalog->executeSchemaPragma("PRAGMA table_info({$mainTable})")['rows'];
        $mainRows = $catalog->executeSchemaPragma("PRAGMA main.table_info({$mainTable})")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_table_info('{$auxTable}', '{$auxSchema}')")['rows'];
        $auxIndexes = $catalog->executeTableValuedPragma("pragma_index_list('{$auxTable}', '{$auxSchema}')")['rows'];
        $mainIndexInfo = $catalog->executeSchemaPragma("PRAGMA main.index_xinfo({$mainIndex})")['rows'];
        $auxIndexInfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$auxIndex}', '{$auxSchema}')")['rows'];
        $foreignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$auxTable}', '{$auxSchema}')")['rows'];
        $tableList = $catalog->executeTableValuedPragma("pragma_table_list('{$auxTable}', '{$auxSchema}')")['rows'];
        $missingAfterDrop = $catalog->executeTableValuedPragma("pragma_index_info('missing_index_{$variant}', '{$auxSchema}')")['rows'];

        $t->same(['main', 'temp', $auxSchema], array_column($databaseList, 'name'));
        $t->same($auxFile, $databaseList[2]['file']);
        $t->same('temp_value', $unqualified[1]['name']);
        $t->same('key_name', $mainRows[1]['name']);
        $t->same("'main_{$variant}'", $mainRows[1]['dflt_value']);
        $t->same('key_name', $auxRows[1]['name']);
        $t->same("'aux_{$variant}'", $auxRows[1]['dflt_value']);
        $t->same('sqlite_autoindex_' . $auxTable . '_1', $auxIndexes[0]['name']);
        $t->same('pk', $auxIndexes[0]['origin']);
        $t->same($auxIndex, $auxIndexes[1]['name']);
        $t->same('key_name', $mainIndexInfo[1]['name']);
        $t->same('NOCASE', $mainIndexInfo[1]['coll']);
        $t->same(1, $mainIndexInfo[1]['desc']);
        $t->same('tenant_id', $auxIndexInfo[0]['name']);
        $t->same('key_name', $auxIndexInfo[1]['name']);
        $t->same('NOCASE', $auxIndexInfo[1]['coll']);
        $t->same($parentTable, $foreignKeys[0]['table']);
        $t->same('tenant_id', $foreignKeys[0]['from']);
        $t->same('CASCADE', $foreignKeys[0]['on_update']);
        $t->same('RESTRICT', $foreignKeys[0]['on_delete']);
        $t->same($auxSchema, $tableList[0]['schema']);
        $t->same(4, $tableList[0]['ncol']);
        $t->same(1, $tableList[0]['wr']);
        $t->same([], $missingAfterDrop);
    };
}

return $tests;
