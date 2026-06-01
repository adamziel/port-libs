<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteAttachTempWalSchemaTriggerPlan;
use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPartialIndexCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$settingsRows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'site_title', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'route_rules', 'load_policy' => 'no'],
];
$settingsIndex = [
    ['rowid' => 1, 'load_policy' => 'yes', 'key_name' => 'base_url'],
    ['rowid' => 2, 'load_policy' => 'yes', 'key_name' => 'site_title'],
];
$settingsPredicate = new SQLiteIndexPredicate('load_policy', SQLiteIndexPredicate::EQUALS, 'yes');

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");
$prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 1, 0x61000001, 0x61000002);
$checksum = SQLiteWal::checksumPair($prefix, false);
$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'sqlite_schema', 'sqlite_schema', 1, 'CREATE TABLE sqlite_schema(type text, name text, tbl_name text, rootpage integer, sql text)', 1),
    $record('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)', 2),
    $record('trigger', 'app_settings_ai', 'app_settings', 0, "CREATE TRIGGER app_settings_ai AFTER UPDATE ON app_settings BEGIN INSERT INTO sqlite_schema(type, name, tbl_name, rootpage, sql) VALUES('index', 'app_settings_load_policy', 'app_settings', 5, new.key_value); END", 3),
]);
$schemaWal = [
    'main' => [
        'wal' => SQLiteWal::parse($prefix . pack('N*', $checksum[0], $checksum[1]), null, true),
        'database_bytes' => $page('main schema before') . $page('settings before'),
        'database_path' => '/tmp/app-settings.sqlite',
        'transactions' => [[
            'pages' => [1 => $page('main schema after'), 2 => $page('settings after')],
            'database_page_count' => 2,
            'commit' => true,
        ]],
        'watch_pages' => [1, 2],
    ],
];
$schemaCache = [
    'main' => ['schema_cookie' => 11, 'tables' => ['sqlite_schema', 'app_settings'], 'file' => '/tmp/app-settings.sqlite'],
];
$triggerNewRow = [
    'setting_id' => 4,
    'key_name' => 'module_registry',
    'key_value' => 'CREATE INDEX app_settings_load_policy ON app_settings(load_policy)',
    'load_policy' => 'yes',
];

return [
    'source-neutral defaults pragma table is app settings' => static function (TestRunner $t) use ($settingsRows, $settingsIndex, $settingsPredicate): void {
        $page = SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page(
            $settingsRows,
            $settingsIndex,
            $settingsPredicate,
            ['load_policy', 'key_name'],
        );

        $t->same('app_settings', $page['current_source']['table']);
        $t->same('idx_app_settings_partial', $page['current_source']['index']);
        $t->same(['load_policy', 'key_name'], $page['current_source']['index_columns']);
        $t->same('ok', $page['status']);
    },
    'source-neutral defaults attach prepared table is app settings' => static function (TestRunner $t) use ($catalog, $schemaWal, $schemaCache, $triggerNewRow): void {
        $plan = SQLiteAttachTempWalSchemaTriggerPlan::plan(
            catalog: $catalog,
            triggerName: 'app_settings_ai',
            schemaWal: $schemaWal,
            schemaCache: $schemaCache,
            newRow: $triggerNewRow,
        );

        $t->same(true, $plan['schema_cache']['prepared_tables']['app_settings']['found']);
        $t->same(true, $plan['schema_cache']['prepared_tables']['app_settings']['requires_reprepare']);
        $t->same(['main'], $plan['reprepare_schemas']);
        $t->same('planned', $plan['status']);
    },
];
