<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $mainTable = "main_schema2_settings_{$variant}";
    $tempTable = "temp_schema2_settings_{$variant}";
    $tempIndex = "temp_schema2_settings_{$variant}_key";
    $tempView = "temp_schema2_settings_{$variant}_view";
    $tempTrigger = "temp_schema2_settings_{$variant}_ai";
    $auxTable = "aux_schema2_settings_{$variant}";

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $mainTable, $mainTable, 10 + $variant, "CREATE TABLE {$mainTable}(id INTEGER PRIMARY KEY, key_name TEXT)", 10 + $variant),
        ],
        [
            $record('table', $tempTable, $tempTable, 1000 + $variant, "CREATE TABLE {$tempTable}(id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT DEFAULT 'temp_{$variant}')", 1000 + $variant),
            $record('index', $tempIndex, $tempTable, 2000 + $variant, "CREATE INDEX {$tempIndex} ON {$tempTable}(key_name)", 2000 + $variant),
            $record('view', $tempView, $tempView, null, "CREATE VIEW {$tempView} AS SELECT key_name, key_value FROM {$tempTable}", 3000 + $variant),
            $record('trigger', $tempTrigger, $tempTable, null, "CREATE TRIGGER {$tempTrigger} AFTER INSERT ON {$tempTable} BEGIN SELECT new.key_name; END", 4000 + $variant),
        ],
    );
    $catalog->attach("auxschema2_{$variant}", "auxschema2_{$variant}.sqlite", [
        $record('table', $auxTable, $auxTable, 5000 + $variant, "CREATE TABLE {$auxTable}(id INTEGER PRIMARY KEY, aux_value TEXT)", 5000 + $variant),
    ]);

    return $catalog;
};

$preparedFor = static fn (int $cookie, int $variant): array => [
    ['id' => "temp-schema-reader-{$variant}", 'schema_cookie' => $cookie, 'sql' => "SELECT * FROM temp_schema2_settings_{$variant}"],
    ['id' => "temp-master-reader-{$variant}", 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM sqlite_temp_schema'],
    ['id' => "temp-view-reader-{$variant}", 'schema_cookie' => $cookie, 'sql' => "SELECT * FROM temp_schema2_settings_{$variant}_view"],
    ['id' => "fresh-reader-{$variant}", 'schema_cookie' => $cookie + 1, 'sql' => "SELECT * FROM main_schema2_settings_{$variant}"],
];

$tests = [];

// Source truth:
// - upstream schema2.test schema2-1.* through schema2-4.*: temp-schema
//   CREATE/DROP TABLE, VIEW, TRIGGER, and INDEX expire prepared statements.
// - upstream schema2.test schema2-5.*: ATTACH keeps an existing statement
//   runnable, while DETACH expires statements that resolve through the removed
//   schema.
// - upstream schema2.test schema2-10.* and schema2-12.1: schema catalog
//   remains coherent with active readers and rollback/cookie reuse must still
//   expire stale prepared SQL.
foreach (range(1, 200) as $variant) {
    $tempTable = "temp_schema2_settings_{$variant}";
    $tempIndex = "temp_schema2_settings_{$variant}_key";
    $tempView = "temp_schema2_settings_{$variant}_view";
    $tempTrigger = "temp_schema2_settings_{$variant}_ai";
    $auxSchema = "auxschema2_{$variant}";
    $auxTable = "aux_schema2_settings_{$variant}";

    $tests["real upstream schema2.test temp create/drop table invalidates prepared statements variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $preparedFor, $variant, $tempTable): void {
        $catalog = $catalogFor($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$tempTable, "temp_schema2_runtime_{$variant}"], [], 'temp');
        $result = $catalog->applySchemaDdlCurrentSource(
            'temp',
            [
                "CREATE TABLE temp_schema2_runtime_{$variant}(id INTEGER PRIMARY KEY, label TEXT DEFAULT 'runtime_{$variant}')",
                "DROP TABLE temp_schema2_runtime_{$variant}",
            ],
            7000 + $variant,
            $snapshot,
            $preparedFor(7000 + $variant, $variant),
        );

        $t->same('schema_cache_expired', $result['status']);
        $t->same('temp', $result['schema']);
        $t->same(2, count($result['ddl_plan']['operations']));
        $t->same(['create_table', 'drop_table'], array_column($result['ddl_plan']['operations'], 'kind'));
        $t->same(7002 + $variant, $result['ddl_plan']['after_schema_cookie']);
        $t->same([], $result['invalidation']['changed_tables']);
        $t->same([$tempTable, "temp_schema2_runtime_{$variant}"], $result['invalidation']['unchanged_tables']);
        $t->same(false, $result['invalidation']['current']);
        $t->same(true, $result['invalidation']['stale']);
        $t->same(["temp-schema-reader-{$variant}", "temp-master-reader-{$variant}", "temp-view-reader-{$variant}", "fresh-reader-{$variant}"], $result['ddl_plan']['invalidated_prepared']);
        $t->same('temp', $catalog->executeSchemaPragma("PRAGMA table_info({$tempTable})")['schema']);
    };

    $tests["real upstream schema2.test temp view trigger index ddl invalidates prepared statements variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $preparedFor, $variant, $tempTable, $tempView, $tempTrigger, $tempIndex): void {
        $catalog = $catalogFor($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$tempTable, $tempView], [$tempIndex], 'temp');
        $nextIndex = "{$tempIndex}_next";
        $nextView = "{$tempView}_next";
        $nextTrigger = "{$tempTrigger}_next";
        $result = $catalog->applySchemaDdlCurrentSource(
            'temp',
            [
                "CREATE INDEX {$nextIndex} ON {$tempTable}(key_value)",
                "CREATE VIEW {$nextView} AS SELECT key_value FROM {$tempTable}",
                "CREATE TRIGGER {$nextTrigger} AFTER UPDATE ON {$tempTable} BEGIN SELECT new.key_value; END",
                "DROP TRIGGER {$nextTrigger}",
                "DROP VIEW {$nextView}",
                "DROP INDEX {$nextIndex}",
            ],
            8000 + $variant,
            $snapshot,
            $preparedFor(8000 + $variant, $variant),
        );

        $t->same('schema_cache_expired', $result['status']);
        $t->same(['create_index', 'create_view', 'create_trigger', 'drop_trigger', 'drop_view', 'drop_index'], array_column($result['ddl_plan']['operations'], 'kind'));
        $t->same(8006 + $variant, $result['ddl_plan']['after_schema_cookie']);
        $t->same(true, $result['ddl_plan']['schema_changed']);
        $t->same([$tempTable, $tempView], $result['invalidation']['unchanged_tables']);
        $t->same([$tempIndex], $result['invalidation']['unchanged_indexes']);
        $t->same(["temp-schema-reader-{$variant}", "temp-master-reader-{$variant}", "temp-view-reader-{$variant}", "fresh-reader-{$variant}"], $result['ddl_plan']['invalidated_prepared']);
        $t->same($tempIndex, $catalog->executeSchemaPragma("PRAGMA index_list({$tempTable})")['rows'][0]['name']);
    };

    $tests["real upstream schema2.test attach leaves temp resolution current variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $record, $variant, $tempTable): void {
        $catalog = $catalogFor($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$tempTable], [], 'temp');
        $catalog->attach("late_schema2_{$variant}", "late_schema2_{$variant}.sqlite", [
            $record('table', "late_schema2_settings_{$variant}", "late_schema2_settings_{$variant}", 9000 + $variant, "CREATE TABLE late_schema2_settings_{$variant}(id INTEGER PRIMARY KEY)", 9000 + $variant),
        ]);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $rows = $catalog->executeSchemaPragma("PRAGMA table_info({$tempTable})")['rows'];

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same(["late_schema2_{$variant}"], $invalidation['added_schemas']);
        $t->same([], $invalidation['removed_schemas']);
        $t->same(true, $invalidation['sequence_changed']);
        $t->same([], $invalidation['changed_tables']);
        $t->same([], $invalidation['changed_indexes']);
        $t->same('temp', $catalog->executeSchemaPragma("PRAGMA table_info({$tempTable})")['schema']);
        $t->same("'temp_{$variant}'", $rows[2]['dflt_value']);
    };

    $tests["real upstream schema2.test detach expires attached temp statement dependencies variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $variant, $auxSchema, $auxTable, $tempTable): void {
        $catalog = $catalogFor($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$auxTable, $tempTable], [], $auxSchema);
        $catalog->detach($auxSchema);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same([], $invalidation['added_schemas']);
        $t->same([$auxSchema], $invalidation['removed_schemas']);
        $t->same(true, $invalidation['sequence_changed']);
        $t->same([$auxTable], $invalidation['changed_tables']);
        $t->same([$tempTable], $invalidation['unchanged_tables']);
        $t->same([], $catalog->executeTableValuedPragma("pragma_table_list('{$auxTable}')")['rows']);
        $t->same('temp', $catalog->executeSchemaPragma("PRAGMA table_info({$tempTable})")['schema']);
    };

    $tests["real upstream schema2.test rollback cookie reuse keeps temp prepared statement stale variant {$variant}"] = static function (TestRunner $t) use ($catalogFor, $preparedFor, $variant, $tempTable): void {
        $catalog = $catalogFor($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$tempTable, "temp_schema2_after_rollback_{$variant}"], [], 'temp');
        $result = $catalog->applySchemaDdlCurrentSource(
            'temp',
            [
                "CREATE TABLE temp_schema2_rolled_back_{$variant}(id INTEGER PRIMARY KEY, marker TEXT DEFAULT 'rolled_back_{$variant}')",
                "DROP TABLE temp_schema2_rolled_back_{$variant}",
                "CREATE TABLE temp_schema2_after_rollback_{$variant}(id INTEGER PRIMARY KEY, marker TEXT DEFAULT 'after_{$variant}')",
            ],
            9000 + $variant,
            $snapshot,
            $preparedFor(9000 + $variant, $variant),
        );

        $rows = $catalog->executeSchemaPragma("PRAGMA table_info(temp_schema2_after_rollback_{$variant})")['rows'];

        $t->same('schema_cache_expired', $result['status']);
        $t->same(['create_table', 'drop_table', 'create_table'], array_column($result['ddl_plan']['operations'], 'kind'));
        $t->same(9003 + $variant, $result['ddl_plan']['after_schema_cookie']);
        $t->same(["temp_schema2_after_rollback_{$variant}"], $result['invalidation']['changed_tables']);
        $t->same(["temp-schema-reader-{$variant}", "temp-master-reader-{$variant}", "temp-view-reader-{$variant}", "fresh-reader-{$variant}"], $result['ddl_plan']['invalidated_prepared']);
        $t->same('marker', $rows[1]['name']);
        $t->same("'after_{$variant}'", $rows[1]['dflt_value']);
        $t->same([], $catalog->executeSchemaPragma("PRAGMA table_info(temp_schema2_rolled_back_{$variant})")['rows']);
    };
}

return $tests;
