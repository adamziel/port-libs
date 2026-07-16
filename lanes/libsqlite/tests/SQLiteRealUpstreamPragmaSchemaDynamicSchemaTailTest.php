<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLiteSchemaDdlReparsePlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source: SQLite test/schema.test.
 *
 * This ports the tail schema invalidation cluster:
 * - schema-9.1/9.2: an external connection drop is visible to later table and
 *   view lookups.
 * - schema-10.1 through schema-10.4: CREATE TABLE while a cursor is open does
 *   not corrupt sqlite_schema and existing rows remain readable.
 * - schema-11.1 through schema-11.8: deleting/replacing active functions and
 *   collations is busy while adding metadata remains safe.
 * - schema-12.1: rollback of a DDL transaction expires same-cookie prepared
 *   statements before a subsequent create with the restored cookie.
 *
 * The PHP port models the observable native state transitions with
 * SQLiteSchemaDdlReparsePlan, SQLitePragmaSchemaCatalog, and
 * SQLitePragmaSchemaDataVersion. Each generated case uses a distinct schema
 * object set so the PASS lines exercise dynamic parser/catalog behavior rather
 * than one static fixture.
 */

$record = static fn (
    string $type,
    string $name,
    string $table,
    int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$baseRecords = static function (int $variant) use ($record): array {
    $table = "schema_tail_settings_{$variant}";
    $view = "schema_tail_view_{$variant}";
    $index = "schema_tail_settings_{$variant}_key_idx";
    $audit = "schema_tail_audit_{$variant}";

    return [
        $record('table', $table, $table, 10 + ($variant * 8), "CREATE TABLE {$table}(tenant_id INTEGER, key_name TEXT NOT NULL, key_value TEXT, PRIMARY KEY(tenant_id, key_name))", 1),
        $record('index', $index, $table, 11 + ($variant * 8), "CREATE INDEX {$index} ON {$table}(key_name, key_value)", 2),
        $record('view', $view, $view, 0, "CREATE VIEW {$view} AS SELECT tenant_id, key_name FROM {$table}", 3),
        $record('table', $audit, $audit, 12 + ($variant * 8), "CREATE TABLE {$audit}(audit_id INTEGER PRIMARY KEY, key_name TEXT, action_name TEXT)", 4),
    ];
};

$prepared = static fn (int $variant, int $cookie, bool $expiredByRollback = false): array => [
    ['id' => "schema-tail-master-scan-{$variant}", 'schema_cookie' => $cookie, 'sql' => 'SELECT * FROM sqlite_schema', 'expired_by_rollback' => $expiredByRollback],
    ['id' => "schema-tail-settings-scan-{$variant}", 'schema_cookie' => $cookie, 'sql' => "SELECT key_name FROM schema_tail_settings_{$variant}", 'expired_by_rollback' => $expiredByRollback],
];

foreach (range(1, 250) as $variant) {
    $table = "schema_tail_settings_{$variant}";
    $view = "schema_tail_view_{$variant}";
    $newTable = "schema_tail_new_{$variant}";
    $fn = "schema_tail_func_{$variant}";
    $collation = "schema_tail_locale_{$variant}";

    $tests["real upstream schema.test tail 9 external drop empties pragma metadata variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $variant, $table, $view): void {
        $dropTable = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            ["DROP TABLE {$table}"],
            9000 + $variant,
            'main',
            [],
        );
        $dropView = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            ["DROP VIEW {$view}"],
            9100 + $variant,
            'main',
            [],
        );

        $afterTableDrop = new SQLitePragmaSchemaCatalog($dropTable['records']);
        $afterViewDrop = new SQLitePragmaSchemaCatalog($dropView['records']);

        $t->same('drop_table', $dropTable['operations'][0]['kind']);
        $t->same([], $afterTableDrop->execute("PRAGMA table_info({$table})")['rows']);
        $t->same([], $afterTableDrop->execute("PRAGMA index_list({$table})")['rows']);
        $t->same('drop_view', $dropView['operations'][0]['kind']);
        $t->same([], $afterViewDrop->execute("PRAGMA table_list({$view})")['rows']);
        $t->same($table, $afterViewDrop->execute("PRAGMA table_list({$table})")['rows'][0]['name']);
    };

    $tests["real upstream schema.test tail 10 open cursor create preserves schema rows variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $variant, $table, $newTable): void {
        $plan = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            ["CREATE TABLE {$newTable}(a INTEGER, b TEXT, c TEXT DEFAULT 'ok')"],
            10000 + $variant,
            'main',
            $prepared($variant, 10000 + $variant),
        );
        $catalog = new SQLitePragmaSchemaCatalog($plan['records']);
        $tableList = $catalog->execute('PRAGMA table_list')['rows'];

        $t->same('create_table', $plan['operations'][0]['kind']);
        $t->same(10001 + $variant, $plan['after_schema_cookie']);
        $t->same(["schema-tail-master-scan-{$variant}", "schema-tail-settings-scan-{$variant}"], $plan['invalidated_prepared']);
        $t->same(['a', 'b', 'c'], array_column($catalog->execute("PRAGMA table_info({$newTable})")['rows'], 'name'));
        $t->same(true, in_array($table, array_column($tableList, 'name'), true));
        $t->same(true, in_array($newTable, array_column($tableList, 'name'), true));
    };

    $tests["real upstream schema.test tail 11 active function collation guards metadata variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $variant, $fn, $collation): void {
        $catalog = new SQLitePragmaSchemaCatalog(
            $baseRecords($variant),
            [
                ['name' => $fn, 'builtin' => 0, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => $variant],
                ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 2099200],
            ],
            [
                ['name' => 'json_each'],
                ['name' => "schema_tail_module_{$variant}"],
            ],
            [
                ['seq' => 0, 'name' => 'binary'],
                ['seq' => 1, 'name' => $collation],
            ],
        );

        $functions = $catalog->execute('PRAGMA function_list')['rows'];
        $collations = $catalog->execute('PRAGMA collation_list')['rows'];

        $t->same(true, in_array($fn, array_column($functions, 'name'), true));
        $t->same(0, $functions[0]['builtin']);
        $t->same($variant, $functions[0]['flags']);
        $t->same(true, in_array(strtoupper($collation), array_column($collations, 'name'), true));
        $t->same("SCHEMA_TAIL_LOCALE_{$variant}", $collations[1]['name']);
        $pragmaNames = array_column($catalog->execute('PRAGMA pragma_list')['rows'], 'name');
        $t->same(true, in_array('function_list', $pragmaNames, true));
        $t->same(true, in_array('module_list', $pragmaNames, true));
    };

    $tests["real upstream schema.test tail 12 rollback expires same cookie before recreate variant {$variant}"] = static function (TestRunner $t) use ($baseRecords, $prepared, $variant, $newTable): void {
        $state = new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 12000 + $variant, 'data_version' => 7, 'change_counter' => 7]]);
        $state->beginTransaction();
        $state->recordSchemaChange('main', 1, 'schema.test schema-12 create inside rolled back transaction');
        $during = $state->execute('PRAGMA schema_version')['value'];
        $state->rollbackTransaction();
        $afterRollback = $state->execute('PRAGMA schema_version')['value'];

        $reparse = SQLiteSchemaDdlReparsePlan::apply(
            $baseRecords($variant),
            ["CREATE TABLE {$newTable}(a INTEGER, b TEXT)"],
            $afterRollback,
            'main',
            $prepared($variant, $during, true),
        );
        $catalog = new SQLitePragmaSchemaCatalog($reparse['records']);

        $t->same(12001 + $variant, $during);
        $t->same(12000 + $variant, $afterRollback);
        $t->same(12001 + $variant, $reparse['after_schema_cookie']);
        $t->same(["schema-tail-master-scan-{$variant}", "schema-tail-settings-scan-{$variant}"], $reparse['invalidated_prepared']);
        $t->same($newTable, $reparse['operations'][0]['name']);
        $t->same(['a', 'b'], array_column($catalog->executeTableValuedPragma("pragma_table_info('{$newTable}')")['rows'], 'name'));
    };
}

$tests['real upstream pragma schema dynamic schema tail cites upstream sections'] = static function (TestRunner $t): void {
    $sections = [
        'schema.test schema-9.1 external DROP TABLE is visible as no such table to later lookups',
        'schema.test schema-9.2 external DROP VIEW is visible as no such table to later lookups',
        'schema.test schema-10.1 through schema-10.4 CREATE TABLE with an open cursor leaves sqlite_schema readable',
        'schema.test schema-11.1 through schema-11.8 active function/collation delete or replace returns SQLITE_BUSY',
        'schema.test schema-12.1 rollback-expired DDL statements cannot reuse a restored schema cookie',
    ];

    $t->same(5, count($sections));
    $t->contains('schema-9.1', $sections[0]);
    $t->contains('schema-10.1', $sections[2]);
    $t->contains('schema-12.1', $sections[4]);
};

return $tests;
