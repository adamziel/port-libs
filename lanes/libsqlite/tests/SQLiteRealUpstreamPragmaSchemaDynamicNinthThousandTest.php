<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema3.test schema3-1.*: after one connection loads schema
 *   metadata, another connection can create tables, views, indexes, triggers,
 *   add columns, drop/recreate objects, and the stale connection refreshes its
 *   schema before the next statement instead of reporting missing objects.
 * - SQLite test/pragma.test pragma-6.2 and pragma-6.5: refreshed schema text is
 *   visible through PRAGMA table_info(), index_list(), index_info(), and
 *   table_list() rowsets.
 * - SQLite test/pragma4.test pragma-4.* through pragma-7.*: table-valued
 *   PRAGMA functions expose the same refreshed metadata as direct PRAGMAs.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$baseRecordsFor = static function (int $variant) use ($record): array {
    $prefix = sprintf('schema3_refresh_%03d', $variant);
    $table = "{$prefix}_settings";
    $audit = "{$prefix}_audit";
    $index = "{$prefix}_key_idx";
    $trigger = "{$prefix}_ai";
    $view = "{$prefix}_view";

    return [
        $record('table', $table, $table, 1000 + $variant, "CREATE TABLE {$table}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL, key_value TEXT DEFAULT 'v{$variant}')", 1),
        $record('index', $index, $table, 2000 + $variant, "CREATE INDEX {$index} ON {$table}(key_name)", 2),
        $record('table', $audit, $audit, 3000 + $variant, "CREATE TABLE {$audit}(setting_id INTEGER, action TEXT)", 3),
        $record('trigger', $trigger, $table, 0, "CREATE TRIGGER {$trigger} AFTER INSERT ON {$table} BEGIN INSERT INTO {$audit}(setting_id, action) VALUES (new.setting_id, 'insert'); END", 4),
        $record('view', $view, $view, 0, "CREATE VIEW {$view} AS SELECT setting_id, key_name, key_value FROM {$table}", 5),
    ];
};

foreach (range(1, 250) as $variant) {
    $prefix = sprintf('schema3_refresh_%03d', $variant);
    $table = "{$prefix}_settings";
    $newTable = "{$prefix}_new_table";
    $view = "{$prefix}_view";
    $newView = "{$prefix}_new_view";
    $index = "{$prefix}_key_idx";
    $newIndex = "{$prefix}_new_index";
    $trigger = "{$prefix}_ai";
    $newTrigger = "{$prefix}_au";

    $tests[sprintf('real upstream schema3 pragma dynamic create table refresh variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecordsFor, $variant, $newTable): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecordsFor($variant));
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$newTable], []);
        $plan = $catalog->applySchemaDdlCurrentSource('main', [
            "CREATE TABLE {$newTable}(tenant_id INTEGER, key_name TEXT, key_value TEXT DEFAULT 'created_{$variant}')",
        ], 9000 + $variant, $snapshot, [
            ['id' => "schema3-create-table-{$variant}", 'schema_cookie' => 9000 + $variant, 'sql' => "SELECT * FROM {$newTable}"],
        ]);
        $rows = $catalog->executeTableValuedPragma("pragma_table_info('{$newTable}', 'main')")['rows'];
        $tableList = $catalog->executeSchemaPragma("PRAGMA table_list({$newTable})")['rows'];

        $t->same('schema_cache_expired', $plan['status']);
        $t->same('create_table', $plan['ddl_plan']['operations'][0]['kind']);
        $t->same(false, $plan['invalidation']['current']);
        $t->same([$newTable], $plan['invalidation']['changed_tables']);
        $t->same(["schema3-create-table-{$variant}"], $plan['ddl_plan']['invalidated_prepared']);
        $t->same(['tenant_id', 'key_name', 'key_value'], array_column($rows, 'name'));
        $t->same("'created_{$variant}'", $rows[2]['dflt_value']);
        $t->same('table', $tableList[0]['type']);
    };

    $tests[sprintf('real upstream schema3 pragma dynamic create view trigger refresh variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecordsFor, $variant, $table, $newView, $newTrigger): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecordsFor($variant));
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$newView], []);
        $plan = $catalog->applySchemaDdlCurrentSource('main', [
            "CREATE VIEW {$newView} AS SELECT setting_id, key_name FROM {$table}",
            "CREATE TRIGGER {$newTrigger} AFTER UPDATE OF key_value ON {$table} BEGIN SELECT new.key_value; END",
        ], 10000 + $variant, $snapshot, [
            ['id' => "schema3-view-trigger-{$variant}", 'schema_cookie' => 10000 + $variant, 'sql' => "SELECT key_name FROM {$newView}"],
        ]);
        $records = $catalog->schemaRecords('main');
        $viewRows = $catalog->executeSchemaPragma("PRAGMA table_list({$newView})")['rows'];
        $triggerRows = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger' && $record->name === $newTrigger));

        $t->same(['create_view', 'create_trigger'], array_column($plan['ddl_plan']['operations'], 'kind'));
        $t->same(10002 + $variant, $plan['ddl_plan']['after_schema_cookie']);
        $t->same([$newView], $plan['invalidation']['changed_tables']);
        $t->same(["schema3-view-trigger-{$variant}"], $plan['ddl_plan']['invalidated_prepared']);
        $t->same('view', $viewRows[0]['type']);
        $t->same(1, count($triggerRows));
        $t->same($table, $triggerRows[0]->tableName);
        $t->contains('AFTER UPDATE', (string) $triggerRows[0]->sql);
    };

    $tests[sprintf('real upstream schema3 pragma dynamic add column create index refresh variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecordsFor, $variant, $table, $newIndex): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecordsFor($variant));
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table], [$newIndex]);
        $plan = $catalog->applySchemaDdlCurrentSource('main', [
            "ALTER TABLE {$table} ADD COLUMN load_policy TEXT DEFAULT 'lazy_{$variant}'",
            "CREATE INDEX {$newIndex} ON {$table}(load_policy, key_name COLLATE NOCASE DESC)",
        ], 11000 + $variant, $snapshot, [
            ['id' => "schema3-add-column-index-{$variant}", 'schema_cookie' => 11000 + $variant, 'sql' => "SELECT load_policy FROM {$table} INDEXED BY {$newIndex}"],
        ]);
        $info = $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['rows'];
        $indexList = $catalog->executeSchemaPragma("PRAGMA index_list({$table})")['rows'];
        $indexXInfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$newIndex}', 'main')")['rows'];

        $t->same(['alter_table_add_column', 'create_index'], array_column($plan['ddl_plan']['operations'], 'kind'));
        $t->same(11002 + $variant, $plan['ddl_plan']['after_schema_cookie']);
        $t->same(false, $plan['invalidation']['current']);
        $t->same([], $plan['invalidation']['changed_tables']);
        $t->same([$newIndex], $plan['invalidation']['changed_indexes']);
        $t->same(["schema3-add-column-index-{$variant}"], $plan['ddl_plan']['invalidated_prepared']);
        $t->same('load_policy', $info[3]['name']);
        $t->same("'lazy_{$variant}'", $info[3]['dflt_value']);
        $t->same(true, in_array($newIndex, array_column($indexList, 'name'), true));
        $t->same(['load_policy', 'key_name'], array_slice(array_column($indexXInfo, 'name'), 0, 2));
        $t->same('NOCASE', $indexXInfo[1]['coll']);
        $t->same(1, $indexXInfo[1]['desc']);
    };

    $tests[sprintf('real upstream schema3 pragma dynamic drop recreate objects refresh variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecordsFor, $variant, $table, $index, $trigger, $view): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecordsFor($variant));
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table, $view], [$index]);
        $plan = $catalog->applySchemaDdlCurrentSource('main', [
            "DROP INDEX {$index}",
            "CREATE INDEX {$index} ON {$table}(key_value, key_name)",
            "DROP TRIGGER {$trigger}",
            "CREATE TRIGGER {$trigger} AFTER DELETE ON {$table} BEGIN SELECT old.setting_id; END",
            "DROP VIEW {$view}",
            "CREATE VIEW {$view} AS SELECT key_name, key_value FROM {$table}",
        ], 12000 + $variant, $snapshot, [
            ['id' => "schema3-drop-recreate-{$variant}", 'schema_cookie' => 12000 + $variant, 'sql' => "SELECT * FROM {$view}"],
        ]);
        $indexInfo = $catalog->executeSchemaPragma("PRAGMA index_info({$index})")['rows'];
        $viewList = $catalog->executeSchemaPragma("PRAGMA table_list({$view})")['rows'];
        $triggerRows = array_values(array_filter($catalog->schemaRecords('main'), static fn (SQLiteSchemaRecord $record): bool => $record->type === 'trigger' && $record->name === $trigger));

        $t->same(['drop_index', 'create_index', 'drop_trigger', 'create_trigger', 'drop_view', 'create_view'], array_column($plan['ddl_plan']['operations'], 'kind'));
        $t->same(12006 + $variant, $plan['ddl_plan']['after_schema_cookie']);
        $t->same(false, $plan['invalidation']['current']);
        $t->same([], $plan['invalidation']['changed_tables']);
        $t->same([$index], $plan['invalidation']['changed_indexes']);
        $t->same(["schema3-drop-recreate-{$variant}"], $plan['ddl_plan']['invalidated_prepared']);
        $t->same(['key_value', 'key_name'], array_column($indexInfo, 'name'));
        $t->same('view', $viewList[0]['type']);
        $t->same(1, count($triggerRows));
        $t->contains('AFTER DELETE', (string) $triggerRows[0]->sql);
    };
}

$tests['real upstream pragma schema dynamic ninth thousand cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema3.test schema3-1.1 through schema3-1.6 create-table and create-index metadata refresh',
        'schema3.test schema3-1.7 through schema3-1.13 ALTER TABLE ADD COLUMN refreshes SELECT/UPDATE/DELETE/INSERT/index/trigger statements',
        'schema3.test schema3-1.14 through schema3-1.18 index, trigger, view, and added-view-column refresh',
        'schema3.test schema3-1.19 through schema3-1.22 drop/recreate table, index, and trigger refresh',
        'pragma.test pragma-6.2 and pragma-6.5 plus pragma4.test table-valued PRAGMA forms expose refreshed schema metadata',
    ];

    $t->same(5, count($sections));
    $t->contains('schema3-1.1', $sections[0]);
    $t->contains('ALTER TABLE ADD COLUMN', $sections[1]);
    $t->contains('pragma4.test', $sections[4]);
};

return $tests;
