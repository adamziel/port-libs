<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNext114Plan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan;

$parents = [
    ['post_id' => 10, 'post_title' => 'Imported parent', 'slug' => 'parent'],
    ['post_id' => 20, 'post_title' => 'Imported child', 'slug' => 'child'],
    ['post_id' => 30, 'post_title' => 'Imported leaf', 'slug' => 'leaf'],
];
$children = [
    ['meta_id' => 1, 'post_id' => 10, 'meta_key' => '_source'],
    ['meta_id' => 2, 'post_id' => 20, 'meta_key' => '_source'],
    ['meta_id' => 3, 'post_id' => 30, 'meta_key' => '_source'],
];
$plan = SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::commitBarrierRetryNext141(
    $parents,
    $children,
    [['match' => 10, 'set' => ['post_id' => 110, 'post_title' => 'Rekeyed parent']]],
    ['parent_key' => 'post_id', 'child_key' => 'post_id', 'on_update' => 'cascade', 'deferred' => true],
    [
        [
            'name' => 'wp_posts_au_enqueue_child',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'enqueue-update',
            'when' => ['old.post_id', '=', 10],
            'match' => 20,
            'set' => ['post_id' => 120, 'post_title' => 'Recursive child'],
        ],
        [
            'name' => 'wp_posts_au_enqueue_leaf',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'enqueue-update',
            'when' => ['old.post_id', '=', 20],
            'match' => 30,
            'set' => ['post_id' => 130, 'post_title' => 'Recursive leaf'],
        ],
        [
            'name' => 'wp_posts_au_orphan_audit',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'insert-child',
            'when' => ['old.post_id', '=', 20],
            'row' => ['meta_id' => 99, 'post_id' => 20, 'meta_key' => '_old_child_audit'],
        ],
    ],
    [
        'old.post_id',
        ['expr' => 'new.post_id', 'as' => 'new_post_id'],
        ['expr' => 'new.post_title', 'as' => 'title'],
    ],
    [
        'savepoint' => 'wp_import_batch',
        'current_source' => 'current-trigger-returning-yield',
        'next_source' => 'next-deferred-commit-check',
        'retry_source' => 'next-retry-after-rollback-to',
    ],
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['commit_blocked'] === true);
    assert($plan['invalidated_returning_count'] === 3);
    assert($plan['durable_returning_rows'] === []);
    assert($plan['retry']['parent_keys'] === [10, 20, 30]);
    assert($plan['retry']['status'] === 'retry-from-restored-savepoint-image');
    echo "wordpress-trigger-deferred-returning-savepoint-current-source-next141 self-test passed\n";
    return;
}

echo json_encode([
    'commit_barrier' => $plan['commit_barrier'],
    'yielded_returning_count' => count($plan['yielded_returning_rows']),
    'invalidated_returning_count' => $plan['invalidated_returning_count'],
    'retry_status' => $plan['retry']['status'],
    'retry_parent_keys' => $plan['retry']['parent_keys'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
