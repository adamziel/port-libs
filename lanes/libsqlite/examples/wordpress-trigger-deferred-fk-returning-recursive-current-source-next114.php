<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan;

$parents = [
    ['post_id' => 10, 'post_title' => 'Imported parent'],
    ['post_id' => 20, 'post_title' => 'Imported child'],
    ['post_id' => 30, 'post_title' => 'Imported leaf'],
];
$children = [
    ['meta_id' => 1, 'post_id' => 10, 'meta_key' => '_source'],
    ['meta_id' => 2, 'post_id' => 20, 'meta_key' => '_source'],
    ['meta_id' => 3, 'post_id' => 30, 'meta_key' => '_source'],
];
$triggers = [
    [
        'name' => 'wp_posts_au_enqueue_child',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'enqueue-update',
        'when' => ['old.post_id', '=', 10],
        'match' => 20,
        'set' => ['post_id' => 120, 'post_title' => 'Recursive child'],
        'values' => ['old_id' => 'old.post_id', 'new_id' => 'new.post_id'],
    ],
    [
        'name' => 'wp_posts_au_orphan_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'when' => ['old.post_id', '=', 20],
        'row' => ['meta_id' => 99, 'post_id' => 20, 'meta_key' => '_old_child_audit'],
    ],
];

$plan = SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents(
    $parents,
    $children,
    [['match' => 10, 'set' => ['post_id' => 110, 'post_title' => 'Rekeyed parent']]],
    ['parent_key' => 'post_id', 'child_key' => 'post_id', 'on_update' => 'cascade', 'deferred' => true],
    $triggers,
    ['old.post_id', ['expr' => 'new.post_id', 'as' => 'new_post_id']],
    ['current_source' => 'copied-wp-posts-import', 'next_source' => 'recursive-trigger-drain'],
);

if (in_array('--self-test', $argv, true)) {
    assert(count($plan['returning_rows']) === 2);
    assert(array_column($plan['parent'], 'post_id') === [110, 120, 30]);
    assert($plan['deferred_violations'][0]['child_key'] === 20);
    assert($plan['commit_status'] === 'deferred-constraint-failed');
    echo "wordpress-trigger-deferred-fk-returning-recursive-current-source-next114 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
