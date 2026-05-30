<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $root,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowId);

$makeCatalog = static function (int $variant = 1) use ($record): SQLiteAttachedSchemaCatalog {
    $mainName = 'app_record_' . $variant;
    $viewName = 'app_record_view_' . $variant;
    $indexName = 'app_record_key_' . $variant;
    $tempName = 'session_record_' . $variant;
    $auxName = 'archive_record_' . $variant;

    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', $mainName, $mainName, 2, "CREATE TABLE {$mainName}(id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT DEFAULT 'main_{$variant}')", 1),
        $record('index', $indexName, $mainName, 3, "CREATE INDEX {$indexName} ON {$mainName}(key_name)", 2),
        $record('view', $viewName, $viewName, null, "CREATE VIEW {$viewName} AS SELECT id, key_name FROM {$mainName}", 3),
    ], [
        $record('table', $tempName, $tempName, 4, "CREATE TABLE {$tempName}(id INTEGER PRIMARY KEY, key_name TEXT DEFAULT 'temp_{$variant}')", 4),
    ]);
    $catalog->attach('aux', "archive-{$variant}.sqlite", [
        $record('table', $auxName, $auxName, 5, "CREATE TABLE {$auxName}(id INTEGER PRIMARY KEY, key_name TEXT DEFAULT 'aux_{$variant}')", 5),
    ]);

    return $catalog;
};

$tableRows = static fn (SQLiteAttachedSchemaCatalog $catalog, string $sql): array => $catalog->executeSchemaPragma($sql)['rows'];

// Source truth: upstream schema.test schema-9.1 and schema-9.2.
foreach (range(1, 90) as $variant) {
    $mainName = 'app_record_' . $variant;
    $viewName = 'app_record_view_' . $variant;
    $indexName = 'app_record_key_' . $variant;
    $tempName = 'session_record_' . $variant;
    $auxName = 'archive_record_' . $variant;

    $tests["real upstream schema.test 9 dynamic drop invalidates table lookup variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $tableRows, $variant, $mainName, $viewName, $indexName): void {
        $catalog = $makeCatalog($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$mainName, $viewName], [$indexName], 'main');
        $catalog->replaceSchemaRecords('main', []);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same([$mainName, $viewName], $invalidation['changed_tables']);
        $t->same([$indexName], $invalidation['changed_indexes']);
        $t->same('main', $invalidation['table_changes'][$mainName]['before']['schema']);
        $t->same(null, $invalidation['table_changes'][$mainName]['after']['schema']);
        $t->same([], $tableRows($catalog, "PRAGMA table_info({$mainName})"));
        $t->same([], $tableRows($catalog, "PRAGMA index_info({$indexName})"));
    };

    $tests["real upstream schema.test 9 dynamic drop view lookup variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $variant, $viewName): void {
        $catalog = $makeCatalog($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$viewName], [], 'main');
        $records = array_values(array_filter(
            $catalog->schemaRecords('main'),
            static fn (SQLiteSchemaRecord $record): bool => $record->name !== $viewName,
        ));
        $catalog->replaceSchemaRecords('main', $records);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $rows = $catalog->executeSchemaPragma("PRAGMA table_list({$viewName})")['rows'];

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same([$viewName], $invalidation['changed_tables']);
        $t->same('view', $invalidation['table_changes'][$viewName]['before']['type']);
        $t->same(null, $invalidation['table_changes'][$viewName]['after']['type']);
        $t->same([], $rows);
        $t->same(1, $invalidation['before_generation']);
        $t->same(2, $invalidation['after_generation']);
    };

    $tests["real upstream schema.test 12 rollback expires matching-cookie statement variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $record, $variant, $mainName): void {
        $catalog = $makeCatalog($variant);
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$mainName], [], 'main');
        $rolledBackName = 'rolled_back_record_' . $variant;
        $nextName = 'committed_record_' . $variant;

        $catalog->replaceSchemaRecords('main', [
            $record('table', $mainName, $mainName, 2, "CREATE TABLE {$mainName}(id INTEGER PRIMARY KEY, key_name TEXT)", 1),
            $record('table', $nextName, $nextName, 20 + $variant, "CREATE TABLE {$nextName}(id INTEGER PRIMARY KEY, key_name TEXT DEFAULT 'next_{$variant}')", 10 + $variant),
        ]);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);

        $t->same(false, $invalidation['current']);
        $t->same(true, $invalidation['stale']);
        $t->same([], $catalog->executeSchemaPragma("PRAGMA table_info({$rolledBackName})")['rows']);
        $t->same($nextName, $catalog->executeSchemaPragma("PRAGMA table_list({$nextName})")['rows'][0]['name']);
        $t->same('key_name', $catalog->executeSchemaPragma("PRAGMA table_info({$nextName})")['rows'][1]['name']);
        $t->same("'next_{$variant}'", $catalog->executeSchemaPragma("PRAGMA table_info({$nextName})")['rows'][1]['dflt_value']);
        $t->same(1, $invalidation['before_generation']);
        $t->same(2, $invalidation['after_generation']);
    };

    $tests["real upstream pragma.test 6.1 database list and schema shadow variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $variant, $mainName, $tempName, $auxName): void {
        $catalog = $makeCatalog($variant);
        $databaseList = $catalog->executeSchemaPragma('PRAGMA database_list')['rows'];
        $mainRows = $catalog->executeSchemaPragma("PRAGMA main.table_info({$mainName})")['rows'];
        $tempRows = $catalog->executeSchemaPragma("PRAGMA temp.table_info({$tempName})")['rows'];
        $auxRows = $catalog->executeTableValuedPragma("pragma_table_info('{$auxName}', 'aux')")['rows'];

        $t->same([0, 1, 2], array_column($databaseList, 'seq'));
        $t->same(['main', 'temp', 'aux'], array_column($databaseList, 'name'));
        $t->same([null, '', "archive-{$variant}.sqlite"], array_column($databaseList, 'file'));
        $t->same("'main_{$variant}'", $mainRows[2]['dflt_value']);
        $t->same("'temp_{$variant}'", $tempRows[1]['dflt_value']);
        $t->same("'aux_{$variant}'", $auxRows[1]['dflt_value']);
        $t->same('main', $catalog->executeSchemaPragma("PRAGMA main.table_list({$mainName})")['schema']);
        $t->same('aux', $catalog->executeTableValuedPragma("pragma_table_list('{$auxName}', 'aux')")['schema']);
    };
}

// Source truth: upstream pragma.test pragma-8.1 and pragma3.test data_version cases.
foreach (range(1, 85) as $variant) {
    $tests["real upstream pragma.test 8 schema and user version isolated schema variant {$variant}"] = static function (TestRunner $t) use ($variant): void {
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => 100 + $variant, 'data_version' => 1, 'change_counter' => 1, 'user_version' => 0],
            'aux' => ['schema_version' => 200 + $variant, 'data_version' => 1, 'change_counter' => 1, 'user_version' => 0],
        ]);
        $state->execute('PRAGMA main.schema_version = ' . (300 + $variant));
        $state->execute('PRAGMA aux.user_version = ' . (400 + $variant));

        $t->same(300 + $variant, $state->execute('PRAGMA main.schema_version')['value']);
        $t->same(200 + $variant, $state->execute('PRAGMA aux.schema_version')['value']);
        $t->same(0, $state->execute('PRAGMA main.user_version')['value']);
        $t->same(400 + $variant, $state->execute('PRAGMA aux.user_version')['value']);
        $t->same([['schema_version' => 300 + $variant]], $state->execute('PRAGMA main.schema_version')['rows']);
        $t->same([['user_version' => 400 + $variant]], $state->execute('PRAGMA aux.user_version')['rows']);
    };

    $tests["real upstream pragma3 data_version external commit observer variant {$variant}"] = static function (TestRunner $t) use ($variant): void {
        $reader = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => 1, 'data_version' => $variant, 'change_counter' => $variant, 'user_version' => 0],
        ]);
        $writer = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => 1, 'data_version' => $variant, 'change_counter' => $variant, 'user_version' => 0],
        ]);
        $writer->recordLocalCommit('main', 1, 'local_writer_commit');
        $reader->recordExternalCommit('main', 1, 'observed_writer_commit');

        $t->same($variant, $writer->execute('PRAGMA data_version')['value']);
        $t->same($variant + 1, $reader->execute('PRAGMA data_version')['value']);
        $t->same($variant + 1, $writer->headerUpdate('main')['file_change_counter']);
        $t->same($variant + 1, $reader->headerUpdate('main')['file_change_counter']);
        $t->same(false, $reader->execute('PRAGMA data_version = ' . ($variant + 50))['changed']);
        $t->same($variant + 1, $reader->execute('PRAGMA data_version')['value']);
    };
}

return $tests;
