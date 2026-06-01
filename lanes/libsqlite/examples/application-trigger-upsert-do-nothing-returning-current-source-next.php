<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan;

$plan = SQLiteTriggerUpsertDoNothingReturningSavepointCurrentSourceNextPlan::execute(
    [
        ['setting_id' => 1, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
        ['setting_id' => 2, 'tenant_id' => 1, 'key_name' => 'public_url', 'key_value' => 'https://public_url.test', 'load_policy' => 'yes'],
    ],
    [
        ['setting_id' => 10, 'tenant_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://duplicate.test', 'load_policy' => 'yes'],
        ['setting_id' => 11, 'tenant_id' => 1, 'key_name' => 'module_seed', 'key_value' => 'seed', 'load_policy' => 'no'],
    ],
    [
        ['setting_id' => 20, 'tenant_id' => 1, 'key_name' => 'module_seed', 'key_value' => 'seed-next', 'load_policy' => 'no'],
        ['setting_id' => 21, 'tenant_id' => 1, 'key_name' => 'theme_variant', 'key_value' => 'theme', 'load_policy' => 'yes'],
    ],
    ['tenant_id', 'key_name'],
    [
        [
            'name' => 'app_settings_bi_prepare',
            'timing' => 'before',
            'event' => 'insert',
            'action' => 'set-new',
            'when' => ['new.load_policy', '=', 'no'],
            'set' => ['key_value' => 'concat:new.key_value:-prepared'],
            'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
        ],
        [
            'name' => 'app_settings_ai_audit',
            'timing' => 'after',
            'event' => 'insert',
            'action' => 'audit',
            'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
        ],
    ],
    [
        ['expr' => 'new.setting_id', 'as' => 'id'],
        ['expr' => 'new.key_name', 'as' => 'name'],
        ['expr' => 'new.key_value', 'as' => 'value'],
    ],
    [
        'savepoint' => 'app_import_do_nothing_142',
        'current_source' => 'app-settings-current142',
        'next_source' => 'app-settings-next142',
    ],
);

$summary = [
    'scenario' => 'application-trigger-upsert-do-nothing-returning-current-source-next',
    'applicationUse' => 'Preview copied app_settings import batches that use INSERT ... ON CONFLICT DO NOTHING RETURNING inside a savepoint: duplicate setting rows fire BEFORE trigger diagnostics but yield no RETURNING row, while the next source sees either the released current insert or the savepoint image.',
    'status' => $plan['status'],
    'currentReturned' => array_column($plan['current_returning_rows'], 'name'),
    'currentSkipped' => array_column(array_column($plan['current_skipped_conflicts'], 'incoming'), 'key_name'),
    'nextReturned' => array_column($plan['next_returning_rows'], 'name'),
    'nextSkipped' => array_column(array_column($plan['next_skipped_conflicts'], 'incoming'), 'key_name'),
    'committedChanges' => $plan['committed_changes'],
    'dependencyClosure' => 'no new support component needed; this reuses native PHP trigger, UPSERT conflict, RETURNING projection, and savepoint current-source planning',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['status'] === 'trigger-upsert-do-nothing-returning-current-source-next142-released');
    assert($summary['currentReturned'] === ['module_seed']);
    assert($summary['currentSkipped'] === ['base_url']);
    assert($summary['nextReturned'] === ['theme_variant']);
    assert($summary['nextSkipped'] === ['module_seed']);
    assert($summary['committedChanges'] === 2);
    echo "application-trigger-upsert-do-nothing-returning-current-source-next self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
