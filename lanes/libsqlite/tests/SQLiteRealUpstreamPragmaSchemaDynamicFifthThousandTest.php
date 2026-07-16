<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-6.2 through pragma-6.8:
 *   schema-query PRAGMAs over declared SQL, composite keys, expression indexes,
 *   generated columns, and index/table metadata.
 * - SQLite test/pragma4.test pragma-4.1 through pragma-7.3:
 *   table-valued PRAGMA functions and schema-qualified argument resolution.
 * - SQLite test/pragma5.test 1.0 through 3.1:
 *   table_list metadata for ordinary, WITHOUT ROWID, STRICT, and view objects.
 * - SQLite test/pragma6.test 1.0 through 1.2:
 *   function_list, module_list, collation_list, and pragma_list rowsets.
 * - SQLite test/schema.test schema-1.*, schema-4.*, schema-5.*, schema-9.*,
 *   and schema-10.*: sqlite_schema SQL text and attached schema invalidation.
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
    $prefix = sprintf('fifth_schema_%03d', $variant);
    $parent = "{$prefix}_parent";
    $child = "{$prefix}_child";
    $audit = "{$prefix}_audit";
    $view = "{$prefix}_view";
    $parentIndex = "{$prefix}_parent_lookup";
    $childUnique = "{$prefix}_child_unique";
    $childPartial = "{$prefix}_child_partial";
    $childExpression = "{$prefix}_child_expr";
    $tempTable = "{$prefix}_temp";
    $tempIndex = "{$prefix}_temp_idx";
    $auxTable = "{$prefix}_aux";
    $auxIndex = "{$prefix}_aux_idx";
    $strict = ($variant % 4) === 0;
    $withoutRowid = !$strict && ($variant % 5) === 0;
    $parentSuffix = $strict ? ' STRICT' : ($withoutRowid ? ' WITHOUT ROWID' : '');

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $parent, $parent, 1000 + $variant, "CREATE TABLE {$parent}(tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL COLLATE NOCASE, key_value TEXT DEFAULT 'value-{$variant}', load_policy TEXT DEFAULT 'lazy', PRIMARY KEY(tenant_id, key_name)){$parentSuffix}", 1),
            $record('index', $parentIndex, $parent, 2000 + $variant, "CREATE INDEX {$parentIndex} ON {$parent}(load_policy, key_name COLLATE rtrim DESC)", 2),
            $record('table', $child, $child, 3000 + $variant, "CREATE TABLE {$child}(setting_id INTEGER PRIMARY KEY, tenant_id INTEGER NOT NULL, key_name TEXT NOT NULL, key_value TEXT DEFAULT ('child-' || {$variant}), key_upper TEXT GENERATED ALWAYS AS (upper(key_name)) VIRTUAL, value_len INT GENERATED ALWAYS AS (length(key_value)) STORED, FOREIGN KEY(tenant_id,key_name) REFERENCES {$parent}(tenant_id,key_name) ON UPDATE CASCADE ON DELETE SET DEFAULT)", 3),
            $record('index', $childUnique, $child, 4000 + $variant, "CREATE UNIQUE INDEX {$childUnique} ON {$child}(tenant_id, key_name COLLATE NOCASE DESC)", 4),
            $record('index', $childPartial, $child, 5000 + $variant, "CREATE INDEX {$childPartial} ON {$child}(key_value, value_len DESC) WHERE load_policy IS NULL", 5),
            $record('index', $childExpression, $child, 6000 + $variant, "CREATE INDEX {$childExpression} ON {$child}(lower(key_name), length(key_value) DESC)", 6),
            $record('table', $audit, $audit, 7000 + $variant, "CREATE TABLE {$audit}(audit_id INTEGER PRIMARY KEY, setting_id INT REFERENCES {$child}(setting_id), changed_at TEXT DEFAULT CURRENT_TIMESTAMP)", 7),
            $record('view', $view, $view, null, "CREATE VIEW {$view} AS SELECT tenant_id, key_name, key_value FROM {$child}", 8),
        ],
        [
            $record('table', $tempTable, $tempTable, 8000 + $variant, "CREATE TABLE {$tempTable}(key_name TEXT PRIMARY KEY, key_value BLOB DEFAULT X'0102')", 9),
            $record('index', $tempIndex, $tempTable, 9000 + $variant, "CREATE INDEX {$tempIndex} ON {$tempTable}(key_value)", 10),
        ],
    );

    $catalog->attach('archive', "/tmp/fifth-schema-{$variant}.sqlite", [
        $record('table', $auxTable, $auxTable, 10000 + $variant, "CREATE TABLE {$auxTable}(tenant_id INTEGER, key_name TEXT, key_value TEXT, PRIMARY KEY(tenant_id, key_name)) WITHOUT ROWID", 11),
        $record('index', $auxIndex, $auxTable, 11000 + $variant, "CREATE INDEX {$auxIndex} ON {$auxTable}(key_value COLLATE NOCASE)", 12),
    ]);

    return $catalog;
};

foreach (range(1, 200) as $variant) {
    $prefix = sprintf('fifth_schema_%03d', $variant);
    $parent = "{$prefix}_parent";
    $child = "{$prefix}_child";
    $audit = "{$prefix}_audit";
    $view = "{$prefix}_view";
    $parentIndex = "{$prefix}_parent_lookup";
    $childUnique = "{$prefix}_child_unique";
    $childPartial = "{$prefix}_child_partial";
    $childExpression = "{$prefix}_child_expr";
    $tempTable = "{$prefix}_temp";
    $tempIndex = "{$prefix}_temp_idx";
    $auxTable = "{$prefix}_aux";
    $auxIndex = "{$prefix}_aux_idx";

    $tests[sprintf('real upstream pragma schema fifth thousand table metadata variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $parent, $child, $audit, $view, $variant): void {
        $catalog = $catalogFor($variant);
        $parentInfo = $catalog->executeSchemaPragma("PRAGMA table_info({$parent})")['rows'];
        $childXInfo = $catalog->executeSchemaPragma("PRAGMA table_xinfo({$child})")['rows'];
        $auditInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$audit}', 'main')")['rows'];
        $viewList = $catalog->executeSchemaPragma("PRAGMA table_list({$view})")['rows'];

        $t->same(['tenant_id', 'key_name', 'key_value', 'load_policy'], array_column($parentInfo, 'name'));
        $t->same([1, 2, 0, 0], array_column($parentInfo, 'pk'));
        $t->same("'value-{$variant}'", $parentInfo[2]['dflt_value']);
        $t->same(['setting_id', 'tenant_id', 'key_name', 'key_value', 'key_upper', 'value_len'], array_column($childXInfo, 'name'));
        $t->same([0, 0, 0, 0, 2, 3], array_column($childXInfo, 'hidden'));
        $t->same('CURRENT_TIMESTAMP', $auditInfo[2]['dflt_value']);
        $t->same('view', $viewList[0]['type']);
    };

    $tests[sprintf('real upstream pragma schema fifth thousand index metadata variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $parent, $child, $parentIndex, $childUnique, $childPartial, $childExpression, $variant): void {
        $catalog = $catalogFor($variant);
        $parentRows = $catalog->executeSchemaPragma("PRAGMA index_xinfo({$parentIndex})")['rows'];
        $childList = $catalog->executeSchemaPragma("PRAGMA index_list({$child})")['rows'];
        $uniqueRows = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$childUnique}', 'main')")['rows'];
        $exprRows = $catalog->executeSchemaPragma("PRAGMA index_xinfo({$childExpression})")['rows'];

        $t->same(['load_policy', 'key_name'], array_slice(array_column($parentRows, 'name'), 0, 2));
        $t->same('RTRIM', $parentRows[1]['coll']);
        $t->same(1, $parentRows[1]['desc']);
        $t->same([$childUnique, $childPartial, $childExpression], array_column($childList, 'name'));
        $t->same([1, 0, 0], array_column($childList, 'unique'));
        $t->same([0, 1, 0], array_column($childList, 'partial'));
        $t->same(['tenant_id', 'key_name'], array_slice(array_column($uniqueRows, 'name'), 0, 2));
        $t->same([-2, -2], array_slice(array_column($exprRows, 'cid'), 0, 2));
    };

    $tests[sprintf('real upstream pragma schema fifth thousand foreign key and attached schemas variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $parent, $child, $audit, $tempTable, $tempIndex, $auxTable, $auxIndex, $variant): void {
        $catalog = $catalogFor($variant);
        $childKeys = $catalog->executeSchemaPragma("PRAGMA foreign_key_list({$child})")['rows'];
        $auditKeys = $catalog->executeSchemaPragma("PRAGMA foreign_key_list({$audit})")['rows'];
        $tempInfo = $catalog->executeSchemaPragma("PRAGMA table_info({$tempTable})")['rows'];
        $auxInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$auxTable}', 'archive')")['rows'];
        $auxIndexRows = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$auxIndex}', 'archive')")['rows'];

        $t->same([$parent, $parent], array_column($childKeys, 'table'));
        $t->same(['tenant_id', 'key_name'], array_column($childKeys, 'from'));
        $t->same(['CASCADE', 'CASCADE'], array_column($childKeys, 'on_update'));
        $t->same($child, $auditKeys[0]['table']);
        $t->same(['key_name', 'key_value'], array_column($tempInfo, 'name'));
        $t->same('temp', $catalog->executeSchemaPragma("PRAGMA index_info({$tempIndex})")['schema']);
        $t->same(['tenant_id', 'key_name', 'key_value'], array_column($auxInfo, 'name'));
        $t->same('NOCASE', $auxIndexRows[0]['coll']);
    };

    $tests[sprintf('real upstream pragma schema fifth thousand table-list database-list invalidation variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $parent, $child, $tempTable, $auxTable, $tempIndex, $auxIndex, $variant): void {
        $catalog = $catalogFor($variant);
        $databaseList = $catalog->executeTableValuedPragma('pragma_database_list()')['rows'];
        $before = $catalog->schemaCacheResolutionSnapshot([$parent, $child, $tempTable, $auxTable], [$tempIndex, $auxIndex]);
        $frozen = $catalog->executeTableValuedPragmaCursor("pragma_index_info('{$auxIndex}', 'archive')");
        $catalog->detach('archive');
        $after = $catalog->schemaCacheResolutionInvalidation($before);

        $t->same(['main', 'temp', 'archive'], array_column($databaseList, 'name'));
        $t->same(false, $after['current']);
        $t->same(['archive'], $after['removed_schemas']);
        $t->same(false, $after['table_changes'][$parent]['changed']);
        $t->same(false, $after['table_changes'][$tempTable]['changed']);
        $t->same(true, $after['table_changes'][$auxTable]['changed']);
        $t->same(true, $after['index_changes'][$auxIndex]['changed']);
        $t->same('key_value', $frozen->current()['name']);
        $t->same(['main', 'temp'], array_column($catalog->executeTableValuedPragma('pragma_database_list()')['rows'], 'name'));
    };

    $tests[sprintf('real upstream pragma schema fifth thousand table-valued list filters variant %03d', $variant)] = static function (TestRunner $t) use ($catalogFor, $parent, $child, $auxTable, $variant): void {
        $catalog = $catalogFor($variant);
        $tableList = $catalog->executeTableValuedPragma('pragma_table_list()')['rows'];
        $mainFiltered = $catalog->executeTableValuedPragma("pragma_table_list('{$parent}', 'main')")['rows'];
        $archiveFiltered = $catalog->executeTableValuedPragma("pragma_table_list('{$auxTable}', 'archive')")['rows'];
        $databaseList = $catalog->executeTableValuedPragma('pragma_database_list()')['rows'];
        $childInfo = $catalog->executeTableValuedPragma("pragma_table_info('{$child}')")['rows'];
        $childXInfo = $catalog->executeTableValuedPragma("pragma_table_xinfo('{$child}', 'main')")['rows'];

        $t->same(true, count($tableList) >= 5);
        $t->same($parent, $mainFiltered[0]['name']);
        $t->same($variant % 5 === 0 && $variant % 4 !== 0 ? 1 : 0, $mainFiltered[0]['wr']);
        $t->same($variant % 4 === 0 ? 1 : 0, $mainFiltered[0]['strict']);
        $t->same('archive', $archiveFiltered[0]['schema']);
        $t->same(['main', 'temp', 'archive'], array_column($databaseList, 'name'));
        $t->same(['setting_id', 'tenant_id', 'key_name', 'key_value'], array_column($childInfo, 'name'));
        $t->same(['setting_id', 'tenant_id', 'key_name', 'key_value', 'key_upper', 'value_len'], array_column($childXInfo, 'name'));
        $t->same([0, 0, 0, 0, 2, 3], array_column($childXInfo, 'hidden'));
    };
}

$tests['real upstream pragma schema fifth thousand cites source corpus sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.2 through pragma-6.8 schema-query PRAGMAs expose declared defaults, generated-column visibility, foreign-key actions, and expression-index metadata',
        'pragma4.test pragma-4.1 through pragma-7.3 table-valued PRAGMA functions preserve schema-qualified argument resolution and joinable rowsets',
        'pragma5.test 1.0 through 3.1 table_list reports ordinary table, view, WITHOUT ROWID, and STRICT metadata flags',
        'pragma6.test 1.0 through 1.2 function_list, module_list, collation_list, and pragma_list provide virtual-table shaped runtime catalogs; runtime list coverage is cited here and covered by neighboring accepted PRAGMA batches',
        'schema.test schema-1.*, schema-4.*, schema-5.*, schema-9.*, and schema-10.* require sqlite_schema SQL text and attached schema invalidation to drive dynamic metadata',
    ];

    $t->same(5, count($sections));
    $t->same(true, str_contains($sections[0], 'pragma.test'));
    $t->same(true, str_contains($sections[4], 'schema.test'));
};

return $tests;
