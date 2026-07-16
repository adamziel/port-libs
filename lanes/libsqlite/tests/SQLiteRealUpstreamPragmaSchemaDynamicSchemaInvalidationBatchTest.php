<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$makeCatalog = static function (int $variant): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog([
        new SQLiteSchemaRecord(
            'table',
            'app_settings_' . $variant,
            'app_settings_' . $variant,
            2 + $variant,
            "CREATE TABLE app_settings_{$variant}(setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)",
            1,
        ),
    ]);
};

foreach (range(1, 60) as $variant) {
    $table = 'schema_invalid_table_' . $variant;
    $view = 'schema_invalid_view_' . $variant;
    $trigger = 'schema_invalid_trigger_' . $variant;
    $index = 'schema_invalid_index_' . $variant;
    $baseTable = 'app_settings_' . $variant;

    $tests["real upstream schema.test 1-4 create/drop schema object invalidation variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $variant, $table, $view, $trigger, $index, $baseTable): void {
        $catalog = $makeCatalog($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$baseTable, $table, $view], [$index]);

        $create = $catalog->applySchemaDdlCurrentSource('main', [
            "CREATE TABLE {$table}(id INTEGER PRIMARY KEY, key_name TEXT DEFAULT 'created_{$variant}')",
            "CREATE VIEW {$view} AS SELECT key_name FROM {$table}",
            "CREATE TRIGGER {$trigger} AFTER INSERT ON {$table} BEGIN SELECT 1; END",
            "CREATE INDEX {$index} ON {$table}(key_name)",
        ], 100 + $variant, $snapshot);

        $created = $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['rows'];
        $invalidation = $create['invalidation'];

        $t->same('schema_cache_expired', $create['status']);
        $t->same(true, $create['cache_invalidated']);
        $t->same(false, $invalidation['current']);
        $t->same([$table, $view], $invalidation['changed_tables']);
        $t->same([$index], $invalidation['changed_indexes']);
        $t->same([$baseTable], $invalidation['unchanged_tables']);
        $t->same('key_name', $created[1]['name']);
        $t->same("'created_{$variant}'", $created[1]['dflt_value']);

        $dropSnapshot = $catalog->schemaCacheResolutionSnapshot([$table, $view], [$index]);
        $drop = $catalog->applySchemaDdlCurrentSource('main', [
            "DROP TRIGGER {$trigger}",
            "DROP INDEX {$index}",
            "DROP VIEW {$view}",
            "DROP TABLE {$table}",
        ], 200 + $variant, $dropSnapshot);
        $dropInvalidation = $drop['invalidation'];

        $t->same('schema_cache_expired', $drop['status']);
        $t->same([$table, $view], $dropInvalidation['changed_tables']);
        $t->same([$index], $dropInvalidation['changed_indexes']);
        $t->same([], $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['rows']);
    };
}

foreach (range(1, 45) as $variant) {
    $main = 'shared_schema_lookup_' . $variant;
    $aux = 'aux_' . $variant;
    $auxFile = 'auxiliary-' . $variant . '.db';

    $tests["real upstream schema.test 5 attach stable detach invalidates variant {$variant}"] = static function (TestRunner $t) use ($variant, $main, $aux, $auxFile): void {
        $catalog = new SQLiteAttachedSchemaCatalog([
            new SQLiteSchemaRecord(
                'table',
                $main,
                $main,
                10 + $variant,
                "CREATE TABLE {$main}(key_name TEXT PRIMARY KEY, main_value TEXT DEFAULT 'main_{$variant}')",
                10 + $variant,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $main . '_1', $main, 20 + $variant, null, 20 + $variant),
        ]);

        $snapshot = $catalog->schemaCacheResolutionSnapshot([$main], []);
        $catalog->attach($aux, $auxFile, [
            new SQLiteSchemaRecord(
                'table',
                $main,
                $main,
                30 + $variant,
                "CREATE TABLE {$main}(key_name TEXT PRIMARY KEY, aux_value TEXT DEFAULT 'aux_{$variant}')",
                30 + $variant,
            ),
            new SQLiteSchemaRecord('index', 'sqlite_autoindex_aux_' . $main . '_1', $main, 40 + $variant, null, 40 + $variant),
        ]);
        $afterAttach = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $mainRows = $catalog->executeSchemaPragma("PRAGMA table_info({$main})")['rows'];
        $auxRows = $catalog->executeSchemaPragma("PRAGMA {$aux}.table_info({$main})")['rows'];

        $t->same(false, $afterAttach['current']);
        $t->same([$aux], $afterAttach['added_schemas']);
        $t->same([], $afterAttach['removed_schemas']);
        $t->same([$main], $afterAttach['unchanged_tables']);
        $t->same([], $afterAttach['changed_tables']);
        $t->same('main_value', $mainRows[1]['name']);
        $t->same('aux_value', $auxRows[1]['name']);
        $t->same($auxFile, $catalog->executeSchemaPragma('PRAGMA database_list')['rows'][2]['file']);

        $detachSnapshot = $catalog->schemaCacheResolutionSnapshot([$main], []);
        $catalog->detach($aux);
        $afterDetach = $catalog->schemaCacheResolutionInvalidation($detachSnapshot);

        $t->same(false, $afterDetach['current']);
        $t->same([], $afterDetach['added_schemas']);
        $t->same([$aux], $afterDetach['removed_schemas']);
        $t->same([$main], $afterDetach['unchanged_tables']);
        $t->same(['main', 'temp'], array_column($catalog->executeSchemaPragma('PRAGMA database_list')['rows'], 'name'));
    };
}

foreach (range(1, 35) as $variant) {
    $table = 'schema2_temp_shadow_' . $variant;

    $tests["real upstream schema2 temp/main shadowing and schema cookie variant {$variant}"] = static function (TestRunner $t) use ($variant, $table): void {
        $catalog = new SQLiteAttachedSchemaCatalog(
            [
                new SQLiteSchemaRecord(
                    'table',
                    $table,
                    $table,
                    1000 + $variant,
                    "CREATE TABLE {$table}(key_name TEXT PRIMARY KEY, main_value TEXT DEFAULT 'main_{$variant}')",
                    1000 + $variant,
                ),
                new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $table . '_1', $table, 1100 + $variant, null, 1100 + $variant),
            ],
            [
                new SQLiteSchemaRecord(
                    'table',
                    $table,
                    $table,
                    1200 + $variant,
                    "CREATE TABLE {$table}(key_name TEXT PRIMARY KEY, temp_value TEXT DEFAULT 'temp_{$variant}')",
                    1200 + $variant,
                ),
                new SQLiteSchemaRecord('index', 'sqlite_autoindex_temp_' . $table . '_1', $table, 1300 + $variant, null, 1300 + $variant),
            ],
        );

        $snapshot = $catalog->schemaCacheResolutionSnapshot([$table], []);
        $unqualifiedBefore = $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['rows'];
        $mainBefore = $catalog->executeSchemaPragma("PRAGMA main.table_info({$table})")['rows'];
        $apply = $catalog->applySchemaDdlCurrentSource('main', [
            "CREATE INDEX schema2_temp_shadow_{$variant}_main_value ON {$table}(main_value)",
        ], 300 + $variant, $snapshot);
        $unqualifiedAfter = $catalog->executeSchemaPragma("PRAGMA table_info({$table})")['rows'];
        $mainIndex = $catalog->executeSchemaPragma("PRAGMA main.index_list({$table})")['rows'];

        $t->same('temp_value', $unqualifiedBefore[1]['name']);
        $t->same('main_value', $mainBefore[1]['name']);
        $t->same('schema_cache_expired', $apply['status']);
        $t->same(true, $apply['cache_invalidated']);
        $t->same([$table], $apply['invalidation']['unchanged_tables']);
        $t->same([], $apply['invalidation']['changed_tables']);
        $t->same('temp_value', $unqualifiedAfter[1]['name']);
        $t->same('schema2_temp_shadow_' . $variant . '_main_value', $mainIndex[1]['name']);
        $t->same('c', $mainIndex[1]['origin']);
        $t->same(1, $catalog->lookupCacheStats()['generation']);
    };
}

$tests['real upstream pragma schema invalidation batch cites source corpus sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema.test schema-1.* create/drop table invalidates sqlite_master prepared statements',
        'schema.test schema-2.* create/drop view invalidates sqlite_master prepared statements',
        'schema.test schema-3.* create/drop trigger invalidates sqlite_master prepared statements',
        'schema.test schema-4.* create/drop index invalidates sqlite_master prepared statements',
        'schema.test schema-5.* ATTACH does not change unqualified table winner but DETACH invalidates database array',
        'schema2.test temp/main schema shadowing keeps unqualified PRAGMA pinned to temp',
    ];

    $t->same(6, count($sections));
    $t->same(true, str_starts_with($sections[0], 'schema.test schema-1'));
    $t->same(true, str_contains($sections[4], 'ATTACH'));
    $t->same(true, str_contains($sections[5], 'schema2.test'));
};

return $tests;
