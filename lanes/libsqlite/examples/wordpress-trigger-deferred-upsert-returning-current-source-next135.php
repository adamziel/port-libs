<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerUpsertSavepointReturningCurrentSourceNext132Plan.php';
require_once __DIR__ . '/../src/SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan;

$parents = [
    ['post_id' => 10, 'post_title' => 'Existing page'],
    ['post_id' => 20, 'post_title' => 'Existing post'],
];
$postmeta = [
    ['meta_id' => 1, 'post_id' => 10, 'meta_key' => '_source', 'meta_value' => 'old', 'revision' => 1, 'source' => 'seed'],
    ['meta_id' => 2, 'post_id' => 20, 'meta_key' => '_source', 'meta_value' => 'old-child', 'revision' => 1, 'source' => 'seed'],
];
$incoming = [
    ['meta_id' => 3, 'post_id' => 10, 'meta_key' => '_import_batch', 'meta_value' => 'batch-a', 'revision' => 1, 'source' => 'import'],
    ['meta_id' => 4, 'post_id' => 10, 'meta_key' => '_source', 'meta_value' => 'updated-source', 'revision' => 2, 'source' => 'import'],
    ['meta_id' => 5, 'post_id' => 999, 'meta_key' => '_missing_parent', 'meta_value' => 'orphan', 'revision' => 1, 'source' => 'import'],
];

$plan = SQLiteTriggerDeferredUpsertReturningCurrentSourceNextPlan::executeDeferredCommit(
    $postmeta,
    $incoming,
    ['meta_key'],
    [
        'meta_id' => static fn (array $old, array $row): int => $old['meta_id'],
        'post_id' => static fn (array $old, array $row): int => $row['post_id'],
        'meta_value' => static fn (array $old, array $row): string => $row['meta_value'],
        'revision' => static fn (array $old, array $row): int => $old['revision'] + 1,
        'source' => static fn (array $old, array $row): string => $row['source'],
    ],
    [
        [
            'name' => 'wp_postmeta_ai_stamp',
            'timing' => 'after',
            'event' => 'insert',
            'mutate_target' => true,
            'set' => ['source' => 'after-insert-trigger'],
            'values' => ['key' => 'new.meta_key', 'post_id' => 'new.post_id'],
        ],
        [
            'name' => 'wp_postmeta_bu_audit',
            'timing' => 'before',
            'event' => 'update',
            'values' => ['old_value' => 'old.meta_value', 'new_value' => 'new.meta_value'],
        ],
        [
            'name' => 'wp_postmeta_au_stamp',
            'timing' => 'after',
            'event' => 'update',
            'mutate_target' => true,
            'set' => ['source' => 'after-update-trigger'],
            'values' => ['key' => 'new.meta_key', 'revision' => 'new.revision'],
        ],
    ],
    ['meta_id', 'post_id', 'meta_key', ['expr' => 'new.revision', 'as' => 'next_revision']],
    $parents,
    [
        'child_table' => 'wp_postmeta',
        'parent_table' => 'wp_posts',
        'child_key' => 'post_id',
        'parent_key' => 'post_id',
        'deferred' => true,
    ],
    [
        'transaction' => 'wp_import_txn',
        'current_source' => 'current-upsert-returning-yield',
        'next_source' => 'next-deferred-commit',
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['commit_status'] === 'deferred-foreign-key-failed');
    assert(count($plan['current_returning']) === 3);
    assert($plan['next_returning'] === []);
    assert($plan['deferred_violations'][0]['value'] === 999);
    assert(array_column($plan['after_transaction_rollback'], 'meta_key') === ['_source', '_source']);
    echo "wordpress-trigger-deferred-upsert-returning-current-source-next135 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-trigger-deferred-upsert-returning-current-source-next135',
    'commit_status' => $plan['commit_status'],
    'yielded_returning_rows' => count($plan['current_returning']),
    'next_returning_rows' => count($plan['next_returning']),
    'deferred_violation_values' => array_column($plan['deferred_violations'], 'value'),
    'restored_meta_keys' => array_column($plan['after_transaction_rollback'], 'meta_key'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
