<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer primary key, key_name text, key_value text, load_policy text)', 1),
    $record('table', 'app_setting_audit', 'app_setting_audit', 3, 'CREATE TABLE app_setting_audit(setting_id integer, label text, key_name text)', 2),
    $record('view', 'app_setting_import_view', 'app_setting_import_view', 0, "CREATE VIEW app_setting_import_view AS SELECT setting_id, key_name, key_value, load_policy FROM app_settings WHERE load_policy = 'yes'", 3),
    $record('trigger', 'app_setting_import_view_insert', 'app_setting_import_view', 0, "CREATE TRIGGER app_setting_import_view_insert INSTEAD OF INSERT ON app_setting_import_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.setting_id, new.key_name, new.key_value, new.load_policy); INSERT INTO app_setting_audit(setting_id, label, key_name) VALUES(new.setting_id, 'view-import', new.key_name); SELECT new.setting_id, new.key_name; END", 4),
]);

$plan = SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan::execute(
    $catalog,
    'app_setting_import_view_insert',
    [
        'main.app_settings' => [
            ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
        ],
        'main.app_setting_audit' => [
            ['setting_id' => 1, 'label' => 'seed', 'key_name' => 'base_url'],
        ],
    ],
    [
        ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing.test', 'load_policy' => 'yes'],
        ['setting_id' => 3, 'key_name' => 'site_title', 'key_value' => 'Ported Title', 'load_policy' => 'yes'],
    ],
    [
        ['setting_id' => 4, 'key_name' => 'module_registry', 'key_value' => 'a:0:{}', 'load_policy' => 'yes'],
        ['setting_id' => 5, 'key_name' => 'route_rules', 'key_value' => 'cached', 'load_policy' => 'no'],
    ],
    'app_import',
    ['setting_id', 'key_name', 'value' => 'key_value'],
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $plan['status'] !== 'current-next-view-trigger-returning-applied'
        || $plan['changes'] !== 8
        || array_column($plan['current_returning'], 'key_name') !== ['landing_url', 'site_title']
        || array_column($plan['next_returning'], 'key_name') !== ['module_registry', 'route_rules']
        || array_column($plan['tables']['main.app_settings'], 'key_name') !== ['base_url', 'landing_url', 'site_title', 'module_registry', 'route_rules']
    ) {
        fwrite(STDERR, "application-trigger-view-returning-savepoint-recursive-current-source-next123 self-test failed\n");
        exit(1);
    }

    echo "application-trigger-view-returning-savepoint-recursive-current-source-next123 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $plan['status'],
    'changes' => $plan['changes'],
    'currentReturned' => array_column($plan['current_returning'], 'key_name'),
    'nextSourceSettings' => array_column($plan['next_source_tables']['main.app_settings'], 'key_name'),
    'nextReturned' => array_column($plan['next_returning'], 'key_name'),
    'finalSettings' => array_column($plan['tables']['main.app_settings'], 'key_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
