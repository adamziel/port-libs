<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://home.test', 'load_policy' => 'yes', 'revision' => 1, 'source' => 'seed'],
];

$currentView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-149-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-149-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-trigger-body',
];
$nextView = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-149-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-149-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'audit_label' => 'next-trigger-body',
];

$assignments = [
    'setting_id' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming, string $phase): mixed => $incoming['load_policy'],
    'source' => static fn (array $old, array $incoming, string $phase): string => (string) ($incoming['source'] ?? $phase . '-trigger'),
    'revision' => static fn (array $old, array $incoming, string $phase): int => (int) $old['revision'] + 1,
];

$returning = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
];

$plan = SQLiteTriggerReturningUpsertViewCurrentSourceNextPlan::execute(
    $rows,
    [
        ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 12, 'name' => 'fresh_feature', 'value' => 'enabled', 'load_policy_flag' => 'no'],
    ],
    [
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import'],
        ['import_id' => 22, 'name' => 'cache_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import'],
    ],
    $currentView,
    $nextView,
    ['key_name'],
    $assignments,
    $returning,
    ['key' => 'key_name', 'savepoint' => 'app_import_view_149'],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'trigger-returning-upsert-view-current-source-pinned-next149');
    assert($plan['visible_view']['trigger_source'] === 'main@trigger-cookie-149-current');
    assert($plan['trigger_source_changed'] === true);
    assert($plan['next_returning_rows'] === []);
    assert(array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'name') === ['home', 'cache_rules']);
    assert(array_column($plan['after_savepoint'], 'key_name') === ['siteurl', 'home']);
    echo "application-trigger-returning-upsert-view-current-source-next self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
