<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan;

$record = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$catalog = new SQLiteAttachedSchemaCatalog([
    $record('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    $record('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text)', 2),
    $record('view', 'wp_option_import_view', 'wp_option_import_view', 0, "CREATE VIEW wp_option_import_view AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = 'yes'", 3),
    $record('trigger', 'wp_option_import_view_insert', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'view-import', new.option_name); SELECT new.option_id, new.option_name; END", 4),
]);

$plan = SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan::execute(
    $catalog,
    'wp_option_import_view_insert',
    [
        'main.wp_options' => [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
        ],
        'main.wp_option_audit' => [
            ['option_id' => 1, 'label' => 'seed', 'option_name' => 'siteurl'],
        ],
    ],
    [
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Ported Site', 'autoload' => 'yes'],
    ],
    [
        ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'option_value' => 'cached', 'autoload' => 'no'],
    ],
    'wp_import',
    ['option_id', 'option_name', 'value' => 'option_value'],
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $plan['status'] !== 'current-next-view-trigger-returning-applied'
        || $plan['changes'] !== 8
        || array_column($plan['current_returning'], 'option_name') !== ['home', 'blogname']
        || array_column($plan['next_returning'], 'option_name') !== ['active_plugins', 'rewrite_rules']
        || array_column($plan['tables']['main.wp_options'], 'option_name') !== ['siteurl', 'home', 'blogname', 'active_plugins', 'rewrite_rules']
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
    'currentReturned' => array_column($plan['current_returning'], 'option_name'),
    'nextSourceOptions' => array_column($plan['next_source_tables']['main.wp_options'], 'option_name'),
    'nextReturned' => array_column($plan['next_returning'], 'option_name'),
    'finalOptions' => array_column($plan['tables']['main.wp_options'], 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
