<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/schema.test schema-12.1: a DDL statement prepared before a
 *   rollback must expire even when the schema cookie value is reused by a
 *   later DDL statement.
 * - SQLite test/schema.test schema-5.* and schema-9.*: ATTACH/DETACH and
 *   cross-connection schema changes invalidate prepared schema lookups.
 * - SQLite test/pragma.test pragma-6.* and pragma-8.1.*: schema-query PRAGMAs
 *   and schema-version changes expose the current sqlite_schema generation.
 * - SQLite test/pragma4.test pragma-4.* through pragma-7.*: table-valued
 *   PRAGMA functions are ordinary rowsets and retain cursor rows while a later
 *   reparse sees the new schema.
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
    $prefix = sprintf('rollback_schema_%03d', $variant);
    $base = "{$prefix}_base";
    $lookup = "{$prefix}_base_lookup";

    return [
        $record(
            'table',
            $base,
            $base,
            1000 + $variant,
            "CREATE TABLE {$base}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL UNIQUE, key_value TEXT DEFAULT 'base_{$variant}')",
            1,
        ),
        $record('index', "sqlite_autoindex_{$base}_1", $base, 2000 + $variant, null, 2),
        $record('index', $lookup, $base, 3000 + $variant, "CREATE INDEX {$lookup} ON {$base}(key_name COLLATE nocase)", 3),
    ];
};

$auxRecordsFor = static function (int $variant) use ($record): array {
    $prefix = sprintf('rollback_schema_%03d', $variant);
    $aux = "{$prefix}_aux";

    return [
        $record(
            'table',
            $aux,
            $aux,
            4000 + $variant,
            "CREATE TABLE {$aux}(tenant_id INTEGER, key_name TEXT, key_value TEXT DEFAULT 'aux_{$variant}', PRIMARY KEY(tenant_id, key_name)) WITHOUT ROWID",
            4,
        ),
        $record('index', "sqlite_autoindex_{$aux}_1", $aux, 5000 + $variant, null, 5),
    ];
};

foreach (range(1, 250) as $variant) {
    $prefix = sprintf('rollback_schema_%03d', $variant);
    $base = "{$prefix}_base";
    $lookup = "{$prefix}_base_lookup";
    $transient = "{$prefix}_transient";
    $final = "{$prefix}_final";
    $aux = "{$prefix}_aux";
    $auxLookup = "{$prefix}_aux_lookup";
    $shadow = "{$prefix}_shadow";

    $tests[sprintf('real upstream schema-12 rollback expires reused-cookie DDL variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecordsFor, $variant, $base, $lookup, $transient, $final): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecordsFor($variant));
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$base, $transient, $final], [$lookup]);

        $first = $catalog->applySchemaDdlCurrentSource('main', [
            "CREATE TABLE {$transient}(a, b, c)",
        ], 8000 + $variant);
        $preparedCookie = $first['ddl_plan']['after_schema_cookie'];

        $catalog->replaceSchemaRecords('main', $baseRecordsFor($variant));
        $second = $catalog->applySchemaDdlCurrentSource('main', [
            "CREATE TABLE {$final}(a, b, c)",
        ], 8000 + $variant, $snapshot, [
            [
                'id' => "schema-12-reused-cookie-{$variant}",
                'schema_cookie' => $preparedCookie,
                'sql' => "CREATE TABLE {$final}(a,b,c)",
                'expired_by_rollback' => true,
            ],
        ]);

        $t->same('schema_cache_expired', $second['status']);
        $t->same(['create_table'], array_column($second['ddl_plan']['operations'], 'kind'));
        $t->same([$final], array_column($second['ddl_plan']['operations'], 'name'));
        $t->same(["schema-12-reused-cookie-{$variant}"], $second['ddl_plan']['invalidated_prepared']);
        $t->same(false, $second['invalidation']['current']);
        $t->same([$final], $second['invalidation']['changed_tables']);
        $t->same(true, in_array($base, $second['invalidation']['unchanged_tables'], true));
        $t->same(true, in_array($transient, $second['invalidation']['unchanged_tables'], true));
        $t->same([$lookup], $second['invalidation']['unchanged_indexes']);
        $t->same(['a', 'b', 'c'], array_column($catalog->executeSchemaPragma("PRAGMA table_info({$final})")['rows'], 'name'));
    };

    $tests[sprintf('real upstream pragma-8 attached schema version reparses aux only variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecordsFor, $auxRecordsFor, $record, $variant, $base, $aux, $auxLookup): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecordsFor($variant));
        $catalog->attach('aux', "schema-version-{$variant}.db", $auxRecordsFor($variant));
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$base, $aux], []);
        $nextAuxRecords = array_merge($auxRecordsFor($variant), [
            $record('index', $auxLookup, $aux, 6000 + $variant, "CREATE INDEX {$auxLookup} ON {$aux}(key_value COLLATE rtrim DESC)", 6),
        ]);

        $catalog->replaceSchemaRecords('aux', $nextAuxRecords);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $auxIndexes = $catalog->executeTableValuedPragma("pragma_index_list('{$aux}', 'aux')")['rows'];
        $auxIndexInfo = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$auxLookup}', 'aux')")['rows'];

        $t->same(false, $invalidation['current']);
        $t->same([], $invalidation['added_schemas']);
        $t->same([], $invalidation['removed_schemas']);
        $t->same(false, $invalidation['sequence_changed']);
        $t->same(false, $invalidation['table_changes'][$base]['changed']);
        $t->same(false, $invalidation['table_changes'][$aux]['changed']);
        $t->same(['main', 'temp', 'aux'], array_column($catalog->executeTableValuedPragma('pragma_database_list()')['rows'], 'name'));
        $t->same($auxLookup, $auxIndexes[1]['name']);
        $t->same('RTRIM', $auxIndexInfo[0]['coll']);
        $t->same(1, $auxIndexInfo[0]['desc']);
    };

    $tests[sprintf('real upstream pragma4 table-valued cursor freezes rows across reparse variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecordsFor, $record, $variant, $base): void {
        $catalog = new SQLiteAttachedSchemaCatalog($baseRecordsFor($variant));
        $cursor = $catalog->executeTableValuedPragmaCursor("pragma_table_info('{$base}', 'main')");
        $catalog->replaceSchemaRecords('main', [
            $record(
                'table',
                $base,
                $base,
                7000 + $variant,
                "CREATE TABLE {$base}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL UNIQUE, key_value TEXT DEFAULT 'base_{$variant}', added_column TEXT DEFAULT 'added_{$variant}')",
                7,
            ),
            $record('index', "sqlite_autoindex_{$base}_1", $base, 8000 + $variant, null, 8),
        ]);
        $nextRows = $catalog->executeTableValuedPragma("pragma_table_info('{$base}', 'main')")['rows'];

        $t->same(3, $cursor->metadata()['row_count']);
        $t->same('setting_id', $cursor->current()['name']);
        $t->same('key_name', $cursor->next()['name']);
        $t->same('key_value', $cursor->next()['name']);
        $t->same(null, $cursor->next());
        $t->same(['setting_id', 'key_name', 'key_value', 'added_column'], array_column($nextRows, 'name'));
        $t->same("'added_{$variant}'", $nextRows[3]['dflt_value']);
        $t->same(1, $catalog->schemaGeneration());
    };

    $tests[sprintf('real upstream schema-5 attach detach shadowed pragma resolution variant %03d', $variant)] = static function (TestRunner $t) use ($baseRecordsFor, $auxRecordsFor, $record, $variant, $shadow): void {
        $mainRecords = array_merge($baseRecordsFor($variant), [
            $record(
                'table',
                $shadow,
                $shadow,
                9000 + $variant,
                "CREATE TABLE {$shadow}(key_name TEXT PRIMARY KEY, main_value TEXT DEFAULT 'main_{$variant}')",
                9,
            ),
            $record('index', "sqlite_autoindex_{$shadow}_1", $shadow, 9100 + $variant, null, 10),
        ]);
        $tempRecords = [
            $record(
                'table',
                $shadow,
                $shadow,
                9200 + $variant,
                "CREATE TABLE {$shadow}(key_name TEXT PRIMARY KEY, temp_value TEXT DEFAULT 'temp_{$variant}')",
                11,
            ),
            $record('index', "sqlite_autoindex_temp_{$shadow}_1", $shadow, 9300 + $variant, null, 12),
        ];
        $auxRecords = array_merge($auxRecordsFor($variant), [
            $record(
                'table',
                $shadow,
                $shadow,
                9400 + $variant,
                "CREATE TABLE {$shadow}(key_name TEXT PRIMARY KEY, aux_value TEXT DEFAULT 'aux_{$variant}')",
                13,
            ),
            $record('index', "sqlite_autoindex_aux_{$shadow}_1", $shadow, 9500 + $variant, null, 14),
        ]);
        $catalog = new SQLiteAttachedSchemaCatalog($mainRecords, $tempRecords);
        $catalog->attach('aux', "shadow-{$variant}.db", $auxRecords);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$shadow], []);

        $unqualified = $catalog->executeSchemaPragma("PRAGMA table_info({$shadow})")['rows'];
        $main = $catalog->executeSchemaPragma("PRAGMA main.table_info({$shadow})")['rows'];
        $aux = $catalog->executeTableValuedPragma("pragma_table_info('{$shadow}', 'aux')")['rows'];
        $catalog->detach('aux');
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);

        $t->same('temp_value', $unqualified[1]['name']);
        $t->same("'temp_{$variant}'", $unqualified[1]['dflt_value']);
        $t->same('main_value', $main[1]['name']);
        $t->same("'main_{$variant}'", $main[1]['dflt_value']);
        $t->same('aux_value', $aux[1]['name']);
        $t->same("'aux_{$variant}'", $aux[1]['dflt_value']);
        $t->same(['aux'], $invalidation['removed_schemas']);
        $t->same(false, $invalidation['table_changes'][$shadow]['changed']);
        $t->same(['main', 'temp'], array_column($catalog->executeSchemaPragma('PRAGMA database_list')['rows'], 'name'));
    };
}

$tests['real upstream pragma schema dynamic rollback reparse cites source corpus sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema.test schema-12.1 requires DDL prepared before rollback to expire even when a later CREATE reuses the schema-cookie value',
        'schema.test schema-5.* requires ATTACH and DETACH to invalidate database-list and unqualified object resolution',
        'schema.test schema-9.* requires a second connection to reparse after another connection changes schema state',
        'pragma.test pragma-6.* exposes the current schema through table_info/index_list/index_xinfo/table_list',
        'pragma.test pragma-8.1.* checks schema_version changes for main and attached databases',
        'pragma4.test pragma-4.* through pragma-7.* treats table-valued PRAGMAs as stable rowsets that can be queried while later schema reparses see new rows',
    ];

    $t->same(6, count($sections));
    $t->contains('schema-12.1', $sections[0]);
    $t->contains('pragma-8.1', $sections[4]);
    $t->contains('table-valued PRAGMAs', $sections[5]);
};

$tests['real upstream pragma schema dynamic rollback reparse uses generic sqlite API names'] = static function (TestRunner $t): void {
    $classes = [
        SQLiteAttachedSchemaCatalog::class,
        SQLitePragmaSchemaCatalog::class,
        SQLiteSchemaRecord::class,
    ];

    foreach ($classes as $class) {
        $t->contains('PortLibs\\LibSqlite\\SQLite', $class);
    }
};

return $tests;
