<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan;

$parents = [
    ['post_id' => 10, 'post_title' => 'Imported parent'],
    ['post_id' => 20, 'post_title' => 'Imported child'],
];
$children = [
    ['meta_id' => 1, 'post_id' => 10, 'meta_key' => '_source'],
    ['meta_id' => 2, 'post_id' => 20, 'meta_key' => '_source'],
];
$triggers = [
    [
        'name' => 'wp_posts_au_orphan_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'when' => ['old.post_id', '=', 10],
        'row' => ['meta_id' => 99, 'post_id' => 10, 'meta_key' => '_old_parent_audit'],
    ],
];

$plan = SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::updateParentsWithinSavepoint(
    $parents,
    $children,
    [['match' => 10, 'set' => ['post_id' => 110, 'post_title' => 'Rekeyed parent']]],
    ['parent_key' => 'post_id', 'child_key' => 'post_id', 'on_update' => 'cascade', 'deferred' => true],
    $triggers,
    ['old.post_id', ['expr' => 'new.post_id', 'as' => 'new_post_id']],
    ['savepoint' => 'wp_import_batch', 'current_source' => 'copied-wp-posts-import', 'next_source' => 'rollback-to-savepoint'],
);

if (in_array('--self-test', $argv, true)) {
    assert(count($plan['returning_rows']) === 1);
    assert($plan['returning_rows'][0]['new_post_id'] === 110);
    assert(array_column($plan['after_statement']['parent'], 'post_id') === [110, 20]);
    assert(array_column($plan['after_savepoint']['parent'], 'post_id') === [10, 20]);
    assert($plan['after_savepoint']['commit_status'] === 'ok-after-rollback-to-savepoint');
    echo "application-trigger-deferred-returning-savepoint self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
