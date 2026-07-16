<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan;

$parents = [
    ['record_id' => 10, 'record_title' => 'Imported parent'],
    ['record_id' => 20, 'record_title' => 'Imported child'],
];
$children = [
    ['detail_id' => 1, 'record_id' => 10, 'detail_key' => '_source'],
    ['detail_id' => 2, 'record_id' => 20, 'detail_key' => '_source'],
];
$triggers = [
    [
        'name' => 'app_items_au_orphan_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'when' => ['old.record_id', '=', 10],
        'row' => ['detail_id' => 99, 'record_id' => 10, 'detail_key' => '_old_parent_audit'],
    ],
];

$plan = SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan::updateParentsWithinSavepoint(
    $parents,
    $children,
    [['match' => 10, 'set' => ['record_id' => 110, 'record_title' => 'Rekeyed parent']]],
    ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_update' => 'cascade', 'deferred' => true],
    $triggers,
    ['old.record_id', ['expr' => 'new.record_id', 'as' => 'new_record_id']],
    ['savepoint' => 'app_import_batch', 'current_source' => 'copied-wp-posts-import', 'next_source' => 'rollback-to-savepoint'],
);

if (in_array('--self-test', $argv, true)) {
    assert(count($plan['returning_rows']) === 1);
    assert($plan['returning_rows'][0]['new_record_id'] === 110);
    assert(array_column($plan['after_statement']['parent'], 'record_id') === [110, 20]);
    assert(array_column($plan['after_savepoint']['parent'], 'record_id') === [10, 20]);
    assert($plan['after_savepoint']['commit_status'] === 'ok-after-rollback-to-savepoint');
    echo "application-trigger-deferred-returning-savepoint self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
