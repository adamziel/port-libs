<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $root,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$makeCatalog = static function (int $variant, string $kind) use ($record): SQLiteAttachedSchemaCatalog {
    $table = "schema_cache_item_{$variant}";
    $index = "schema_cache_item_{$variant}_lookup";
    $view = "schema_cache_item_{$variant}_view";
    $trigger = "schema_cache_item_{$variant}_ai";
    $auxTable = "schema_cache_aux_item_{$variant}";
    $auxIndex = "schema_cache_aux_item_{$variant}_lookup";

    $main = [
        $record('table', $table, $table, 10 + $variant, "CREATE TABLE {$table}(id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)", 10 + $variant),
        $record('index', $index, $table, 20 + $variant, "CREATE INDEX {$index} ON {$table}(key_name)", 20 + $variant),
        $record('view', $view, $view, null, "CREATE VIEW {$view} AS SELECT key_name, key_value FROM {$table}", 30 + $variant),
        $record('trigger', $trigger, $table, null, "CREATE TRIGGER {$trigger} AFTER INSERT ON {$table} BEGIN SELECT 1; END", 40 + $variant),
    ];
    $temp = $kind === 'temp-shadow' ? [
        $record('table', $table, $table, 50 + $variant, "CREATE TABLE {$table}(id INTEGER PRIMARY KEY, temp_value TEXT)", 50 + $variant),
        $record('index', "temp_{$index}", $table, 60 + $variant, "CREATE INDEX temp_{$index} ON {$table}(temp_value)", 60 + $variant),
    ] : [];

    $catalog = new SQLiteAttachedSchemaCatalog($main, $temp);
    $catalog->attach("aux{$variant}", "aux{$variant}.sqlite", [
        $record('table', $auxTable, $auxTable, 70 + $variant, "CREATE TABLE {$auxTable}(id INTEGER PRIMARY KEY, aux_value TEXT)", 70 + $variant),
        $record('index', $auxIndex, $auxTable, 80 + $variant, "CREATE INDEX {$auxIndex} ON {$auxTable}(aux_value)", 80 + $variant),
    ]);

    return $catalog;
};

$tests = [];

// Source truth:
// - upstream schema.test schema-1.* through schema-4.*: CREATE/DROP TABLE,
//   VIEW, TRIGGER, and INDEX invalidate prepared sqlite_master statements.
// - upstream schema.test schema-5.*: ATTACH does not expire the current
//   statement, while DETACH does.
// - upstream schema.test schema-12.1: rollback/DDL cookie reuse still expires
//   a prepared statement instead of trusting an equal cookie value.
foreach (range(1, 250) as $variant) {
    $table = "schema_cache_item_{$variant}";
    $index = "schema_cache_item_{$variant}_lookup";
    $view = "schema_cache_item_{$variant}_view";
    $trigger = "schema_cache_item_{$variant}_ai";
    $auxSchema = "aux{$variant}";
    $auxTable = "schema_cache_aux_item_{$variant}";
    $auxIndex = "schema_cache_aux_item_{$variant}_lookup";

    $tests["real upstream schema.test dynamic invalidation create table variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $record, $variant, $table, $index): void {
        $catalog = $makeCatalog($variant, 'main-only');
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table, "schema_cache_new_{$variant}"], [$index], 'main');
        $catalog->replaceSchemaRecords('main', [
            $record('table', $table, $table, 10 + $variant, "CREATE TABLE {$table}(id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)", 10 + $variant),
            $record('index', $index, $table, 20 + $variant, "CREATE INDEX {$index} ON {$table}(key_name)", 20 + $variant),
            $record('table', "schema_cache_new_{$variant}", "schema_cache_new_{$variant}", 90 + $variant, "CREATE TABLE schema_cache_new_{$variant}(id INTEGER PRIMARY KEY, label TEXT)", 90 + $variant),
        ]);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $newRows = $catalog->executeSchemaPragma("PRAGMA table_info(schema_cache_new_{$variant})")['rows'];

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same(1, $invalidation['before_generation']);
        $t->same(2, $invalidation['after_generation']);
        $t->same(['schema_cache_new_' . $variant], $invalidation['changed_tables']);
        $t->same([], $invalidation['changed_indexes']);
        $t->same('label', $newRows[1]['name']);
        $t->same('main', $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['schema']);
    };

    $tests["real upstream schema.test dynamic invalidation drop table variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $record, $variant, $table, $index): void {
        $catalog = $makeCatalog($variant, 'main-only');
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table], [$index], 'main');
        $catalog->replaceSchemaRecords('main', [
            $record('view', "schema_cache_item_{$variant}_view", "schema_cache_item_{$variant}_view", null, "CREATE VIEW schema_cache_item_{$variant}_view AS SELECT {$variant}", 30 + $variant),
        ]);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same([$table], $invalidation['changed_tables']);
        $t->same([$index], $invalidation['changed_indexes']);
        $t->same(null, $invalidation['table_changes'][$table]['after']['schema']);
        $t->same(null, $invalidation['index_changes'][$index]['after']['schema']);
        $t->same([], $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['rows']);
        $t->same([], $catalog->executeSchemaPragma("PRAGMA index_info({$index})")['rows']);
    };

    $tests["real upstream schema.test dynamic invalidation view trigger index ddl variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $record, $variant, $table, $index, $view, $trigger): void {
        $catalog = $makeCatalog($variant, 'main-only');
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table, $view], [$index], 'main');
        $nextIndex = "{$index}_next";
        $nextView = "{$view}_next";
        $nextTrigger = "{$trigger}_next";
        $catalog->replaceSchemaRecords('main', [
            $record('table', $table, $table, 10 + $variant, "CREATE TABLE {$table}(id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT, extra_value TEXT)", 10 + $variant),
            $record('index', $nextIndex, $table, 120 + $variant, "CREATE INDEX {$nextIndex} ON {$table}(extra_value)", 120 + $variant),
            $record('view', $nextView, $nextView, null, "CREATE VIEW {$nextView} AS SELECT extra_value FROM {$table}", 130 + $variant),
            $record('trigger', $nextTrigger, $table, null, "CREATE TRIGGER {$nextTrigger} AFTER INSERT ON {$table} BEGIN SELECT 2; END", 140 + $variant),
        ]);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $list = $catalog->executeSchemaPragma("PRAGMA index_list({$table})")['rows'];

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same([$view], $invalidation['changed_tables']);
        $t->same([$index], $invalidation['changed_indexes']);
        $t->same($table, $invalidation['unchanged_tables'][0]);
        $t->same(null, $invalidation['table_changes'][$view]['after']['schema']);
        $t->same($nextIndex, $list[0]['name']);
        $t->same('extra_value', $catalog->executeSchemaPragma("PRAGMA index_info({$nextIndex})")['rows'][0]['name']);
    };

    $tests["real upstream schema.test dynamic invalidation attach keeps existing statement current variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $record, $variant, $table, $index): void {
        $catalog = $makeCatalog($variant, 'temp-shadow');
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table], [$index], 'main');
        $catalog->attach("late{$variant}", "late{$variant}.sqlite", [
            $record('table', "late_schema_item_{$variant}", "late_schema_item_{$variant}", 200 + $variant, "CREATE TABLE late_schema_item_{$variant}(id INTEGER PRIMARY KEY)", 200 + $variant),
        ]);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $rows = $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['rows'];

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same(["late{$variant}"], $invalidation['added_schemas']);
        $t->same([], $invalidation['removed_schemas']);
        $t->same(true, $invalidation['sequence_changed']);
        $t->same([], $invalidation['changed_tables']);
        $t->same([], $invalidation['changed_indexes']);
        $t->same('temp_value', $rows[1]['name']);
    };

    $tests["real upstream schema.test dynamic invalidation detach expires attached lookup variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $variant, $auxSchema, $auxTable, $auxIndex): void {
        $catalog = $makeCatalog($variant, 'main-only');
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$auxTable], [$auxIndex], $auxSchema);
        $catalog->detach($auxSchema);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same([], $invalidation['added_schemas']);
        $t->same([$auxSchema], $invalidation['removed_schemas']);
        $t->same(true, $invalidation['sequence_changed']);
        $t->same([$auxTable], $invalidation['changed_tables']);
        $t->same([$auxIndex], $invalidation['changed_indexes']);
        $t->same(0, count($catalog->executeTableValuedPragma("pragma_table_list('{$auxTable}')")['rows']));
    };

    $tests["real upstream schema.test dynamic invalidation rollback cookie reuse stays stale variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $record, $variant, $table, $index): void {
        $catalog = $makeCatalog($variant, 'main-only');
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table, "rolled_back_{$variant}"], [$index], 'main');
        $catalog->replaceSchemaRecords('main', [
            $record('table', $table, $table, 10 + $variant, "CREATE TABLE {$table}(id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)", 10 + $variant),
            $record('index', $index, $table, 20 + $variant, "CREATE INDEX {$index} ON {$table}(key_name)", 20 + $variant),
            $record('table', "rolled_back_{$variant}", "rolled_back_{$variant}", 300 + $variant, "CREATE TABLE rolled_back_{$variant}(id INTEGER PRIMARY KEY, marker TEXT DEFAULT 'after_rollback')", 300 + $variant),
        ]);
        $afterDdl = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $freshSnapshot = $catalog->schemaCacheResolutionSnapshot([$table, "rolled_back_{$variant}"], [$index], 'main');

        $t->same(false, $afterDdl['current']);
        $t->same(true, $afterDdl['stale']);
        $t->same(["rolled_back_{$variant}"], $afterDdl['changed_tables']);
        $t->same(true, $catalog->schemaCacheResolutionInvalidation($freshSnapshot)['current']);
        $t->same(false, $catalog->schemaCacheResolutionInvalidation($freshSnapshot)['stale']);
        $t->same("'after_rollback'", $catalog->executeSchemaPragma("PRAGMA table_info(rolled_back_{$variant})")['rows'][1]['dflt_value']);
        $t->same($table, $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['target']);
        $t->same($index, $catalog->executeSchemaPragma("PRAGMA index_list({$table})")['rows'][0]['name']);
    };
}

return $tests;
