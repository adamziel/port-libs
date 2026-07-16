<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningFkSavepointCurrentNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningFkSavepointCurrentNextPlan;

$parents = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1],
    ['setting_id' => 2, 'key_name' => 'module_registry', 'key_value' => 'a:0:{}', 'load_policy' => 'yes', 'revision' => 2],
    ['setting_id' => 3, 'key_name' => 'module_guard', 'key_value' => 'blocked', 'load_policy' => 'no', 'revision' => 3],
];
$meta = [
    ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'owner', 'meta_value' => 'core'],
    ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'owner', 'meta_value' => 'core'],
    ['meta_id' => 12, 'setting_id' => 3, 'meta_key' => 'owner', 'meta_value' => 'module'],
];

$plan = SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
    $parents,
    $meta,
    [
        'setting_id' => static fn (array $old): int => (int) $old['setting_id'] + 100,
        'key_value' => static fn (array $old): string => $old['key_name'] . ':migrated',
        'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
    ],
    static fn (array $row): bool => $row['load_policy'] === 'yes',
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_update' => 'cascade'],
    [[
        'name' => 'app_settings_bu_url_prefix',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.load_policy', '=', 'yes'],
        'set' => ['key_value' => 'concat:preview::new.key_name'],
        'values' => ['old_key' => 'old.setting_id', 'new_key' => 'new.setting_id'],
    ]],
    ['setting_id', 'key_name', ['expr' => 'old.setting_id', 'as' => 'old_setting_id']],
    ['savepoint' => 'app_setting_fk_returning'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'released');
    assert(array_column($plan['next_child'], 'setting_id') === [101, 102, 3]);
    assert(array_column($plan['returning_rows'], 'old_setting_id') === [1, 2]);
    echo "application-trigger-returning-fk-savepoint-current-next74 self-test passed\n";
    return;
}

echo json_encode([
    'savepoint' => $plan['savepoint'],
    'status' => $plan['status'],
    'returning' => $plan['returning_rows'],
    'foreign_key_actions' => $plan['foreign_key_actions'],
    'next_child_keys' => array_column($plan['next_child'], 'setting_id'),
], JSON_PRETTY_PRINT) . "\n";
