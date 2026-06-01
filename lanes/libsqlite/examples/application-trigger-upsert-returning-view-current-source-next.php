<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan;

$plan = SQLiteTriggerUpsertReturningViewCurrentSourceNextPlan::execute(
    [
        ['setting_id' => 1, 'key_name' => 'siteurl', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 1, 'source' => 'seed'],
        ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'https://home.test', 'load_policy' => 'yes', 'revision' => 1, 'source' => 'seed'],
    ],
    [
        ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 12, 'name' => 'home', 'value' => 'https://skip.test', 'load_policy_flag' => 'skip'],
        ['import_id' => 13, 'name' => 'fresh_plugin', 'value' => 'enabled', 'load_policy_flag' => 'no'],
    ],
    [
        ['import_id' => 21, 'name' => 'home', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import'],
    ],
    [
        'name' => 'app_setting_import_view',
        'source' => 'main@view-cookie-144-current',
        'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
        'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
        'where' => static fn (array $old, array $incoming): bool => ($incoming['load_policy'] ?? null) !== 'skip',
    ],
    [
        'name' => 'app_setting_import_view',
        'source' => 'main@view-cookie-144-next',
        'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
        'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    ],
    ['key_name'],
    [
        'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
        'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
        'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
        'source' => static fn (array $old, array $incoming): mixed => $incoming['source'] ?? 'current-import',
        'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + 1,
    ],
    [
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
        ['expr' => 'old_or_null.key_value', 'as' => 'oldValue'],
        ['expr' => 'source', 'as' => 'viewSource'],
    ],
    ['savepoint' => 'app_import_view_144', 'trigger' => 'app_settings_view_io_upsert_144'],
);

$summary = [
    'scenario' => 'application-trigger-upsert-returning-view-current-source-next144',
    'applicationUse' => 'Preview a copied app_settings import routed through an INSTEAD OF view trigger where DO UPDATE WHERE skips suppress RETURNING rows and a held savepoint keeps the next view source out of the visible stream.',
    'status' => $plan['status'],
    'nextSourceAdmitted' => $plan['next_source_admitted'],
    'currentReturningNames' => array_column(array_column($plan['current_returning_rows'], 'returning'), 'name'),
    'skippedNames' => array_column(array_column($plan['current_skipped_rows'], 'incoming_row'), 'key_name'),
    'attemptedNextReturningNames' => array_column(array_column($plan['attempted_next_returning_rows'], 'returning'), 'name'),
    'afterSavepointNames' => array_column($plan['after_savepoint'], 'key_name'),
    'dependencies' => $plan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'trigger-upsert-returning-view-current-source-retained-next144');
    assert($summary['nextSourceAdmitted'] === false);
    assert($summary['currentReturningNames'] === ['siteurl', 'fresh_plugin']);
    assert($summary['skippedNames'] === ['home']);
    assert($summary['attemptedNextReturningNames'] === ['home']);
    assert($summary['afterSavepointNames'] === ['siteurl', 'home']);
    echo "application-trigger-upsert-returning-view-current-source-next144 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
