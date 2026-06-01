<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan;

$parents = [
    ['record_id' => 10, 'record_title' => 'Imported parent', 'slug' => 'parent'],
    ['record_id' => 20, 'record_title' => 'Imported child', 'slug' => 'child'],
    ['record_id' => 30, 'record_title' => 'Imported leaf', 'slug' => 'leaf'],
];
$children = [
    ['detail_id' => 1, 'record_id' => 10, 'detail_key' => '_source'],
    ['detail_id' => 2, 'record_id' => 20, 'detail_key' => '_source'],
    ['detail_id' => 3, 'record_id' => 30, 'detail_key' => '_source'],
];
$plan = SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::commitBarrierRetry(
    $parents,
    $children,
    [['match' => 10, 'set' => ['record_id' => 110, 'record_title' => 'Rekeyed parent']]],
    ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_update' => 'cascade', 'deferred' => true],
    [
        [
            'name' => 'app_items_au_enqueue_child',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'enqueue-update',
            'when' => ['old.record_id', '=', 10],
            'match' => 20,
            'set' => ['record_id' => 120, 'record_title' => 'Recursive child'],
        ],
        [
            'name' => 'app_items_au_enqueue_leaf',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'enqueue-update',
            'when' => ['old.record_id', '=', 20],
            'match' => 30,
            'set' => ['record_id' => 130, 'record_title' => 'Recursive leaf'],
        ],
        [
            'name' => 'app_items_au_orphan_audit',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'insert-child',
            'when' => ['old.record_id', '=', 20],
            'row' => ['detail_id' => 99, 'record_id' => 20, 'detail_key' => '_old_child_audit'],
        ],
    ],
    [
        'old.record_id',
        ['expr' => 'new.record_id', 'as' => 'new_record_id'],
        ['expr' => 'new.record_title', 'as' => 'title'],
    ],
    [
        'savepoint' => 'app_import_batch',
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
    echo "application-trigger-deferred-returning-commit-barrier self-test passed\n";
    return;
}

echo json_encode([
    'commit_barrier' => $plan['commit_barrier'],
    'yielded_returning_count' => count($plan['yielded_returning_rows']),
    'invalidated_returning_count' => $plan['invalidated_returning_count'],
    'retry_status' => $plan['retry']['status'],
    'retry_parent_keys' => $plan['retry']['parent_keys'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
