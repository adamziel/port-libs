<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerUpsertSavepointReturningCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan;

$parents = [
    ['parent_id' => 10, 'parent_title' => 'Existing page'],
    ['parent_id' => 20, 'parent_title' => 'Existing article'],
];
$child_settings = [
    ['setting_id' => 1, 'parent_id' => 10, 'key_name' => 'source_marker', 'key_value' => 'old', 'revision' => 1, 'source' => 'seed'],
    ['setting_id' => 2, 'parent_id' => 20, 'key_name' => 'source_marker', 'key_value' => 'old-child', 'revision' => 1, 'source' => 'seed'],
];
$incoming = [
    ['setting_id' => 3, 'parent_id' => 10, 'key_name' => 'import_batch', 'key_value' => 'batch-a', 'revision' => 1, 'source' => 'import'],
    ['setting_id' => 4, 'parent_id' => 10, 'key_name' => 'source_marker', 'key_value' => 'updated-source', 'revision' => 2, 'source' => 'import'],
    ['setting_id' => 5, 'parent_id' => 999, 'key_name' => 'missing_parent', 'key_value' => 'orphan', 'revision' => 1, 'source' => 'import'],
];

$plan = SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan::executeDeferredCommit(
    $child_settings,
    $incoming,
    ['key_name'],
    [
        'setting_id' => static fn (array $old, array $row): int => $old['setting_id'],
        'parent_id' => static fn (array $old, array $row): int => $row['parent_id'],
        'key_value' => static fn (array $old, array $row): string => $row['key_value'],
        'revision' => static fn (array $old, array $row): int => $old['revision'] + 1,
        'source' => static fn (array $old, array $row): string => $row['source'],
    ],
    [
        [
            'name' => 'app_child_settings_ai_stamp',
            'timing' => 'after',
            'event' => 'insert',
            'mutate_target' => true,
            'set' => ['source' => 'after-insert-trigger'],
            'values' => ['key' => 'new.key_name', 'parent_id' => 'new.parent_id'],
        ],
        [
            'name' => 'app_child_settings_bu_audit',
            'timing' => 'before',
            'event' => 'update',
            'values' => ['old_value' => 'old.key_value', 'new_value' => 'new.key_value'],
        ],
        [
            'name' => 'app_child_settings_au_stamp',
            'timing' => 'after',
            'event' => 'update',
            'mutate_target' => true,
            'set' => ['source' => 'after-update-trigger'],
            'values' => ['key' => 'new.key_name', 'revision' => 'new.revision'],
        ],
    ],
    ['setting_id', 'parent_id', 'key_name', ['expr' => 'new.revision', 'as' => 'next_revision']],
    $parents,
    [
        'child_table' => 'app_child_settings',
        'parent_table' => 'app_parent_settings',
        'child_key' => 'parent_id',
        'parent_key' => 'parent_id',
        'deferred' => true,
    ],
    [
        'transaction' => 'app_import_txn',
        'current_source' => 'current-upsert-returning-yield',
        'next_source' => 'next-deferred-commit',
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['commit_status'] === 'deferred-foreign-key-failed');
    assert(count($plan['current_returning']) === 3);
    assert($plan['next_returning'] === []);
    assert($plan['deferred_violations'][0]['value'] === 999);
    assert(array_column($plan['after_transaction_rollback'], 'key_name') === ['source_marker', 'source_marker']);
    echo "application-trigger-deferred-upsert-returning-current-source-next135 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-trigger-deferred-upsert-returning-current-source-next135',
    'commit_status' => $plan['commit_status'],
    'yielded_returning_rows' => count($plan['current_returning']),
    'next_returning_rows' => count($plan['next_returning']),
    'deferred_violation_values' => array_column($plan['deferred_violations'], 'value'),
    'restored_key_names' => array_column($plan['after_transaction_rollback'], 'key_name'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
