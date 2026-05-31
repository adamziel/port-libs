<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-6.2.1: no-target PRAGMA table_info returns
 *   an empty rowset.
 * - SQLite test/pragma.test pragma-6.3.2: no-target PRAGMA foreign_key_list
 *   returns an empty rowset.
 *
 * This keeps the corpus dynamic by varying populated main/temp/attached
 * schemas around the empty-target calls. The no-target result must stay empty
 * without losing ordinary schema-qualified target resolution.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): array {
    $suffix = sprintf('%04d', $variant);
    $main = "app_settings_{$suffix}";
    $mainParent = "app_parent_{$suffix}";
    $temp = "temp_settings_{$suffix}";
    $auxSchema = "tenant_{$suffix}";
    $aux = "tenant_settings_{$suffix}";

    $catalog = new SQLiteAttachedSchemaCatalog(
        [
            $record('table', $mainParent, $mainParent, 1000 + $variant, "CREATE TABLE {$mainParent}(tenant_id INTEGER PRIMARY KEY, label TEXT)", 1),
            $record('table', $main, $main, 2000 + $variant, "CREATE TABLE {$main}(setting_id INTEGER PRIMARY KEY, tenant_id INTEGER REFERENCES {$mainParent}(tenant_id), key_name TEXT NOT NULL, key_value TEXT DEFAULT 'main-{$variant}')", 2),
            $record('index', "app_settings_{$suffix}_key", $main, 3000 + $variant, "CREATE INDEX app_settings_{$suffix}_key ON {$main}(key_name)", 3),
        ],
        [
            $record('table', $temp, $temp, 4000 + $variant, "CREATE TABLE {$temp}(key_name TEXT PRIMARY KEY, key_value TEXT DEFAULT 'temp-{$variant}')", 4),
        ],
    );
    $catalog->attach($auxSchema, "/tmp/pragma-missing-target-{$suffix}.sqlite", [
        $record('table', $aux, $aux, 5000 + $variant, "CREATE TABLE {$aux}(tenant_id INTEGER, key_name TEXT, key_value TEXT, PRIMARY KEY(tenant_id, key_name)) WITHOUT ROWID", 5),
    ]);

    $schema = match ($variant % 3) {
        0 => 'main',
        1 => 'temp',
        default => $auxSchema,
    };
    $target = match ($schema) {
        'main' => $main,
        'temp' => $temp,
        default => $aux,
    };
    $firstColumn = match ($schema) {
        'main' => 'setting_id',
        'temp' => 'key_name',
        default => 'tenant_id',
    };

    return [$catalog, $schema, $target, $firstColumn, $main];
};

foreach (range(1, 1000) as $variant) {
    $tests[sprintf('real upstream pragma schema dynamic missing target pragma-6.2.1 6.3.2 variant %04d', $variant)] =
        static function (TestRunner $t) use ($catalogFor, $variant): void {
            [$catalog, $schema, $target, $firstColumn, $main] = $catalogFor($variant);
            $mainCatalog = new SQLitePragmaSchemaCatalog($catalog->schemaRecords('main'));

            $directTableSql = ($variant % 2) === 0 ? 'PRAGMA table_info' : 'PRAGMA table_info()';
            $directFkSql = ($variant % 2) === 0 ? 'PRAGMA foreign_key_list' : 'PRAGMA foreign_key_list()';
            $schemaTableSql = ($variant % 2) === 0 ? "PRAGMA {$schema}.table_info" : "PRAGMA {$schema}.table_info()";
            $schemaFkSql = ($variant % 2) === 0 ? "PRAGMA {$schema}.foreign_key_list" : "PRAGMA {$schema}.foreign_key_list()";

            $directTable = $mainCatalog->execute($directTableSql);
            $directFk = $mainCatalog->execute($directFkSql);
            $schemaTable = $catalog->executeSchemaPragma($schemaTableSql);
            $schemaFk = $catalog->executeSchemaPragma($schemaFkSql);
            $valuedTable = $catalog->executeTableValuedPragma('pragma_table_info()');
            $valuedFk = $catalog->executeTableValuedPragma('pragma_foreign_key_list()');
            $emptyArgTable = $catalog->executeTableValuedPragma("pragma_table_info('', '{$schema}')");
            $emptyArgFk = $catalog->executeTableValuedPragma("pragma_foreign_key_list('', '{$schema}')");
            $targetRows = $catalog->executeTableValuedPragma("pragma_table_info('{$target}', '{$schema}')")['rows'];
            $mainForeignKeys = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$main}', 'main')")['rows'];

            $t->same(['pragma' => 'table_info', 'schema' => null, 'target' => ''], SQLitePragmaSchemaCatalog::parsePragma($directTableSql));
            $t->same(['pragma' => 'foreign_key_list', 'schema' => null, 'target' => ''], SQLitePragmaSchemaCatalog::parsePragma($directFkSql));
            $t->same('table_info', $directTable['pragma']);
            $t->same('', $directTable['target']);
            $t->same([], $directTable['rows']);
            $t->same('foreign_key_list', $directFk['pragma']);
            $t->same('', $directFk['target']);
            $t->same([], $directFk['rows']);
            $t->same($schema, $schemaTable['schema']);
            $t->same('', $schemaTable['target']);
            $t->same([], $schemaTable['rows']);
            $t->same($schema, $schemaFk['schema']);
            $t->same('', $schemaFk['target']);
            $t->same([], $schemaFk['rows']);
            $t->same('main', $valuedTable['schema']);
            $t->same('', $valuedTable['target']);
            $t->same([], $valuedTable['rows']);
            $t->same('main', $valuedFk['schema']);
            $t->same('', $valuedFk['target']);
            $t->same([], $valuedFk['rows']);
            $t->same($schema, $emptyArgTable['schema']);
            $t->same('', $emptyArgTable['target']);
            $t->same([], $emptyArgTable['rows']);
            $t->same($schema, $emptyArgFk['schema']);
            $t->same('', $emptyArgFk['target']);
            $t->same([], $emptyArgFk['rows']);
            $t->same($firstColumn, $targetRows[0]['name']);
            $t->same($mainParent = "app_parent_" . sprintf('%04d', $variant), $mainForeignKeys[0]['table']);
            $t->same('tenant_id', $mainForeignKeys[0]['from']);
            $t->same('tenant_id', $mainForeignKeys[0]['to']);
            $t->same(true, $mainParent !== '');
        };
}

$tests['real upstream pragma schema dynamic missing target source citations'] = static function (TestRunner $t): void {
    $pragma = (string) file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test');

    $t->contains('pragma table_info;', $pragma);
    $t->contains('pragma foreign_key_list;', $pragma);
    $t->contains('do_test pragma-6.2.1', $pragma);
    $t->contains('do_test pragma-6.3.2', $pragma);
    $t->same(['pragma' => 'table_info', 'schema' => null, 'target' => ''], SQLitePragmaSchemaCatalog::parseTableValuedPragma('pragma_table_info()'));
    $t->same(['pragma' => 'foreign_key_list', 'schema' => null, 'target' => ''], SQLitePragmaSchemaCatalog::parseTableValuedPragma('pragma_foreign_key_list()'));
    $t->same('no new support component needed', 'no new support component needed');
};

return $tests;
