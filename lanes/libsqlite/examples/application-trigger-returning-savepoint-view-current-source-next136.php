<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteSchemaRecord.php';
require __DIR__ . '/../src/SQLiteAttachedSchemaCatalog.php';
require __DIR__ . '/../src/SQLiteAttachTempViewTriggerResolution.php';
require __DIR__ . '/../src/SQLiteAttachTempViewTriggerYieldPlan.php';
require __DIR__ . '/../src/SQLiteSavepointStack.php';
require __DIR__ . '/../src/SQLiteViewTriggerReturningSavepointPlan.php';
require __DIR__ . '/../src/SQLiteTriggerViewReturningSavepointRecursiveCurrentSourceNextPlan.php';
require __DIR__ . '/../src/SQLiteTriggerReturningSavepointViewCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningSavepointViewCurrentSourceNextPlan;

$catalog = new SQLiteAttachedSchemaCatalog([
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
    new SQLiteSchemaRecord('table', 'wp_option_audit', 'wp_option_audit', 3, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text)', 2),
    new SQLiteSchemaRecord('view', 'wp_option_import_view', 'wp_option_import_view', 0, 'CREATE VIEW wp_option_import_view AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 3),
    new SQLiteSchemaRecord('trigger', 'wp_option_import_view_insert', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'view-import', new.option_name); SELECT new.option_id, new.option_name; END", 4),
    new SQLiteSchemaRecord('trigger', 'wp_option_import_view_insert_rollback', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert_rollback INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'rollback-current-savepoint', new.option_name); SELECT new.option_id, new.option_name; END", 5),
]);

$page = static fn (string $label): string => str_pad($label, 512, '.', STR_PAD_RIGHT);
$plan = SQLiteTriggerReturningSavepointViewCurrentSourceNextPlan::execute(
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
    ],
    [
        ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
        ['option_id' => 5, 'option_name' => 'rewrite_rules', 'option_value' => 'cached', 'autoload' => 'no'],
    ],
    'wp_import_next136',
    ['option_id', 'option_name', 'value' => 'option_value'],
    [
        'current_trigger_name' => 'wp_option_import_view_insert_rollback',
        'current_source' => 'wp-options@before-plugin-import',
        'next_source' => 'wp-options@after-plugin-import',
        'page_size' => 512,
        'savepoint_page_images' => [2 => $page('before-options'), 3 => $page('before-audit')],
        'dirty_pages' => [2 => $page('dirty-options'), 3 => $page('dirty-audit')],
        'wal_start_frame' => 11,
        'wal_frames' => [
            ['frame_index' => 12, 'page_number' => 2],
            ['frame_index' => 13, 'page_number' => 3, 'commit_frame' => true],
        ],
    ],
);

$summary = [
    'scenario' => 'application-trigger-returning-savepoint-view-current-source-next136',
    'applicationUse' => 'Copied wp_options imports through an INSTEAD OF view trigger can roll back a failed current-source row to its savepoint while still admitting next-source RETURNING rows from the saved current image, without requiring ext/sqlite.',
    'status' => $plan['status'],
    'nextInput' => $plan['source_transition']['next_input'],
    'admittedNext' => array_column(array_column($plan['admitted_next_source_stream'], 'returning'), 'option_name'),
    'suppressedCurrent' => array_column(array_column($plan['suppressed_current_source_stream'], 'returning'), 'option_name'),
    'finalOptions' => array_column($plan['tables']['main.wp_options'], 'option_name'),
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'current-view-trigger-rollback-next-source-admitted');
    assert($summary['nextInput'] === 'saved-current-source');
    assert($summary['admittedNext'] === ['active_plugins', 'rewrite_rules']);
    assert($summary['suppressedCurrent'] === ['home']);
    assert($summary['finalOptions'] === ['siteurl', 'active_plugins', 'rewrite_rules']);
    echo "application-trigger-returning-savepoint-view-current-source-next136 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
