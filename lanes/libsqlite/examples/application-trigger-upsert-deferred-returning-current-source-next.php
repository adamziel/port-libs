<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'parent_setting_id' => 10, 'revision' => 1],
    ['setting_id' => 2, 'key_name' => 'dashboard_url', 'key_value' => 'https://dashboard_url.test', 'load_policy' => 'yes', 'parent_setting_id' => 10, 'revision' => 1],
];
$parents = [
    ['parent_id' => 10, 'name' => 'core'],
    ['parent_id' => 20, 'name' => 'regional'],
];
$assignments = [
    'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
    'parent_setting_id' => static fn (array $old, array $incoming): mixed => $incoming['parent_setting_id'],
    'revision' => static fn (array $old, array $incoming): mixed => $old['revision'] + 1,
];
$triggers = [[
    'name' => 'app_settings_bu_base_url_suffix',
    'timing' => 'before',
    'event' => 'update',
    'action' => 'set-new',
    'when' => ['new.key_name', '=', 'base_url'],
    'set' => ['key_value' => 'concat:new.key_value:/app'],
]];
$returning = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.setting_id', 'as' => 'id'],
    ['expr' => 'old_or_null.setting_id', 'as' => 'old_id'],
    ['expr' => 'new.parent_setting_id', 'as' => 'parent_id'],
];

$summary = SQLiteTriggerUpsertDeferredReturningCurrentSourceNextPlan::execute(
    $rows,
    [
        ['setting_id' => 11, 'key_name' => 'base_url', 'key_value' => 'https://broken.test', 'load_policy' => 'yes', 'parent_setting_id' => 99, 'revision' => 0],
        ['setting_id' => 12, 'key_name' => 'fresh_bad', 'key_value' => 'bad', 'load_policy' => 'no', 'parent_setting_id' => 98, 'revision' => 0],
    ],
    [
        ['setting_id' => 21, 'key_name' => 'base_url', 'key_value' => 'https://retry.test', 'load_policy' => 'yes', 'parent_setting_id' => 20, 'revision' => 0],
        ['setting_id' => 22, 'key_name' => 'fresh_good', 'key_value' => 'ok', 'load_policy' => 'no', 'parent_setting_id' => 10, 'revision' => 0],
    ],
    ['key_name'],
    $assignments,
    $triggers,
    $returning,
    $parents,
    [
        'child_key' => 'parent_setting_id',
        'parent_key' => 'parent_id',
        'child_table' => 'app_settings',
        'parent_table' => 'app_setting_groups',
        'deferred' => true,
    ],
    [
        'savepoint' => 'app_import_deferred',
        'current_source' => 'app-settings-current',
        'next_source' => 'app-settings-next',
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'trigger-upsert-deferred-returning-current-source-next137-rolled-back');
    assert($summary['commit_blocked_after_returning'] === true);
    assert(array_column($summary['returning_rows'], 'name') === ['base_url', 'fresh_good']);
    assert(array_column($summary['attempted_current_returning_rows'], 'name') === ['base_url', 'fresh_bad']);
    assert($summary['next_rows'][0]['key_value'] === 'https://retry.test/app');
    echo "application-trigger-upsert-deferred-returning-current-source-next137 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
